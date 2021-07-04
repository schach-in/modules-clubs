<?php

/**
 * clubs module
 * edit an organisation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016, 2019, 2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (empty($brick['vars'])) wrap_quit(404);
$verein = mf_clubs_club($brick['vars'][0]);
if (!$verein) wrap_quit(404);

$zz = zzform_include_table('organisationen');

unset($zz['filter']);
unset($zz['details']);

$zz['title'] = $verein['contact'];
$zz['where']['org_id'] = $verein['org_id'];
$zz['access'] = 'edit_only';
if (empty($_SESSION['login_id'])) {
	$zz['revisions_only'] = true;
}

// contact_category_id
$zz['fields'][6]['sql'] = wrap_edit_sql($zz['fields'][6]['sql'], 'WHERE',
	sprintf('category_id IN (%d, %d)', wrap_category_id('contact/club'), wrap_category_id('contact/chess-department'))
);
$zz['fields'][6]['show_values_as_list'] = true;

$zz['fields'][3]['type'] = 'hidden'; 
$zz['fields'][4]['hide_in_form'] = true;
$zz['fields'][34]['hide_in_form'] = true;
$zz['fields'][13]['hide_in_form'] = true;
$zz['fields'][2]['hide_in_form'] = true;
$zz['fields'][7]['hide_in_form'] = true;
$zz['fields'][40]['append_next'] = false;
$zz['fields'][40]['title_append'] = false;
$zz['fields'][12]['hide_in_form'] = true;
$zz['fields'][10]['hide_in_form'] = true;
$zz['fields'][15]['hide_in_form'] = true;
$zz['fields'][20]['hide_in_form'] = true;
if ($verein['path'] !== 'organisationen/verein') {
	// $zz['fields'][5]['hide_in_form'] = true; // Schachabteilung
}
if (empty($verein['parameters']['foundation_date'])) {
	$zz['fields'][40]['hide_in_form'] = true; // Gründungsdatum
}

$zz['fields'][11]['explanation'] = 'Etwas über Ihren Verein (optional)';
$zz['fields'][9]['title'] = 'Bundesland';
$zz['fields'][40]['explanation'] = 'Falls bekannt: Datum oder Jahr der Gründung';

// Spielorte
$zz['fields'][24]['hide_in_form'] = true;

$zz_conf['referer'] = '../';
$zz_conf['no_timeframe'] = true;
