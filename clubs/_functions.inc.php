<?php

/**
 * clubs module
 * common functions for use with all scripts
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * get a club by its ID
 *
 * @param int $id
 * @return array
 */
function mf_clubs_club($id) {
	$sql = 'SELECT contact_id, contact, categories.path, categories.parameters, contact_category_id
		FROM contacts
		LEFT JOIN categories
			ON contacts.contact_category_id = categories.category_id
		WHERE contact_id = %d';
	$sql = sprintf($sql, $id);
	$club = wrap_db_fetch($sql);
	if (!$club) return false;
	parse_str($club['parameters'], $club['parameters']);
	return $club;
}

/**
 * print out all parent organisations of an organisation in hierarchical list
 *
 * @param int $contact_id
 * @return string
 */
function mf_clubs_parent_orgs($contact_id) {
	$contact_ids = wrap_db_parents($contact_id, 'SELECT mother_contact_id FROM contacts WHERE contact_id IN (%s)');
	if (!$contact_ids) return '';	

	$org = [];
	$sql = 'SELECT contact_id, contact, identifier
		FROM contacts
		WHERE contact_id IN (%s)';
	$sql = sprintf($sql, implode(',', $contact_ids));
	$parent_orgs = wrap_db_fetch($sql, 'contact_id');
	foreach ($contact_ids as $id) {
		$org['parent_orgs'][$id] = $parent_orgs[$id];
		$org['parent_orgs_count'][] = [];
	}
	$text = wrap_template('parent-organisations', $org);
	return $text;
}

/**
 * add a user to persons table using IP address
 *
 * @return bool
 */
function mf_clubs_add_user_from_ip() {
	$values = [];
	$values['action'] = 'insert';
	$values['POST']['contact_category_id'] = wrap_category_id('kontakte/rechner');
	$values['POST']['contact'] = 'IP '.$_SERVER['REMOTE_ADDR'];
	$ops = zzform_multi('contacts', $values);
	if (!$ops['id']) wrap_quit(403, 'Zur Zeit sind keine Änderungen möglich');
	wrap_session_start();
	$_SESSION['user_id'] = $ops['id'];
	$_SESSION['username'] = 'IP '.$_SERVER['REMOTE_ADDR'];
	session_write_close();
	return true;
}

/**
 * test if parameter is a category and get subcategories
 *
 * @param string $category
 * @return array
 */
function mf_clubs_from_category($category) {
	// categories must be lowercase, exclude some abbreviations here
	if (strtolower($category) !== $category) return false;
	$sql = 'SELECT category_id, category, description
			, SUBSTRING_INDEX(path, "/", -1) AS path
			, parameters
		FROM categories
		WHERE SUBSTRING_INDEX(path, "/", -1) = "%s"';
	$sql = sprintf($sql, wrap_db_escape($category));
	$categories = wrap_db_fetch($sql, 'category_id');
	if (!$categories) return false;
	$categories += wrap_db_children($categories
		, 'SELECT category_id, category, SUBSTRING_INDEX(path, "/", -1) AS path
			, (SELECT IFNULL(COUNT(DISTINCT contact_id), NULL) FROM awards
				WHERE awards.award_category_id = categories.category_id) AS awards
			, parameters
			FROM categories
			WHERE main_category_id IN (%s)'
		, 'category_id'
	);
	foreach ($categories as $category_id => $category) {
		if ($category['parameters']) parse_str($category['parameters'], $categories[$category_id]['parameters']);
	}
	return $categories;
}

/**
 * check lat and lon parameters
 *
 * @return mixed (bool true/false or HTTP status code)
 */
function mf_clubs_latlon_check() {
	$status = true;
	if (!empty($_GET['lat']) AND empty($_GET['lon'])) return false;
	if (!empty($_GET['lon']) AND empty($_GET['lat'])) return false;
	if (isset($_GET['lat']) AND !is_numeric($_GET['lat'])) {
		$_GET['lat'] = filter_var($_GET['lat'], FILTER_SANITIZE_NUMBER_FLOAT);
		$status = 404;
	}
	if (isset($_GET['lat']) AND $_GET['lat'] > 90) $_GET['lat'] = 90;
	if (isset($_GET['lat']) AND $_GET['lat'] < -90) $_GET['lat'] = -90;
	if (isset($_GET['lon']) AND !is_numeric($_GET['lon'])) {
		$_GET['lon'] = filter_var($_GET['lon'], FILTER_SANITIZE_NUMBER_FLOAT);
		$status = 404;
	}
	if (isset($_GET['lon']) AND $_GET['lon'] > 180) $_GET['lon'] = 180;
	if (isset($_GET['lon']) AND $_GET['lon'] < -180) $_GET['lon'] = -180;
	return $status;
}

/**
 * deny access to edit pages for bots
 *
 * @return void
 */
function mf_clubs_deny_bots() {
	$is_bot = mf_clubs_deny_bots_check();
	if (!$is_bot) return;
	wrap_quit(403, wrap_text('Bots are not allowed to access this resource.'));
}

/**
 * check if it is a bot access
 *
 * @return void
 */
function mf_clubs_deny_bots_check() {
	if (empty($_SERVER['HTTP_USER_AGENT'])) return false;
	if (strstr($_SERVER['HTTP_USER_AGENT'], 'spider')) return true;
	if (strstr($_SERVER['HTTP_USER_AGENT'], 'bot')) return true;
	return false;
}
