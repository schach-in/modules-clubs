<?php

/**
 * clubs module
 * edit an organisation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016, 2019, 2021-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (empty($brick['vars'])) wrap_quit(404);
$contact = mf_clubs_club($brick['vars'][0]);
if (!$contact) wrap_quit(404);

$values['contact_category_id'] = $contact['contact_category_id'];
$zz = zzform_include('contacts', $values, 'forms');

unset($zz['filter']);

$zz['title'] = $contact['contact'];
$zz['where']['contact_id'] = $contact['contact_id'];
$zz['access'] = 'edit_only';
if (empty($_SESSION['login_id']))
	$zz['revisions_only'] = true;

$i = 1;
foreach ($zz['fields'] as $no => $field) {
	if (empty($zz['fields'][$no])) continue;

	// change sequence of fields
	$zz['fields'][$no]['field_sequence'] = $i;
	$i++;
	if ($i === 8) $i = $i + 2;

	$identifier = zzform_field_identifier($field);
	switch ($identifier) {
	case 'contact':
		$zz['fields'][$no]['type'] = 'hidden';
		$zz['fields'][$no]['title'] = 'Name';
		break;

	case 'contact_category_id':
		$zz['fields'][$no]['title'] = 'Typ';
		$zz['fields'][$no]['sql'] = wrap_edit_sql($zz['fields'][4]['sql'], 'WHERE',
			sprintf('category_id IN (%d, %d)', wrap_category_id('contact/club'), wrap_category_id('contact/chess-department'))
		);
		$zz['fields'][$no]['show_values_as_list'] = true;
		break;

	case 'description':
		$zz['fields'][$no]['explanation'] = 'Etwas über Ihren Verein (optional)'; // 
		$zz['fields'][$no]['field_sequence'] = 8;
		break;

	case 'start_date':
		$zz['fields'][$no]['title'] = 'Gründung o. ä.';
		$zz['fields'][$no]['append_next'] = false;
		$zz['fields'][$no]['title_append'] = false;
		$zz['fields'][$no]['explanation'] = 'Falls bekannt: Datum oder Jahr der Gründung';
		if (empty($contact['parameters']['foundation_date'])) // @todo why is this here?
			$zz['fields'][$no]['hide_in_form'] = true;
		break;

	case 'country_id':
		$zz['fields'][$no]['title'] = 'Bundesland';
		$zz['fields'][$no]['sql'] = 'SELECT country_id, country, main_country_id
			FROM countries
			ORDER BY country_code3';
		$zz['fields'][$no]['show_hierarchy'] = 'main_country_id';
		$zz['fields'][$no]['show_hierarchy_subtree'] = wrap_id('countries', 'DE');
		$zz['fields'][$no]['field_sequence'] = 9;
		break;

	case 'contact_abbr':
	case 'contact_short':
	case 'identifier':
	case 'end_date':
	case 'remarks':
	case 'published':
	case 'parameters':
	case 'created':
	case 'last_update':
	case 'contacts_identifiers':
		$zz['fields'][$no]['hide_in_form'] = true;
		break;
		
	}
}

$zz['page']['referer'] = '../';
$zz['record']['no_timeframe'] = true;
