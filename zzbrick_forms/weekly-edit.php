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


mf_clubs_deny_bots();
if (empty($brick['vars'])) wrap_quit(404);
$contact = mf_clubs_club($brick['vars'][0]);
if (!$contact) wrap_quit(404);

$zz = zzform_include('wochentermine');

$mode = false;
switch ($brick['vars'][1]) {
	case 'add':
		$zz['access'] = 'add_then_edit';
		$zz['page']['referer'] = '../';
		switch ($brick['vars'][2]) {
			case 'monat': $mode = 'monat'; break;
			case 'woche': $mode = 'woche'; break;
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
		$sql = 'SELECT FIND_IN_SET("monat=1", parameters) FROM categories
			LEFT JOIN wochentermine
				ON wochentermine.wochentermin_category_id = categories.category_id
			WHERE wochentermin_id = %d';
		$sql = sprintf($sql, $brick['vars'][2]);
		$parameter = wrap_db_fetch($sql, '', 'single value');
		$mode = $parameter ? 'monat' : 'woche';
		break;
	default:
		wrap_quit(404);
}

$zz['title'] = $contact['contact'];
$zz['where']['contact_id'] = $contact['contact_id'];
unset($zz['subtitle']);

// Vereinsname
$zz['fields'][2]['hide_in_form'] = true;

// Kategorien
if ($mode !== 'monat') {
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
if ($mode !== 'monat') {
	$zz['fields'][9]['hide_in_form'] = true;
}

$sql = 'SELECT contacts.contact_id
		, CONCAT(postcode, " ", place), contact AS veranstaltungsort
	FROM contacts
	LEFT JOIN addresses USING (contact_id)
	LEFT JOIN contacts_contacts USING (contact_id)
	WHERE main_contact_id = %d
	AND relation_category_id = %d
	ORDER BY postcode';
$sql = sprintf($sql
	, $contact['contact_id']
	, wrap_category_id('relation/venue')
);
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
