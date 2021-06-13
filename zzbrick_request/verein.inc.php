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
 * @copyright Copyright © 2015-2021 Gustaf Mossakowski
 * @copyright Copyright © 2020      Falco Nogatz <fnogatz@gmail.com>
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_clubs_verein($params) {
	global $zz_setting;
	global $zz_conf;
	
	require_once $zz_setting['custom_wrap_dir'].'/personen.inc.php';

	if (!isset($params[0])) return false;
	if (strstr($params[0], '=')) return false;
	$edit = false;
	if ((count($params) === 3 OR count($params) === 4) AND $params[1] === 'bearbeiten') {
		$zz_setting['cache'] = false;
		$sql = 'SELECT org_id, organisation
			FROM organisationen
			WHERE identifier = "%s"
			AND ISNULL(aufloesung)';
		$sql = sprintf($sql, wrap_db_escape($params[0]));
		$org = wrap_db_fetch($sql);
		if (!$org) return false;
		$page = [];
		wrap_session_start();
		if (empty($_SESSION)) {
			return brick_format('%%% redirect /'.$params[0].'/bearbeiten/ %%%');
		}
		if (count($params) === 3) {
			switch ($params[2]) {
			case 'info':
				$page = brick_format('%%% forms vereinbearbeiten '.$org['org_id'].' %%%');
				break;
			case 'ort-neu':
				$page = brick_format('%%% forms ortbearbeiten '.$org['org_id'].' add %%%');
				break;
			case 'wochentermin-neu':
				$page = brick_format('%%% forms wochenterminbearbeiten '.$org['org_id'].' add woche %%%');
				break;
			case 'monatstermin-neu':
				$page = brick_format('%%% forms wochenterminbearbeiten '.$org['org_id'].' add monat %%%');
				break;
			}
		} elseif (count($params) === 4) {
			switch ($params[2]) {
			case 'ort-bearbeiten':
				$page = brick_format('%%% forms ortbearbeiten '.$org['org_id'].' edit '.$params[3].' %%%');
				break;
			case 'ort-loeschen':
				$page = brick_format('%%% forms ortloeschen '.$org['org_id'].' '.$params[3].' %%%');
				break;
			case 'wochentermin-bearbeiten':
				$page = brick_format('%%% forms wochenterminbearbeiten '.$org['org_id'].' edit '.$params[3].' %%%');
				break;
			case 'wochentermin-loeschen':
				$page = brick_format('%%% forms wochenterminloeschen '.$org['org_id'].' '.$params[3].' %%%');
				break;
			}
		}
		if (!$page) return false;
		if (empty($page['head'])) $page['head'] = ''; // might come from forms!
		$page['dont_show_h1'] = true;
		$page['meta'][] = [
			'name' => 'robots',
			'content' => 'noindex, follow, noarchive'
		];
		$page['title'] = 'Bearbeiten: '.$org['organisation'];
		unset($page['breadcrumbs']);
		if (count($params) === 3) {
			$page['breadcrumbs'][] = sprintf('<a href="../../">%s</a>', $org['organisation']);
			$page['breadcrumbs'][] = '<a href="../">Bearbeiten</a>';
		} elseif (count($params) === 4) {
			$page['breadcrumbs'][] = sprintf('<a href="../../../">%s</a>', $org['organisation']);
			$page['breadcrumbs'][] = '<a href="../../">Bearbeiten</a>';
		}
		switch ($params[2]) {
		case 'info':
			$page['breadcrumbs'][] = 'Allgemeine Infos';
			break;
		case 'ort-loeschen':
		case 'ort-bearbeiten':
		case 'ort-neu':
			$page['breadcrumbs'][] = 'Spielorte';
			break;
		case 'wochentermin-loeschen':
		case 'wochentermin-bearbeiten':
		case 'wochentermin-neu':
			$page['breadcrumbs'][] = 'Wochentermine';
			break;
		case 'monatstermin-neu':
			$page['breadcrumbs'][] = 'Monatstermine';
			break;
		}
		return $page;
	} elseif (count($params) === 2) {
		$zz_setting['cache'] = false;
		if ($params[1] === 'bearbeiten') {
			$edit = true;
			array_pop($params);
		} else {
			// funny URLs like http://schach.in/[club]/%20'A=0
			if (substr($params[1], 0, 1) === '%') return false;
			if (substr($params[1], 0, 1) === '+') return false;
			return brick_format('%%% request vereine '.$params[0].' '.$params[1].' %%%');
		}
	}
	if (count($params) !== 1) return false;

	$sql = 'SELECT org.org_id, org.organisation
			, org.website, YEAR(org.aufloesung) AS aufloesung, org.gruendung, org.description
			, ok.identifier AS zps_code
			, members, members_female, members_u25, (YEAR(CURDATE()) - avg_byear) AS avg_age, avg_rating
			, nachfolger.organisation AS nachfolger, nachfolger.identifier AS nachfolger_kennung
			, SUBSTRING_INDEX(categories.path, "/", -1) AS category
			, IF(SUBSTRING_INDEX(categories.path, "/", -1) = "schulschachgruppe", 1, NULL) AS schulschachgruppe
			, IF(SUBSTRING_INDEX(categories.path, "/", -1) = "schachkindergarten", 1, NULL) AS schachkindergarten
			, IF(SUBSTRING_INDEX(categories.path, "/", -1) = "verein", 1, NULL) AS verein
			, IF(org.abteilung = "ja", 1, NULL) AS schachabteilung
			, (SELECT COUNT(org_id) FROM organisationen members WHERE members.mutter_org_id = org.org_id) AS member_orgs
		FROM organisationen org
		LEFT JOIN categories
			ON org.contact_category_id = categories.category_id
		LEFT JOIN vereinsdb_stats USING (org_id)
		LEFT JOIN organisationen_kennungen ok
			ON ok.org_id = org.org_id
			AND ok.identifier_category_id = %d
			AND NOT ISNULL(ok.current)
		LEFT JOIN organisationen nachfolger
			ON org.nachfolger_org_id = nachfolger.org_id
		WHERE org.identifier = "%s"
	';
	$sql = sprintf($sql, wrap_category_id('kennungen/zps'),
		wrap_db_escape($params[0])
	);
	$org = wrap_db_fetch($sql);
	if (!$org) {
		return brick_format('%%% request vereine '.$params[0].' %%%');
	}
	if (in_array($org['category'], ['verband']) AND $org['member_orgs']) {
		return brick_format('%%% request vereine '.$params[0].' %%%');
	}
	if ($org['members'] < wrap_get_setting('clubs_statistik_min_mitglieder')) {
		$org['keine_statistik'] = true;
	}
	$org['edit'] = $edit;
	if ($org['aufloesung']) {
		$page['status'] = 410;
		$org['edit'] = false;
	}
	if ($org['edit']) {
		if (empty($_SESSION['logged_in'])) {
			$mpage = wrap_session_check('clubedit');
			if ($mpage !== true) return $mpage;
		}
		if (empty($_SESSION['user_id'])) {
			mf_clubs_add_user_from_ip();
		}
		$org['logged_in'] = !empty($_SESSION['logged_in']) ? true : false;
		$org['remote_addr'] = $_SERVER['REMOTE_ADDR'];
		$org['request_uri'] = $_SERVER['REQUEST_URI'];
	}

	if ($org['verein']) {
		$sql = 'SELECT FIDE_Titel, Spielername, DWZ, FIDE_Elo
			FROM dwz_spieler
			WHERE ZPS = "%s"
			AND (Status = "A" OR ISNULL(Status))
			ORDER BY DWZ DESC
			LIMIT 10';
		$sql = sprintf($sql, $org['zps_code']);
		$org['topten'] = wrap_db_fetch($sql, '_dummy_', 'numeric');
		$i = 1;
		foreach ($org['topten'] as $index => $spieler) {
			$org['topten'][$index]['no'] = $i;
			$spieler = explode(',', $spieler['Spielername']);
			$spieler = array_reverse($spieler);
			$org['topten'][$index]['spieler'] = implode(' ', $spieler);
			$i++;
		}
	}

	$sql = 'SELECT places.contact_id, cc_id
			, contact, address
			, postcode, place, description
			, latitude, longitude, organisationen_orte.remarks, organisationen_orte.published, sequence
		FROM organisationen_orte
		LEFT JOIN contacts places
			ON organisationen_orte.contact_id = places.contact_id
		LEFT JOIN addresses
			ON places.contact_id = addresses.contact_id
		WHERE org_id = %d
		ORDER BY sequence, places.contact, postcode, place, address';
	$sql = sprintf($sql, $org['org_id']);
	$org['orte'] = wrap_db_fetch($sql, 'contact_id');

	// website, telefon, telefax, e_mail
	$details = my_contactdetails(array_keys($org['orte']));
	foreach ($details as $contact_id => $contactdetails) {
		$org['orte'][$contact_id]['details'] = $contactdetails;
	}

	$sql = 'SELECT team_id, event, team, team_no
			, teams.kennung AS team_identifier
			, events.event
			, CONCAT(events.date_begin, "/", IFNULL(events.date_end, "")) AS duration
			, categories.category AS series
			, (SELECT platz_no FROM tabellenstaende WHERE tabellenstaende.team_id = teams.team_id AND tabellenstaende.runde_no = tournaments.runden) AS platz_no
		FROM teams
		LEFT JOIN events USING (event_id)
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories
			ON events.series_category_id = categories.category_id
		WHERE verein_org_id = %d
		AND teams.team_status = "Teilnehmer"
		AND tournaments.teilnehmerliste = "ja"
		ORDER BY IFNULL(events.date_begin, events.date_end) DESC, events.event DESC
	';
	$sql = sprintf($sql, $org['org_id']);
	$org['teams'] = wrap_db_fetch($sql, 'team_id');

	if ($org['verein']) {
		$org['parent_orgs'] = mf_clubs_parent_orgs($org['org_id']);
	}
	
	// Auszeichnungen
	$sql = 'SELECT auszeichnung_id, category_id, category, dauer_von, dauer_bis
			, SUBSTRING_INDEX(path, "/", -1) AS path
		FROM auszeichnungen
		LEFT JOIN categories
			ON auszeichnungen.auszeichnung_category_id = categories.category_id
		WHERE org_id = %d
		ORDER BY categories.sequence, dauer_von';
	$sql = sprintf($sql, $org['org_id']);
	$org['auszeichnungen'] = wrap_db_fetch($sql, ['category', 'auszeichnung_id'], 'list category dauer_von');
	foreach ($org['auszeichnungen'] as $key => $auszeichnungen) {
		$auszeichnung = reset($auszeichnungen['dauer_von']);
		$org['auszeichnungen'][$key]['path'] = $auszeichnung['path'];
	}
	$org['auszeichnungen'] = array_values($org['auszeichnungen']);

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
		WHERE org_id = %d
		ORDER BY wochentag, uhrzeit_beginn';
	$sql = sprintf($sql, $org['org_id']);
	$wochentermine = wrap_db_fetch($sql, 'wochentermin_id');

	if ($org['edit']) {
		require_once $zz_conf['dir'].'/revisions.inc.php';
		$revisions = zz_revisions_read('organisationen', $org['org_id']);
		foreach ($revisions as $key => $value) {
			if (is_array($value)) {
				// ...
				echo wrap_print('not yet supported');
				exit;
			} else {
				if ($key === 'abteilung') {
					if ($value === 'Ja') $org['schachabteilung'] = 1;
					else $org['schachabteilung'] = NULL;
				} else {
					$org[$key] = $value;
				}
			}
		}
		$orte_sort = [];
		foreach ($org['orte'] as $contact_id => $ort) {
			$revisions = zz_revisions_read('organisationen_orte', $ort['cc_id']);
			if (is_null($revisions)) {
				unset($org['orte'][$contact_id]);
				continue;
			} elseif ($revisions) {
				// ...
				echo wrap_print('not yet supported');
				exit;
			}
			$revisions = zz_revisions_read('contacts', $ort['contact_id']);
			foreach ($revisions as $key => $value) {
				if (is_array($value)) {
					foreach ($value as $subtable) {
						foreach ($subtable as $subkey => $subvalue) {
							$org['orte'][$contact_id][$subkey] = $subvalue;
						}
					}
				} else {
					$org['orte'][$contact_id][$key] = $value;
				}
			}
			$ort = $org['orte'][$contact_id];
			$orte_sort[] = sprintf('%02d-%s-%s-%s-%s'
				, $ort['sequence'], $ort['contact'], $ort['postcode']
				, $ort['place'], $ort['address']
			);
		}
		$keys = array_keys($org['orte']);
		array_multisort($org['orte'], $orte_sort);
		array_multisort($keys, $orte_sort);
		$org['orte'] = array_combine($keys, $org['orte']);
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
	foreach ($org['orte'] as $contact_id => $ort) {
		if ($ort['published'] === 'no') unset($org['orte'][$contact_id]);
	}
	foreach ($wochentermine as $id => $wochentermin) {
		if ($wochentermin['oeffentlich'] === 'nein') unset($wochentermine[$id]);
	}

	if (!empty($org['orte'])) {
		foreach ($wochentermine as $wochentermin) {
			if (!$wochentermin['place_contact_id']) {
				$place = reset($org['orte']);
				$contact_id = $place['contact_id'];
			} else {
				foreach ($org['orte'] as $ort) {
					if ($ort['contact_id'] !== $wochentermin['place_contact_id']) continue;
					$contact_id = $ort['contact_id'];
				}
				if (!$contact_id) {
					// @todo read from database which place
					continue;
				}
			}
			$org['orte'][$contact_id]['wochentermine'][] = $wochentermin;
		}
	}

	// Karte mit Spielorten
	foreach ($org['orte'] as $id => $ort) {
		if (!$ort['longitude']) continue; // platforms
		if ($org['edit']) $org['orte'][$id]['edit'] = true;
		$longitude[] = $ort['longitude'];
		$latitude[] = $ort['latitude'];
		if (!isset($org['lon_min'])) {
			$org['lon_min'] = $ort['longitude'];
			$org['lon_max'] = $ort['longitude'];
			$org['lat_min'] = $ort['latitude'];
			$org['lat_max'] = $ort['latitude'];
		} else {
			if ($ort['longitude']) {
				if ($ort['longitude'] < $org['lon_min']) $org['lon_min'] = $ort['longitude'];
				if ($ort['longitude'] > $org['lon_max']) $org['lon_max'] = $ort['longitude'];
			}
			if ($ort['latitude']) {
				if ($ort['latitude'] < $org['lat_min']) $org['lat_min'] = $ort['latitude'];
				if ($ort['latitude'] > $org['lat_max']) $org['lat_max'] = $ort['latitude'];
			}
		}
	}
	if (!empty($longitude)) {
		$org['longitude'] = array_sum($longitude) / count($longitude);
		$org['latitude'] = array_sum($latitude) / count($latitude);
	}
	if (count($org['orte']) > 1) $org['orte_plural'] = true;

	$page['title'] = $org['organisation'];
	if ($org['schachabteilung']) {
		$page['title'] .= ' <br><em>Schachabteilung</em>'; 
	}
	if ($org['edit']) {
		$page['breadcrumbs'][] = sprintf('<a href="../">%s</a>', $org['organisation']);
		$page['breadcrumbs'][] = 'Bearbeiten';
	} else {
		$page['breadcrumbs'][] = $org['organisation'];
	}
	$page['head'] = wrap_template('vereine-map-head');
	if (!empty($org['lat_min'])) {
		$page['extra']['body_attributes'] = ' id="clubmap"';
	}
	$page['dont_show_h1'] = true;
	$page['text'] = wrap_template('verein', $org);
	return $page;
}
