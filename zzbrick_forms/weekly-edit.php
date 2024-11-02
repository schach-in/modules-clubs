<?php

/**
 * clubs module
 * form script: edit weekly events
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017, 2019, 2021-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


mf_clubs_editform($brick['data']);

$zz = zzform_include('wochentermine');

$mode = false;
switch ($brick['vars'][1]) {
	case 'add':
		$zz['access'] = 'add_then_edit';
		$zz['page']['referer'] = '../';
		switch ($brick['vars'][2]) {
			case 'month': $mode = 'month'; break;
			case 'week': $mode = 'week'; break;
		}
		break;
	case 'edit':
		if (empty($brick['vars'][2])) wrap_quit(404);
		$zz['access'] = 'edit_only';
		if (empty($_SESSION['login_id'])) {
			$zz['revisions_only'] = true;
		}
		$zz['where']['wochentermin_id'] = $brick['vars'][2];
		$zz['page']['referer'] = '../../';
		$sql = 'SELECT wochentermin_id FROM categories
			LEFT JOIN wochentermine
				ON wochentermine.wochentermin_category_id = categories.category_id
			WHERE wochentermin_id = %d
			AND parameters LIKE "%%&monat=1%%"';
		$sql = sprintf($sql, $brick['vars'][2]);
		$parameter = wrap_db_fetch($sql, '', 'single value');
		$mode = $parameter ? 'month' : 'week';
		break;
	default:
		wrap_quit(404);
}

global $zz_page;
$zz['title'] = sprintf('%s<br>%s', $zz_page['db']['title'], $brick['data']['contact']);
$zz['where']['contact_id'] = $brick['data']['contact_id'];
unset($zz['subtitle']);

// Vereinsname
$zz['fields'][2]['hide_in_form'] = true;

// Kategorien
if ($mode !== 'month') {
	$zz['fields'][6]['sql'] = 'SELECT category_id, category, main_category_id
		FROM categories
		WHERE (ISNULL(parameters) OR parameters NOT LIKE "%&monat=1%")
		ORDER BY sequence';
} else {
	$zz['fields'][6]['sql'] = 'SELECT category_id, category, main_category_id
		FROM categories
		WHERE parameters LIKE "%&monat=1%"
		ORDER BY sequence';
}

// Wochen im Monat
if ($mode !== 'month') {
	$zz['fields'][9]['hide_in_form'] = true;
}

$sql = 'SELECT contacts.contact_id
		, CONCAT(postcode, " ", place), contact AS place_contact
	FROM contacts
	LEFT JOIN addresses USING (contact_id)
	LEFT JOIN contacts_contacts USING (contact_id)
	WHERE main_contact_id = %d
	AND relation_category_id = /*_ID categories relation/venue _*/
	ORDER BY postcode';
$sql = sprintf($sql, $brick['data']['contact_id']);
$places = wrap_db_fetch($sql, 'contact_id');
if (count($places) > 1) {
	// Spielorte nur vorgegebene
	$zz['fields'][8]['sql'] = $sql;
	unset($zz['fields'][8]['explanation']);
} else {
	$zz['fields'][8]['hide_in_form'] = true;
	$zz['fields'][8]['value'] = '';
}

if (empty($_SESSION['login_id'])) {
	$zz['hooks']['after_insert'] = 'mf_clubs_add_revision_public';
	$zz['fields'][10]['if']['insert']['value'] = 'nein';
}

$zz['record']['no_timeframe'] = true;
$zz['page']['dont_show_title_as_breadcrumb'] = true;
$zz['page']['meta'][] = ['name' => 'robots', 'content' => 'noindex, follow, noarchive'];
