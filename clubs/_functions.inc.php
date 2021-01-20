<?php

/**
 * Zugzwang Project
 * common functions for use with all scripts
 *
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * print out all parent organisations of an organisation in hierarchical list
 *
 * @param int $org_id
 * @return string
 */
function clubs_parent_orgs($org_id) {
	$org_ids = wrap_db_parents($org_id, 'SELECT mutter_org_id FROM organisationen WHERE org_id IN (%s)');
	if (!$org_ids) return '';	

	$org = [];
	$sql = 'SELECT org_id, organisation, kennung
		FROM organisationen
		WHERE org_id IN (%s)';
	$sql = sprintf($sql, implode(',', $org_ids));
	$parent_orgs = wrap_db_fetch($sql, 'org_id');
	foreach ($org_ids as $id) {
		$org['parent_orgs'][$id] = $parent_orgs[$id];
		$org['parent_orgs_count'][] = [];
	}
	$text = wrap_template('parent-organisations', $org);
	return $text;
}

/**
 * add a user to personen table using IP address
 *
 * @return bool
 */
function clubs_add_user_from_ip() {
	global $zz_conf;
	require_once $zz_conf['dir'].'/zzform.php';

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
function clubs_from_category($category) {
	$sql = 'SELECT category_id, category, description
			, SUBSTRING_INDEX(path, "/", -1) AS path
		FROM categories
		WHERE SUBSTRING_INDEX(path, "/", -1) = "%s"';
	$sql = sprintf($sql, wrap_db_escape($category));
	$categories = wrap_db_fetch($sql, 'category_id');
	if (!$categories) return false;
	$categories += wrap_db_children($categories
		, 'SELECT category_id, category, SUBSTRING_INDEX(path, "/", -1) AS path
			, (SELECT IFNULL(COUNT(DISTINCT org_id), NULL) FROM auszeichnungen
				WHERE auszeichnungen.auszeichnung_category_id = categories.category_id) AS auszeichnungen
			FROM categories
			WHERE main_category_id IN (%s)'
		, 'category_id'
	);
	return $categories;
}
