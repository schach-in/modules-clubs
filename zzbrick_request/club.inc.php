<?php

/**
 * clubs module
 * output of a data for a single club
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @author Falco Nogatz <fnogatz@gmail.com>
 * @copyright Copyright © 2015-2025 Gustaf Mossakowski
 * @copyright Copyright © 2020, 2023 Falco Nogatz <fnogatz@gmail.com>
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_clubs_club($params, $settings) {
	// this script is getting all URLs, shortcuts for URLs that definitely
	// are not for this script
	if(mod_clubs_club_known_urls()) return false;
	if (!isset($params[0])) return false;
	$edit = $settings['edit'] ?? false;
	if ($edit) {
		mf_clubs_deny_bots();
		wrap_setting('cache', false);
	}
	if (count($params) === 2) {
		// funny URLs like http://schach.in/[club]/%20'A=0
		if (substr($params[1], 0, 1) === '%') return false;
		if (substr($params[1], 0, 1) === '+') return false;
		return brick_format('%%% request clubs '.$params[0].' '.$params[1].' %%%');
	}
	if (count($params) !== 1) return false;

	// @todo set if to read `state` in categories.parameters
	$sql = 'SELECT org.contact_id, org.contact, org.contact_short
			, YEAR(org.end_date) AS end_date, org.start_date, org.description
			, ok.identifier AS zps_code
			, members, members_female, members_u25, (YEAR(CURDATE()) - avg_byear) AS avg_age, avg_rating
			, members_passive
			, SUBSTRING_INDEX(categories.path, "/", -1) AS category
			, IF(categories.category_id = /*_ID categories contact/school _*/, 1, NULL) AS schulschachgruppe
			, IF(categories.category_id = /*_ID categories contact/kindergarten _*/, 1, NULL) AS schachkindergarten
			, IF(categories.category_id = /*_ID categories contact/club _*/, 1, NULL) AS verein
			, IF(categories.category_id = /*_ID categories contact/chess-department _*/, 1, NULL) AS schachabteilung
			, IF(categories.category_id = /*_ID categories contact/hort _*/, 1, NULL) AS schachhort
			, categories.category_id
			, (SELECT COUNT(*) FROM contacts_contacts members
				WHERE members.main_contact_id = org.contact_id
				AND members.relation_category_id = /*_ID categories relation/member _*/) AS member_orgs
			, categories.parameters
			, countries.country, countries.identifier AS country_identifier
			, IF(categories.category_id IN (
				/*_ID categories contact/school _*/, /*_ID categories contact/kindergarten _*/, /*_ID categories contact/hort _*/), 1, NULL
			) AS state
			, org.identifier
			, org.parameters AS contact_parameters
		FROM contacts org
		LEFT JOIN categories
			ON org.contact_category_id = categories.category_id
		LEFT JOIN clubstats USING (contact_id)
		LEFT JOIN contacts_identifiers ok
			ON ok.contact_id = org.contact_id
			AND ok.identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
			AND NOT ISNULL(ok.current)
		LEFT JOIN countries
			ON org.country_id = countries.country_id
		WHERE org.identifier = "%s"
		AND categories.parameters LIKE "%%&clubpage=1%%"
	';
	$sql = sprintf($sql, wrap_db_escape($params[0]));
	$org = wrap_db_fetch($sql);
	if (!$org) {
		return brick_format('%%% request clubs '.$params[0].' %%%');
	}
	if (in_array($org['category'], ['verband']) AND $org['member_orgs']) {
		return brick_format('%%% request clubs '.$params[0].' %%%');
	}
	parse_str($org['parameters'], $org['parameters']);
	if ($org['contact_parameters'])
		parse_str($org['contact_parameters'], $org['contact_parameters']);
	else
		$org['contact_parameters'] = [];
	$org += mf_contacts_contactdetails($org['contact_id']);
	// remove old URLs
	if (!empty($org['url']))
		foreach ($org['url'] as $index => $url)
			if (!empty($url['parameters']['hidden'])) unset($org['url'][$index]);
	if ($org['members'] < wrap_setting('clubs_stats_min_members'))
		$org['keine_statistik'] = true;
	$org['edit'] = $edit;
	if ($org['end_date']) {
		$sql = 'SELECT contact AS nachfolger, identifier AS nachfolger_kennung
			FROM contacts_contacts
			LEFT JOIN contacts
				ON contacts.contact_id = contacts_contacts.main_contact_id
			WHERE relation_category_id = /*_ID categories relation/successor _*/
			AND contacts_contacts.contact_id = %d';
		$sql = sprintf($sql, $org['contact_id']);
		$org += wrap_db_fetch($sql);
		$page['status'] = 410;
		$org['edit'] = false;
	}
	if ($org['edit']) {
		if (empty($_SESSION['logged_in'])) {
			$mpage = wrap_session_check('clubedit');
			if ($mpage !== true) return $mpage;
		}
		if (empty($_SESSION['user_id']))
			mf_clubs_add_user_from_ip();
		$org['logged_in'] = $_SESSION['logged_in'] ?? false;
	}

	if (in_array('ratings', wrap_setting('modules'))) {
		wrap_include('functions', 'ratings');
		$org['topten'] = mf_ratings_toplist($org);
	}

	if (!empty($org['parameters']['has_place_contact'])) {
		$sql = 'SELECT places.contact_id, cc_id
				, contact, address
				, postcode, place, description
				, latitude, longitude, contacts_contacts.remarks, contacts_contacts.published, sequence
			FROM contacts_contacts
			LEFT JOIN contacts places
				ON contacts_contacts.contact_id = places.contact_id
			LEFT JOIN addresses
				ON places.contact_id = addresses.contact_id
			WHERE contacts_contacts.main_contact_id = %d
			AND contacts_contacts.relation_category_id = /*_ID categories relation/venue _*/
			ORDER BY sequence, places.contact, postcode, place, address';
		$sql = sprintf($sql, $org['contact_id']);
		$org['places'] = wrap_db_fetch($sql, 'contact_id');
		$details = mf_contacts_contactdetails(array_keys($org['places']));
	} else {
		$addresses = mf_contacts_addresses($org['contact_id']);
		if ($addresses)
			$org['places'][$org['contact_id']] = reset($addresses); // only one = @todo change key to address_id
		$details[$org['contact_id']] = mf_contacts_contactdetails($org['contact_id']);
	}

	// website, telefon, telefax, e_mail
	foreach ($details as $contact_id => $contactdetails)
		$org['places'][$contact_id]['details'] = $contactdetails;

	$sql = 'SELECT team_id, event, team, team_no
			, IF(tournaments.teilnehmerliste = "ja", teams.identifier, events.identifier) AS team_identifier
			, events.event
			, CONCAT(events.date_begin, "/", IFNULL(events.date_end, "")) AS duration
			, categories.category AS series
			, (SELECT platz_no FROM tabellenstaende WHERE tabellenstaende.team_id = teams.team_id AND tabellenstaende.runde_no = tournaments.runden) AS platz_no
		FROM teams
		LEFT JOIN events USING (event_id)
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories
			ON events.series_category_id = categories.category_id
		WHERE club_contact_id = %d
		AND teams.team_status = "Teilnehmer"
		ORDER BY IFNULL(events.date_begin, events.date_end) DESC, events.event DESC
	';
	$sql = sprintf($sql, $org['contact_id']);
	$org['teams'] = wrap_db_fetch($sql, 'team_id');

	if ($org['verein'] OR $org['schachabteilung'])
		$org['parent_orgs'] = mf_clubs_parent_orgs($org['contact_id']);
	
	// Main Contact
	$sql = 'SELECT contact AS main_contact
		FROM contacts
		LEFT JOIN contacts_contacts
			ON contacts.contact_id = contacts_contacts.main_contact_id
		WHERE contacts_contacts.contact_id = %d
		AND relation_category_id = /*_ID categories relation/member _*/';
	$sql = sprintf($sql, $org['contact_id']);
	$org['main_contact'] = wrap_db_fetch($sql, '', 'single value');
	
	// Auszeichnungen
	$sql = 'SELECT award_id, category_id, category, award_year, award_year_to
			, SUBSTRING_INDEX(path, "/", -1) AS path
		FROM awards
		LEFT JOIN categories
			ON awards.award_category_id = categories.category_id
		WHERE contact_id = %d
		ORDER BY categories.sequence, award_year';
	$sql = sprintf($sql, $org['contact_id']);
	$org['awards'] = wrap_db_fetch($sql, ['category', 'award_id'], 'list category award_year');
	foreach ($org['awards'] as $key => $awards) {
		$auszeichnung = reset($awards['award_year']);
		$org['awards'][$key]['path'] = $auszeichnung['path'];
	}
	$org['awards'] = array_values($org['awards']);

	// Wochentermine
	$sql = 'SELECT wochentermin_id, place_contact_id, wochentag
			, uhrzeit_beginn
			, uhrzeit_ende
			, wochentermine.beschreibung AS wbeschreibung, woche_im_monat, category
			, oeffentlich
			, IF(woche_im_monat = "letzte", 1, NULL) AS letzte
		FROM wochentermine
		LEFT JOIN categories
			ON categories.category_id = wochentermine.wochentermin_category_id
		WHERE contact_id = %d
		ORDER BY wochentag, uhrzeit_beginn';
	$sql = sprintf($sql, $org['contact_id']);
	$wochentermine = wrap_db_fetch($sql, 'wochentermin_id');

	if ($org['edit']) {
		wrap_include('revisions', 'zzform');
		$revisions = zz_revisions_read('contacts', $org['contact_id']);
		foreach ($revisions as $key => $value) {
			if (is_array($value)) {
				if (str_starts_with($key, 'contactdetails')) {
					if (key($value) <= 0) {
						foreach ($value as $v_key => $v_value) {
							if (!$v_value) continue;
							$sql = 'SELECT category_id, category, parameters
								FROM categories WHERE category_id = %d';
							$sql = sprintf($sql, $v_value['provider_category_id']);
							$category = wrap_db_fetch($sql);
							if (!$category) continue; // should not happen
							parse_str($category['parameters'], $category_parameters);
							$org[$category_parameters['type']][] = array_merge($v_value, $category);
						}
					} else {
						foreach ($org as $o_key => $o_value) {
							if (!is_array($o_value)) continue;
							foreach ($value as $v_key => $v_value) {
								if (empty($o_value[0]['contactdetail_id'])) continue; // other data
								if ($o_value[0]['contactdetail_id'] != $v_key) continue;
								if ($v_value)
									$org[$o_key][0] = array_merge($org[$o_key][0], $v_value);
								else
									$org[$o_key][0] = false;
							}
						}
					}
				} else {
					// ...
					echo wrap_print('not yet supported');
					exit;
				}
			} else {
				$org[$key] = $value;
				if ($key === 'contact_category_id') {
					switch($value) {
					case wrap_category_id('contact/club'):
						$org['schachabteilung'] = NULL;
						$org['verein'] = 1;
						break;
					case wrap_category_id('contact/chess-department'):
						$org['schachabteilung'] = 1;
						$org['verein'] = NULL;
					}
				}
			}
		}
		$places_sort = [];
		foreach ($org['places'] as $contact_id => $place) {
			$revisions = zz_revisions_read('contacts_contacts', $place['cc_id']);
			if (is_null($revisions)) {
				unset($org['places'][$contact_id]);
				continue;
			} elseif ($revisions) {
				// ...
				echo wrap_print('not yet supported');
				exit;
			}
			$revisions = zz_revisions_read('contacts', $place['contact_id']);
			foreach ($revisions as $key => $value) {
				if (is_array($value)) {
					foreach ($value as $subtable) {
						if (is_array($subtable)) {
							foreach ($subtable as $subkey => $subvalue)
								$org['places'][$contact_id][$subkey] = $subvalue;
						} else {
							// @todo something was deleted, e. g. contact details
							// but these are not shown here anyways
						}
					}
				} else {
					$org['places'][$contact_id][$key] = $value;
				}
			}
			$place = $org['places'][$contact_id];
			$places_sort[] = sprintf('%02d-%s-%s-%s-%s'
				, $place['sequence'], $place['contact'], $place['postcode']
				, $place['place'], $place['address']
			);
		}
		$keys = array_keys($org['places']);
		array_multisort($org['places'], $places_sort);
		array_multisort($keys, $places_sort);
		$org['places'] = array_combine($keys, $org['places']);
		foreach ($wochentermine as $id => $wochentermin) {
			$revisions = zz_revisions_read('wochentermine', $id);
			if (is_null($revisions)) {
				unset($wochentermine[$id]);
				continue;
			}
			foreach ($revisions as $key => $value) {
				if ($key === 'woche_im_monat') 
					$wochentermine[$id]['letzte'] = $value === 'letzte' ? 1 : NULL;
				if ($key === 'wochentermin_category_id') {
					$sql = 'SELECT category FROM categories WHERE category_id = %d';
					$sql = sprintf($sql, $value);
					$value = wrap_db_fetch($sql, '', 'single value');
					if ($value) $key = 'category';
				}
				if ($key === 'beschreibung') $key = 'wbeschreibung';
				$wochentermine[$id][$key] = $value;
			}
		}
	}
	// nichtöffentliche Orte entfernen
	foreach ($org['places'] as $contact_id => $place) {
		if (empty($place['published'])) continue;
		if ($place['published'] === 'no') unset($org['places'][$contact_id]);
	}
	foreach ($wochentermine as $id => $wochentermin) {
		if ($wochentermin['oeffentlich'] === 'nein') unset($wochentermine[$id]);
	}

	if (!empty($org['places'])) {
		foreach ($wochentermine as $wochentermin) {
			if (!$wochentermin['place_contact_id']) {
				$place = reset($org['places']);
				$contact_id = $place['contact_id'];
			} else {
				foreach ($org['places'] as $place) {
					if ($place['contact_id'] !== $wochentermin['place_contact_id']) continue;
					$contact_id = $place['contact_id'];
				}
				if (!$contact_id) {
					// @todo read from database which place
					continue;
				}
			}
			$org['places'][$contact_id]['wochentermine'][] = $wochentermin;
		}
	}

	// Karte mit Spielorten
	foreach ($org['places'] as $id => $place) {
		if (empty($place['longitude'])) continue; // platforms
		if ($org['edit']) $org['places'][$id]['edit'] = true;
		$longitude[] = $place['longitude'];
		$latitude[] = $place['latitude'];
		if (!isset($org['lon_min'])) {
			$org['lon_min'] = $place['longitude'];
			$org['lon_max'] = $place['longitude'];
			$org['lat_min'] = $place['latitude'];
			$org['lat_max'] = $place['latitude'];
		} else {
			if ($place['longitude']) {
				if ($place['longitude'] < $org['lon_min']) $org['lon_min'] = $place['longitude'];
				if ($place['longitude'] > $org['lon_max']) $org['lon_max'] = $place['longitude'];
			}
			if ($place['latitude']) {
				if ($place['latitude'] < $org['lat_min']) $org['lat_min'] = $place['latitude'];
				if ($place['latitude'] > $org['lat_max']) $org['lat_max'] = $place['latitude'];
			}
		}
	}
	if (!empty($longitude)) {
		$org['longitude'] = array_sum($longitude) / count($longitude);
		$org['latitude'] = array_sum($latitude) / count($latitude);
	}
	$org['count_places'] = count($org['places']);

	wrap_setting('leaflet_fullscreen', true);
	$page['title'] = $org['contact'];
	if ($org['schachabteilung']) {
		$page['title'] .= ' <br><em>Schachabteilung</em>'; 
	}
	if ($org['edit']) {
		$page['breadcrumbs'][] = ['title' => $org['contact_short'] ?? $org['contact'], 'url_path' => '../'];
		$page['breadcrumbs'][]['title'] = 'Bearbeiten';
		$page['meta'][] = ['name' => 'robots', 'content' => 'noindex, follow, noarchive'];
	} else {
		$page['breadcrumbs'][]['title'] = $org['contact_short'] ?? $org['contact'];
	}
	$page['head'] = wrap_template('leaflet-head');
	if (!empty($org['lat_min']))
		$page['extra']['id'] = 'clubmap';
	$page['dont_show_h1'] = true;
	if (!empty($org['description'])) {
	    $description = markdown($org['description']);
	    $description = strip_tags($description);
	    $description = str_replace("\n", " ", $description);
		$description = substr($description, 0, 160);
		$page['opengraph']['og:description'] = substr($description, 0, strrpos($description, ' '));
	} else {
		$page['opengraph']['og:description'] = 'Profil bei schach.in';
	}
	if (mf_clubs_opengraph_supported($org)) {
		$page['opengraph']['og:width'] = '1200';
		$page['opengraph']['og:height'] = '630';
		$page['opengraph']['og:image'] = wrap_setting('host_base') . sprintf('/%s/opengraph.png', $org['identifier']);
		$page['meta'][] = ['name' => 'twitter:card', 'content' => 'summary_large_image'];
		$page['meta'][] = ['name' => 'twitter:image', 'content' => wrap_setting('host_base') .sprintf('/%s/opengraph.png', $org['identifier'])];
	}
	$page['text'] = wrap_template('club', $org);
	return $page;
}

/**
 * check if URL is definitely not for this script
 * and return (mostly scripts who randomly try to find security flaws)
 *
 * @return bool true: something was found
 */
function mod_clubs_club_known_urls() {
	$uri = parse_url(wrap_setting('host_base').wrap_setting('request_uri'));
	if (empty($uri['path'])) return false;
	foreach (wrap_setting('clubs_unwanted_path_beginnings') as $beginning)
		if (str_starts_with($uri['path'], $beginning)) return true;
	foreach (wrap_setting('clubs_unwanted_file_endings') as $ending)
		if (str_ends_with($uri['path'], '.'.$ending)) return true;
	// no human uses underscore in search words
	if (strpos($uri['path'], '_') !== false) return true;
	
	return false;
}
