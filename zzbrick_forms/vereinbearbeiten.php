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
$zz['where']['contact_id'] = $verein['contact_id'];
$zz['access'] = 'edit_only';
if (empty($_SESSION['login_id'])) {
	$zz['revisions_only'] = true;
}

// contact_category_id
$zz['fields'][4]['sql'] = wrap_edit_sql($zz['fields'][4]['sql'], 'WHERE',
	sprintf('category_id IN (%d, %d)', wrap_category_id('contact/club'), wrap_category_id('contact/chess-department'))
);
$zz['fields'][4]['show_values_as_list'] = true;

$zz['fields'][2]['type'] = 'hidden'; 		// contact
$zz['fields'][10]['hide_in_form'] = true;	// contact_short
$zz['fields'][11]['hide_in_form'] = true;	// contact_abbr
$zz['fields'][72]['hide_in_form'] = true;	// mother_contact_id
$zz['fields'][75]['hide_in_form'] = true;	// table contacts-identifiers
$zz['fields'][3]['hide_in_form'] = true;	// identifier
$zz['fields'][69]['append_next'] = false;	// gruendung
$zz['fields'][69]['title_append'] = false;	// gruendung
$zz['fields'][72]['hide_in_form'] = true;	// successor_contact_id
$zz['fields'][70]['hide_in_form'] = true;	// aufloesung
$zz['fields'][13]['hide_in_form'] = true;	// remarks
$zz['fields'][99]['hide_in_form'] = true;	// last_update
if (empty($verein['parameters']['foundation_date'])) {
	$zz['fields'][69]['hide_in_form'] = true; 	// gruendung
}
$zz['fields'][97]['hide_in_form'] = true;	// created

$zz['fields'][12]['explanation'] = 'Etwas über Ihren Verein (optional)'; // description
$zz['fields'][71]['title'] = 'Bundesland'; // country_id
$zz['fields'][69]['explanation'] = 'Falls bekannt: Datum oder Jahr der Gründung'; // gruendung

// Spielorte
$zz['fields'][76]['hide_in_form'] = true;	// table contacts-contacts

$zz_conf['referer'] = '../';
$zz_conf['no_timeframe'] = true;
