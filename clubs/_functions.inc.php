<?php

// Zugzwang Project
// deutsche-schachjugend.de
// clubs module
// Copyright (c) 2016-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Common functions


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

/**
 * Hinzufügen einer Update-Revision für einen Ort zu einer Organisation,
 * oder einen Wochentermin, so dass oeffentlich = "ja" wird
 *
 * @param array $ops
 * @return array
 */
function my_add_revision_oeffentlich($ops) {
	global $zz_conf;

	$my_ops = [];
	foreach ($ops['return'] as $index => $table) {
		if (!in_array($table['table'], ['organisationen_orte', 'wochentermine'])) continue;
		if ($table['action'] !== 'insert') continue;
		if (!empty($ops['record_new'][$index]['oeffentlich']) AND $ops['record_new'][$index]['oeffentlich'] === 'ja') continue;
		if (!empty($ops['record_new'][$index]['published']) AND $ops['record_new'][$index]['published'] === 'yes') continue;
		
		$my_ops['return'][$index] = $ops['return'][$index];
		$my_ops['return'][$index]['action'] = 'update';
		$my_ops['record_diff'][$index] = $ops['record_diff'][$index];
		$my_ops['record_new'][$index] = $ops['record_new'][$index];
		foreach (array_keys($my_ops['record_diff'][$index]) as $field_name) {
			if ($field_name === 'published') {
				$my_ops['record_new'][$index][$field_name] = 'yes';
				continue;
			}
			if ($field_name === 'oeffentlich') {
				$my_ops['record_new'][$index][$field_name] = 'ja';
				continue;
			}
			$my_ops['record_diff'][$index][$field_name] = 'same';
		}
	}
	if (!array_key_exists(0, $my_ops['return'])) {
		$my_ops['return'][0] = $ops['return'][0];
		$my_ops['record_diff'][0] = $ops['record_diff'][0];
		$my_ops['record_new'][0] = $ops['record_new'][0];
		foreach (array_keys($my_ops['record_diff'][0]) as $field_name) {
			$my_ops['record_diff'][0][$field_name] = 'same';
		}
	}
	if ($my_ops) {
		require_once $zz_conf['dir'].'/revisions.inc.php';
		return zz_revisions($my_ops, [], true);
	}
}
