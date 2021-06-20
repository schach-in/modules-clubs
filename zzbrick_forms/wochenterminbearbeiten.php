<?php

/**
 * clubs module
 * form script: edit weekly events
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017, 2019, 2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (empty($brick['vars'])) wrap_quit(404);
$verein = mf_clubs_club($brick['vars'][0]);
if (!$verein) wrap_quit(404);

$zz = zzform_include_table('wochentermine');

$mode = false;
switch ($brick['vars'][1]) {
	case 'add':
		$zz['access'] = 'add_then_edit';
		$zz_conf['referer'] = '../';
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
		$zz_conf['referer'] = '../../';
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

$zz['title'] = $verein['contact'];
$zz['where']['org_id'] = $verein['org_id'];
unset($zz['subtitle']);

// Vereinsname
$zz['fields'][2]['hide_in_form'] = true;

// Kategorien
if ($mode !== 'monat') {
	$zz['fields'][6]['sql'] = 'SELECT category_id, category, main_category_id
		FROM categories
		WHERE (ISNULL(parameters) OR !FIND_IN_SET("monat=1", parameters))
		ORDER BY sequence';
} else {
	$zz['fields'][6]['sql'] = 'SELECT category_id, category, main_category_id
		FROM categories
		WHERE FIND_IN_SET("monat=1", parameters)
		ORDER BY sequence';
}

// Wochen im Monat
if ($mode !== 'monat') {
	$zz['fields'][9]['hide_in_form'] = true;
}

$sql_ort = sprintf('SELECT contacts.contact_id
		, CONCAT(postcode, " ", place), contact AS veranstaltungsort
	FROM contacts
	LEFT JOIN addresses USING (contact_id)
	LEFT JOIN organisationen_orte
	    ON contacts.contact_id = organisationen_orte.contact_id
	WHERE org_id = %d
	ORDER BY postcode', $verein['org_id']);
$orte = wrap_db_fetch($sql_ort, 'contact_id');
if (count($orte) > 1) {
	// Spielorte nur vorgegebene
	$zz['fields'][8]['sql'] = $sql_ort;
	unset($zz['fields'][8]['explanation']);
} else {
	$zz['fields'][8]['hide_in_form'] = true;
	$zz['fields'][8]['value'] = '';
}

if (empty($_SESSION['login_id'])) {
	$zz['hooks']['after_insert'] = 'mf_clubs_add_revision_public';
	$zz['fields'][10]['if']['add']['value'] = 'nein';
}

$zz_conf['no_timeframe'] = true;
