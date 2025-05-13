<?php

/**
 * clubs module
 * get data for clubs depending on request
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2015-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_clubs_get_clubs($params, $settings = []) {
	if (!empty($settings['category']))
		array_unshift($params, $settings['category']);
	if (empty($params)) $params[0] = false;

	if ($params[0] === 'q') {
		// shortcut for search, which is different than direct access
		$data['q'] = urldecode($params[1]);
		$params = [0 => false];
	} else
		$data['q'] = mod_clubs_get_clubs_q();

	$having = '';
	$extra_field = '';
	$condition = '';
	$condition_cc = '';
	$data['geojson'] = 'deutschland';
	if ($params[0] === 'deutschland' OR ((!$params[0] AND $params[0] !== '0') AND $data['q'] === NULL AND empty($_GET['lat']) AND empty($_GET['lon']))) {
		// show all clubs

	} elseif ($params[0] === 'twitter') {
		$extra_field = ', (SELECT COUNT(*) FROM contactdetails
			WHERE contactdetails.contact_id = organisationen.contact_id
			AND provider_category_id = /*_ID categories provider/twitter _*/) AS website_username';
		$condition = 'HAVING website_username > 0';
		$data['title'] = 'Twitter';
		$data['geojson'] = $params[0];

	} elseif ($federation = mod_clubs_get_clubs_federation($params[0])) {
		// member organisations?
		$sql = 'SELECT contacts.contact_id
			FROM contacts
			LEFT JOIN contacts_contacts
				ON contacts.contact_id = contacts_contacts.contact_id
				AND contacts_contacts.relation_category_id = /*_ID categories relation/member _*/
			WHERE main_contact_id IN (%s)
			AND contact_category_id = /*_ID categories contact/federation _*/
			AND ISNULL(end_date)';
		$contact_ids = wrap_db_children($federation['contact_id'], $sql);
		$condition = sprintf('AND federations.main_contact_id IN (%s)', implode(',', $contact_ids));
		$data['title'] = $federation['contact'];
		$data['zoomtofit'] = true;
		$data['geojson'] = $params[0];
		if (count($contact_ids) > 1) $data['federation_with_clubs'] = true;

	} elseif ($data['categories'] = mf_clubs_from_category($params[0])) {
		$sql = 'SELECT contact_id FROM contacts
			LEFT JOIN awards USING (contact_id)
			WHERE award_category_id IN (%s)';
		$sql = sprintf($sql, implode(',', array_keys($data['categories'])));
		$contact_ids = wrap_db_fetch($sql, 'contact_id', 'single value');
		if (!$contact_ids) return [];

		$condition_cc = 'AND contacts_contacts.sequence = 1';
		$condition = sprintf('AND organisationen.contact_id IN (%s)', implode(',', $contact_ids));
		$data['geojson'] = $params[0];

	} elseif ($data['categories'] = mod_clubs_get_clubs_from_contact_categories($params[0])) {
		$condition = sprintf('AND organisationen.contact_category_id IN (%s)', implode(',', array_keys($data['categories'])));
		$data['geojson'] = $params[0];
		
		if (!empty($params[1])) {
			$condition .= sprintf(' AND countries.identifier = "%s"', wrap_db_escape($params[1]));
			$data['geojson'] .= '/'.$params[1];
			$data['zoomtofit'] = true;
		}
		
	} elseif ($condition = mod_clubs_get_clubs_condition($data['q'] ?? urldecode($params[0]))) {
		if ($data['q'] === NULL) $data['q'] = urldecode($params[0]);
		if ($data['q'] === '0') $data['q'] = 0;
		if (!empty($condition[0]['boundingbox'])) {
			$data['boundingbox'] = sprintf(
				'[[%s, %s], [%s, %s]]'
				, $condition[0]['boundingbox'][0], $condition[0]['boundingbox'][2]
				, $condition[0]['boundingbox'][1], $condition[0]['boundingbox'][3]
			);
			$data['maxzoom'] = 12;
			$data['reselect'] = (count($condition) !== 1) ? $condition : [];
		} else {
			$data['zoomtofit'] = true;
			$data['geojson'] = mod_clubs_get_clubs_geojson($data['q']);
		}
		$data['noindex'] = true;
		$data['url_ending'] = 'none';

	} elseif (!empty($_GET['lat']) AND !empty($_GET['lon'])) {
		$check = mf_clubs_latlon_check();
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
		$data['maxzoom'] = 13;

	} else {
		return ['coordinates' => []];
	}

	if (is_array($condition)) {
		$result = reset($condition);
		$condition = 'HAVING distance <= %d ORDER BY distance';
		$orte_umkreissuche_km = 5;
		$condition = sprintf($condition, $orte_umkreissuche_km); 
		$having = ', 6371 * (ACOS(SIN(%s*Pi()/180)*SIN(latitude*Pi()/180)+COS(%s*Pi()/180)*COS(latitude*Pi()/180)*COS((%s-longitude)*Pi()/180))) AS distance';
		$having = sprintf($having, $result['lat'], $result['lat'], $result['lon']);
	}

	$sql = 'SELECT organisationen.contact AS title, places.contact AS place_contact
			, latitude AS x_latitude, longitude AS y_longitude
			, SUBSTRING_INDEX(categories.path, "/", -1) AS category
			, members, members_female AS female, members_u25 AS u25, (YEAR(CURDATE()) - avg_byear) AS avg_age, avg_rating
			, organisationen.identifier
			, (SELECT IFNULL(COUNT(award_id), NULL) FROM awards
				WHERE awards.contact_id = organisationen.contact_id) AS awards
			, organisationen.contact_id
			%s %s
		FROM contacts organisationen
		LEFT JOIN clubstats USING (contact_id)
		LEFT JOIN contacts_contacts
			ON contacts_contacts.main_contact_id = organisationen.contact_id
			AND contacts_contacts.published = "yes"
			AND contacts_contacts.relation_category_id = /*_ID categories relation/venue _*/
			%s
		LEFT JOIN contacts places
			ON contacts_contacts.contact_id = places.contact_id
		JOIN addresses
			ON IFNULL(places.contact_id, organisationen.contact_id) = addresses.contact_id
		JOIN categories
			ON organisationen.contact_category_id = categories.category_id
		LEFT JOIN countries
			ON organisationen.country_id = countries.country_id
		LEFT JOIN contacts_contacts federations
			ON federations.contact_id = organisationen.contact_id
			AND federations.relation_category_id = /*_ID categories relation/member _*/
		WHERE ISNULL(organisationen.end_date)
		AND NOT ISNULL(latitude) AND NOT ISNULL(longitude)
		AND categories.parameters LIKE "%%&organisation=1%%"
		%s
	';
	$csql = sprintf($sql
		, $extra_field
		, $having
		, $condition_cc
		, $condition
	);
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
			$csql = sprintf($sql
				, $extra_field
				, $having
				, $condition_cc
				, $condition
			);
			$data['coordinates'] = wrap_db_fetch($csql, '_dummy_', 'numeric');
			if ($data['coordinates']) break;
		}
	}

	return $data;
}

/**
 * check query string
 *
 * @return mixed
 */
function mod_clubs_get_clubs_q() {
	$q = $_GET['q'] ?? NULL;
	if ($q === '') return NULL;
	if ($q === '0') return 0;
	if (!$q) return $q;
	$q = trim($q);
	if (strlen($q) > 64)
		wrap_quit(414, 'Die maximale Länge der Suchbegriffe beträgt 64 Zeichen.');
	if (substr($q, -1) === '*') $q = substr($q, 0, -1);
	return $q;
};

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
function mod_clubs_get_clubs_condition($q) {
	// string 0 from params
	if (!$q AND $q !== 0 AND $q !== '0') return '';
	if ($q === 'deutschland') return '';
	$condition = '';
	if (strstr($q, '%')) return "AND 1=2"; // no % allowed, most of the time hackers

	// replace trailing asterisks
	while (substr($q, -1) === '*') $q = substr($q, 0, -1);

	if (strstr($q, '/')) $q = str_replace('/', ' ', $q);
	// some people look for postcode_place e. g. 91781Weissenburg without spaces
	if (!strstr(' ', $q) AND !preg_match('/^(\d+)$/', $q)) {
		if (preg_match('/^(\d+)(\w+)$/', $q, $matches)) {
			unset($matches[0]);
			$q = implode(' ', $matches);
		} elseif (preg_match('/^(\D+)(\d+)$/', $q, $matches)) {
			unset($matches[0]);
			$q = implode(' ', $matches);
		}
	}
	if (strstr($q, ' ')) {
		$q = mod_clubs_get_clubs_condition_parts($q);
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
				$condition = mod_clubs_get_clubs_geocode($url, $postcode);
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
	} elseif ($redirect = mf_clubs_redirect_check($q)) {
		return wrap_redirect($redirect['new_url']);
	} else {
		// city= is experimental and does not work with Bremen, München
		// $url = 'http://nominatim.openstreetmap.org/search.php?city=%s&country=de&format=jsonv2';
		$url = 'q=%s&countrycodes=de&format=jsonv2&accept-language=de&limit=50';
		$wanted = [
			'administrative', 'city', 'suburb', 'village', 'hamlet', 'town',
			'neighbourhood', 'county'
		];
		$condition = mod_clubs_get_clubs_geocode($url, $q, $wanted);
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
					AND provider_category_id = /*_ID categories provider/website _*/ LIKE "%%%s%%")', wrap_db_escape($q));
			}
			$condition .= ') OR (';
			foreach ($qs as $index => $q) {
				if ($index) $condition .= ' AND ';
				$condition .= sprintf('place LIKE "%%%s%%"', wrap_db_escape($q));
				$condition .= sprintf('OR (SELECT identification FROM contactdetails
					WHERE contactdetails.contact_id = organisationen.contact_id
					AND provider_category_id = /*_ID categories provider/website _*/ LIKE "%%%s%%")', wrap_db_escape($q));
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
function mod_clubs_get_clubs_condition_parts($q) {
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
 * @param string $path
 * @param string $q
 * @param array $wanted (optional)
 * @return array
 * @see http://wiki.openstreetmap.org/wiki/Nominatim_usage_policy
 */
function mod_clubs_get_clubs_geocode($path, $q, $wanted = []) {
	wrap_include('syndication', 'zzwrap');

	$url = 'https://nominatim.openstreetmap.org/search.php?'.$path;
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
	// district of a city, e. g. `Dortmund-Eving`, rewrite as `Eving, Dortmund`
	if (!$results AND strstr($q, '-')) {
		$qnew = explode('-', $q);
		$qnew = array_reverse($qnew);
		$qnew = implode(', ', $qnew);
		return mod_clubs_get_clubs_geocode($path, $qnew, $wanted);
	}
	return $results;
}

/**
 * get clubs from categories
 *
 * @param string $identifier
 * @return array
 */
function mod_clubs_get_clubs_from_contact_categories($identifier) {
	$category_id = wrap_category_id('contact/'.$identifier, 'check');
	if (!$category_id) return false;

	$sql = 'SELECT category_id, category, description
			, SUBSTRING_INDEX(path, "/", -1) AS path
		FROM categories
		WHERE category_id = %d';
	$sql = sprintf($sql, $category_id);
	$categories = wrap_db_fetch($sql, 'category_id');
	return $categories;
}

/**
 * prepare query string for GeoJSON URL
 *
 * @param string $string
 * @return string
 */
function mod_clubs_get_clubs_geojson($string) {
	$remove_strings = ['/', ',', '='];
	foreach ($remove_strings as $remove_string)
		$string = str_replace($remove_string, ' ', $string);
	while (strstr($string, '  '))
		$string = str_replace('  ', ' ', $string);
	$string = trim($string);
	$string = urlencode($string);
	$string = 'q/'.$string;
	return $string;
}
