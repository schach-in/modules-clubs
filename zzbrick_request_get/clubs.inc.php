<?php

/**
 * clubs module
 * get data for clubs depending on request
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2015-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_clubs_get_clubs($params, $settings = []) {
	if (count($params) > 1) return [];
	if (empty($params)) $params[0] = false;

	if (isset($_GET['q']) AND $_GET['q'] !== '') {
		$_GET['q'] = trim($_GET['q']);
		if (strlen($_GET['q']) > 64) {
			// extra_ is not created since zzbrick() was not called
			wrap_quit(414, 'Die maximale Länge der Suchbegriffe beträgt 64 Zeichen.');
		}
		if (substr($_GET['q'], -1) === '*') $_GET['q'] = substr($_GET['q'], 0, -1);
	}

	$found = false;
	$having = '';
	$extra_field = '';
	$condition_cc = '';
	if (!$params) {
		$data['geojson'] = 'deutschland';
	} elseif ($params[0] === 'twitter') {
		$extra_field = sprintf(', (SELECT COUNT(*) FROM contactdetails
			WHERE contactdetails.contact_id = organisationen.contact_id
			AND provider_category_id = %d) AS website_username', wrap_category_id('provider/twitter'));
		$condition = 'HAVING website_username > 0';
		$found = true;
		$data['title'] = 'Twitter';
		$data['geojson'] = $params[0];
	} else {
		$data['geojson'] = $params[0];
		$haupt_org = mod_clubs_get_clubs_federation($params[0]);
		if ($haupt_org) {
			$found = true;
			// Unterorganisationen?
			$contact_ids = wrap_db_children(
				$haupt_org['contact_id'],
				sprintf('SELECT contact_id
				FROM contacts WHERE mother_contact_id IN (%%s)
				AND contact_category_id = %d
				AND ISNULL(end_date)', wrap_category_id('contact/federation'))
			);
			$condition = sprintf('AND organisationen.mother_contact_id IN (%s)', implode(',', $contact_ids));
			$data['title'] = $haupt_org['contact'];
			$data['zoomtofit'] = true;
			if ($contact_ids) $data['federation_with_clubs'] = true;
		} else {
			$data['categories'] = mf_clubs_from_category($params[0]);
			if ($data['categories']) {
				$found = true;
				$sql = 'SELECT contact_id FROM contacts
					LEFT JOIN auszeichnungen USING (contact_id)
					WHERE auszeichnung_category_id IN (%s)';
				$sql = sprintf($sql, implode(',', array_keys($data['categories'])));
				$contact_ids = wrap_db_fetch($sql, 'contact_id', 'single value');
				if (!$contact_ids) return false;

				$condition_cc = 'AND contacts_contacts.sequence = 1';
				$condition = sprintf('AND organisationen.contact_id IN (%s)', implode(',', $contact_ids));
				$category = reset($data['categories']);
				$data['title'] = $category['category'];
				$data['zoomtofit'] = false;
				$data['description'] = $category['description'];
			} else {
				if (empty($_GET['q'])) $_GET['q'] = urldecode($params[0]);
				$data['url_ending'] = 'none';
			}
		}
	}

	$data['noindex'] = false;
	if (!$found) {
		$condition = (isset($_GET['q']) AND $_GET['q'] !== '') ? mod_clubs_vereine_condition($_GET['q']) : '';
		$data['title'] = NULL;
		$page['query_strings'][] = 'q';
		if ($condition) {
			if (!empty($condition[0]['boundingbox'])) {
				$data['boundingbox'] = sprintf(
					'[[%s, %s], [%s, %s]]'
					, $condition[0]['boundingbox'][0], $condition[0]['boundingbox'][2]
					, $condition[0]['boundingbox'][1], $condition[0]['boundingbox'][3]
				);
				$data['maxzoom'] = 12;
			} else {
				$data['zoomtofit'] = true;
			}
			$data['noindex'] = true;
		}
	}
	
	if (!$condition AND !empty($_GET['lat']) AND !empty($_GET['lon'])) {
		$condition = [];
		$condition[] = [
			'lat' => $_GET['lat'], 'lon' => $_GET['lon']
		];
		$data['boundingbox'] = sprintf(
			'[[%s, %s], [%s, %s]]'
			, $_GET['lat'], $_GET['lon']
			, $_GET['lat'], $_GET['lon']
		);
		$data['noindex'] = true;
	}
	if (is_array($condition)) {
		$data['reselect'] = (count($condition) !== 1) ? $condition : [];
		$result = reset($condition);
		if (!empty($result['boundingbox'])) {
			$data['boundingbox'] = sprintf(
				'[[%s, %s], [%s, %s]]'
				, $result['boundingbox'][0], $result['boundingbox'][2]
				, $result['boundingbox'][1], $result['boundingbox'][3]
			);
		}
		$data['maxzoom'] = 13;
		$condition = 'HAVING distance <= %d ORDER BY distance';
		$orte_umkreissuche_km = 5;
		$condition = sprintf($condition, $orte_umkreissuche_km); 
		$having = ', 6371 * (ACOS(SIN(%s*Pi()/180)*SIN(latitude*Pi()/180)+COS(%s*Pi()/180)*COS(latitude*Pi()/180)*COS((%s-longitude)*Pi()/180))) AS distance';
		$having = sprintf($having, $result['lat'], $result['lat'], $result['lon']);
	}

	$sql = 'SELECT organisationen.contact AS title, places.contact AS veranstaltungsort
			, latitude AS x_latitude, longitude AS y_longitude
			, SUBSTRING_INDEX(categories.path, "/", -1) AS category
			, members, members_female AS female, members_u25 AS u25, (YEAR(CURDATE()) - avg_byear) AS avg_age, avg_rating
			, organisationen.identifier
			, (SELECT IFNULL(COUNT(auszeichnung_id), NULL) FROM auszeichnungen
				WHERE auszeichnungen.contact_id = organisationen.contact_id) AS auszeichnungen
			, organisationen.contact_id
			%s %s
		FROM contacts organisationen
		LEFT JOIN vereinsdb_stats USING (contact_id)
		LEFT JOIN contacts_contacts
			ON contacts_contacts.main_contact_id = organisationen.contact_id
			AND contacts_contacts.published = "yes"
			%s
		LEFT JOIN contacts places
			ON contacts_contacts.contact_id = places.contact_id
		JOIN addresses
			ON IFNULL(places.contact_id, organisationen.contact_id) = addresses.contact_id
		JOIN categories
			ON organisationen.contact_category_id = categories.category_id
		WHERE ISNULL(organisationen.end_date)
		AND NOT ISNULL(latitude) AND NOT ISNULL(longitude)
		AND categories.parameters LIKE "%%&organisation=1%%"
		%s
	';
	$csql = sprintf($sql, $extra_field, $having, $condition_cc, $condition);
	$data['coordinates'] = wrap_db_fetch($csql, '_dummy_', 'numeric');

	if (!$data['coordinates'] AND $having) {
		while ($orte_umkreissuche_km < 60) {
			$condition = 'HAVING distance <= %d ORDER BY distance';
			$orte_umkreissuche_km += 5;
			switch ($orte_umkreissuche_km) {
				case 10: $data['maxzoom'] = 12; break;
				case 15: $data['maxzoom'] = 11; break;
				case 30: $data['maxzoom'] = 10; break;
				case 40: $data['maxzoom'] = 9; break;
				case 50: $data['maxzoom'] = 8; break;
			}
			$condition = sprintf($condition, $orte_umkreissuche_km); 
			$csql = sprintf($sql, $extra_field, $having, $condition_cc, $condition);
			$data['coordinates'] = wrap_db_fetch($csql, '_dummy_', 'numeric');
			if ($data['coordinates']) break;
		}
	}

	return $data;
}

/**
 * check if identifier in URL is organisation
 *
 * @param string $identifier
 * @return array
 */
function mod_clubs_get_clubs_federation($identifier) {
	$sql = 'SELECT contact_id, contact
		FROM contacts
		LEFT JOIN categories
			ON contacts.contact_category_id = categories.category_id
		WHERE identifier = "%s"
		AND categories.parameters LIKE "%%&organisation=1%%"';
	$sql = sprintf($sql, wrap_db_escape($identifier));
	return wrap_db_fetch($sql);
}

/**
 * create condition for SQL query
 *
 * @param string $q
 * @return mixed string: SQL condition, array: list of results
 */
function mod_clubs_vereine_condition($q) {
	if ($q === 'deutschland') $q = '';
	$condition = '';
	if (strstr($q, '%')) return "AND 1=2"; // no % allowed, most of the time hackers

	// replace trailing asterisks
	while (substr($q, -1) === '*') $q = substr($q, 0, -1);

	if (strstr($q, '/')) $q = str_replace('/', ' ', $q);
	if (strstr($q, ' ')) {
		$q = mod_clubs_vereine_condition_parts($q);
	}

	// replace small 'o's which were 0s in the typewriter age
	if (is_string($q) AND strlen($q) < 6 AND preg_match('~^[0-9o]+$~', $q)) {
		$q = str_replace('o', '0', $q);
	} elseif (is_string($q) AND strlen($q) < 6 AND preg_match('~^[0-9O]+$~', $q)) {
		$q = str_replace('O', '0', $q);
	}
	if (is_numeric($q)) {
		if (strlen($q) === 4) {
			$q .= '0'; // just as a help for people who omit a last number
		}
		if (strlen($q) === 5 AND substr($q, -3) === '000') $q = substr($q, 0, 2);
		if (substr($q, 0, 2) === '11') $q = '10'; // 11 is government in Berlin
		if (strlen($q) === 5) {
			$counter = 0;
			$postcode = $q;
			$url = 'postalcode=%s&countrycodes=de&format=jsonv2&accept-language=de&limit=1';
			while (!$condition) {
				// try postcodes nearby, +1, -1 to +8 -8
				$condition = mod_clubs_vereine_geocode($url, $postcode);
				$counter++;
				$postcode = sprintf('%05d', $counter & 1 ? $q - ceil($counter/2) : $q + ceil($counter/2));
				if ($counter > 16) break;
			}
		}
		if (!$condition) {
			while (substr($q, -1) === '0' AND strlen($q) > 2) {
				$q = substr($q, 0, -1);
			}
			$condition = sprintf(' AND addresses.postcode LIKE "%s%%"', $q);
		}
	} elseif (is_array($q)) {
		$condition = [];
		foreach ($q as $postcode) {
			$condition[] .= sprintf('addresses.postcode LIKE "%s%%"', $postcode);
		}
		$condition = sprintf('AND (%s)', implode(' OR ', $condition));
	} else {
		// city= is experimental and does not work with Bremen, München
		// $url = 'http://nominatim.openstreetmap.org/search.php?city=%s&country=de&format=jsonv2';
		$url = 'q=%s&countrycodes=de&format=jsonv2&accept-language=de&limit=50';
		$wanted = [
			'administrative', 'city', 'suburb', 'village', 'hamlet', 'town',
			'neighbourhood', 'county'
		];
		$condition = mod_clubs_vereine_geocode($url, $q, $wanted);
		if (!$condition) {
			// if it has a space in the name, test all parts separately
			// to avoid cases like Bremen Nord != Bremen-Nord
			$condition = '';
			if (str_starts_with($q, '"') AND str_ends_with($q, '"')) {
				$qs[0] = substr($q, 1, -1);
			} else {
				$qs = explode(' ', $q);
			}
			$condition .= ' AND ((';
			foreach ($qs as $index => $q) {
				if ($index) $condition .= ' AND ';
				$condition .= sprintf('organisationen.contact LIKE "%%%s%%"', wrap_db_escape($q));
				// add support for ae = ä etc.
				$condition .= sprintf('OR organisationen.identifier LIKE LOWER(_latin1"%%%s%%")', wrap_db_escape($q));
				$condition .= sprintf('OR (SELECT identification FROM contactdetails
					WHERE contactdetails.contact_id = organisationen.contact_id
					AND provider_category_id = %d LIKE "%%%s%%")', wrap_category_id('provider/website'), wrap_db_escape($q));
			}
			$condition .= ') OR (';
			foreach ($qs as $index => $q) {
				if ($index) $condition .= ' AND ';
				$condition .= sprintf('place LIKE "%%%s%%"', wrap_db_escape($q));
				$condition .= sprintf('OR (SELECT identification FROM contactdetails
					WHERE contactdetails.contact_id = organisationen.contact_id
					AND provider_category_id = %d LIKE "%%%s%%")', wrap_category_id('provider/website'), wrap_db_escape($q));
			}
			$condition .= '))';
		}
	}
	return $condition;
}

/**
 * Auswertung einer Suchabfrage mit mehreren Worten
 * Falls fünfstellige Zahl dabei: PLZ, alles andere ignorieren
 * Wörter kürzer als drei Zeichen werden ignoriert
 *
 * @param string $q
 * @return mixed string $q or array with postcodes
 */
function mod_clubs_vereine_condition_parts($q) {
	$search = explode(' ', $q);
	foreach ($search as $value) {
		if (!is_numeric($value)) continue;
		if (strlen($value) !== 5) continue;
		// Postleitzahl, vergiss den Rest
		return $value;
	}
	foreach ($search as $index => $part) {
		if (mb_strlen($part) <= 2) unset($search[$index]);
	}
	if (!$search) {
		// oops, we need something
		$search = explode(' ', $q);
		$all_numeric = true;
		foreach ($search as $index => $part) {
			// postcode?
			if (!is_numeric($part)) $all_numeric = false;
		}
		if ($all_numeric) return $search;
		return $q; // unchanged
	}
	$q = implode(' ', $search);
	return $q;
}

/**
 * geocode search string
 * returns places for first result, rest of results will be shown as list
 *
 * @param string $url
 * @param string $q
 * @param array $wanted (optional)
 * @return array
 * @see http://wiki.openstreetmap.org/wiki/Nominatim_usage_policy
 */
function mod_clubs_vereine_geocode($url, $q, $wanted = []) {
	global $zz_setting;
	require_once $zz_setting['core'].'/syndication.inc.php';

	$url = 'https://nominatim.openstreetmap.org/search.php?'.$url;
	$url = sprintf($url, rawurlencode($q));
	wrap_lock_wait('nominatim', 1); // just 1 request per second
	$results = wrap_syndication_get($url);
	wrap_unlock('nominatim');
	unset($results['_']);
	if ($wanted) {
		foreach ($results as $index => $result) {
			if (!in_array($result['type'], $wanted)) {
				unset($results[$index]);
				continue;
			}
		}
	}
	return $results;
}
