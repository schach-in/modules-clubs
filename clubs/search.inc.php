<?php

/**
 * clubs module
 * search form for clubs
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mf_clubs_search($q) {
	$data = [];
	return $data;
}

/**
 * no coordinates found, search
 *
 * @param array $page
 * @param array $data
 * @param array $params
 * @return array (or redirect)
 */
function mod_clubs_clubs_search($page, $data, $params) {
	if (!empty($data['q'])) {
		$club = mf_clubs_search_club($data['q']);
		if ($club)
			return wrap_redirect(sprintf('/%s/', $club['identifier']));

		$data['similar_places'] = mf_clubs_search_similar_places($_GET['q']);
	}
	
	if (!empty($data['federation_with_clubs']))
		return wrap_redirect(sprintf('/%s/liste/', $params[0]), 307);

	wrap_setting('cache', false);
	$page['status'] = !empty($data['similar_places']) ? 200 : 404;
	$data['not_found'] = true;
	$page['title'] = wrap_text('Search');
	$page['breadcrumbs'][]['title'] = wrap_text('Search');
	$page['extra']['not_home'] = true;
	$page['text'] = wrap_template('search-clubs', $data);
	return $page;
}

/**
 * search a single club
 *
 * @param string $search
 * @return array
 */
function mf_clubs_search_club($search) {
	// get search string, remove some characters
	$search = str_replace('"', '', $search);
	$search = trim($search, '+'); // + at the beginning or end has no meaning
	$qs = explode(' ', wrap_db_escape($search));

	// name of a club?
	$sql = 'SELECT contact_id, identifier
		FROM contacts
		LEFT JOIN categories
			ON contacts.contact_category_id = categories.category_id
		WHERE contact LIKE "%%%s%%"
		AND categories.parameters LIKE "%%&organisation=1%%"
		AND ISNULL(end_date)';
	$sql = sprintf($sql, implode('%', $qs));
	$club = wrap_db_fetch($sql);
	if ($club) return $club;

	$q = wrap_filename($search, '', ['-' => '']);
	$sql = 'SELECT contact_id, identifier
	FROM contacts
	LEFT JOIN categories
		ON contacts.contact_category_id = categories.category_id
	WHERE REPLACE(identifier, "-", "") LIKE "%%%s%%"
	AND categories.parameters LIKE "%%&organisation=1%%"
	AND ISNULL(end_date)';
	$sql = sprintf($sql, wrap_db_escape($q));
	$club = wrap_db_fetch($sql);
	if ($club) return $club;

	$change = false;
	foreach ($qs as $index => $qstring) {
		if (strlen($qstring) > 3) continue;
		unset ($qs[$index]);
		$change = true;
	}
	if (!$change) return [];

	$sql = 'SELECT contact_id, identifier
		FROM contacts
		LEFT JOIN categories
			ON contacts.contact_category_id = categories.category_id
		WHERE contact LIKE "%%%s%%"
		AND categories.parameters LIKE "%%&organisation=1%%"
		AND ISNULL(end_date)';
	$sql = sprintf($sql, implode('%', $qs));
	$club = wrap_db_fetch($sql);
	if ($club) return $club;
	
	return [];
}

/**
 * try to find something similar to the search term
 *
 * @param string $q
 * @return array
 */
function mf_clubs_search_similar_places($q) {
	$likes = [];
	$splitstring = mb_str_split($q);
	for ($i = 0; $i < mb_strlen($q); $i++) {
		$this_splitstring = $splitstring;
		$this_splitstring[$i] = '%';
		$likes[] = wrap_db_escape(implode('', $this_splitstring).'%');
	}

	$sql = 'SELECT COUNT(*) AS count, place
		FROM addresses
		WHERE place LIKE "%s"
		AND country_id = /*_ID countries DE _*/
		GROUP BY place';
	$sql = sprintf($sql, implode('" OR place LIKE "', $likes));
	return wrap_db_fetch($sql, '_dummy_', 'numeric');
}
