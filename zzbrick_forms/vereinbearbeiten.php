<?php

/**
 * clubs module
 * edit an organisation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016, 2019, 2021-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (empty($brick['vars'])) wrap_quit(404);
$verein = mf_clubs_club($brick['vars'][0]);
if (!$verein) wrap_quit(404);

$values['relations'] = []; // no relations
$values['addresses'] = []; // no addresses
$values['contactdetails_restrict_to'] = 'organisations';
$zz = zzform_include('contacts', $values, 'forms');

unset($zz['filter']);

$zz['title'] = $verein['contact'];
$zz['where']['contact_id'] = $verein['contact_id'];
$zz['access'] = 'edit_only';
if (empty($_SESSION['login_id']))
	$zz['revisions_only'] = true;

$zz['fields'][2]['type'] = 'hidden'; 		// contact
$zz['fields'][2]['title'] = 'Name';
$zz['fields'][3]['hide_in_form'] = true;	// identifier
$zz['fields'][10]['hide_in_form'] = true;	// contact_short

// contact_category_id
$zz['fields'][4]['title'] = 'Typ';
$zz['fields'][4]['sql'] = wrap_edit_sql($zz['fields'][4]['sql'], 'WHERE',
	sprintf('category_id IN (%d, %d)', wrap_category_id('contact/club'), wrap_category_id('contact/chess-department'))
);
$zz['fields'][4]['show_values_as_list'] = true;

if (!empty($zz['fields'][11]))
	$zz['fields'][11]['hide_in_form'] = true;	// contact_abbr

$zz['fields'][12]['explanation'] = 'Etwas über Ihren Verein (optional)'; // description

// start_date
$zz['fields'][16]['title'] = 'Gründung o. ä.';
$zz['fields'][16]['explanation'] = 'Falls bekannt: Datum oder Jahr der Gründung';
if (empty($verein['parameters']['foundation_date'])) // @todo why is this here?
	$zz['fields'][16]['hide_in_form'] = true;

$zz['fields'][17]['hide_in_form'] = true;	// end_date
$zz['fields'][13]['hide_in_form'] = true;	// remarks

// country_id
$zz['fields'][18]['title'] = 'Bundesland';
$zz['fields'][18]['sql'] = 'SELECT country_id, country, main_country_id
	FROM countries
	ORDER BY country_code3';
$zz['fields'][18]['show_hierarchy'] = 'main_country_id';
$zz['fields'][18]['show_hierarchy_subtree'] = wrap_id('countries', 'DE');

// published
$zz['fields'][14]['hide_in_form'] = true;

// parameters
$zz['fields'][15]['hide_in_form'] = true;

$zz['fields'][97]['hide_in_form'] = true;	// created
$zz['fields'][99]['hide_in_form'] = true;	// last_update

// change sequence of fields
$i = 1;
foreach (array_keys($zz['fields']) as $no) {
	if (empty($zz['fields'][$no])) continue;
	$zz['fields'][$no]['field_sequence'] = $i;
	$i++;
	if ($i === 8) $i = $i + 2;
}
$zz['fields'][12]['field_sequence'] = 8;
$zz['fields'][18]['field_sequence'] = 9;

$zz_conf['referer'] = '../';
$zz_conf['no_timeframe'] = true;
