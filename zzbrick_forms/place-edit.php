<?php

/**
 * clubs module
 * form script: edit places
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2017, 2019-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


mf_clubs_deny_bots();
if (empty($brick['vars'])) wrap_quit(404);
$contact = mf_clubs_club($brick['vars'][0]);
if (!$contact) wrap_quit(404);

$values['contactdetails_restrict_to'] = 'places';
$values['relations_restrict_to'] = 'places';
$zz = zzform_include('contacts/contacts', $values, 'forms');

$zz['title'] = $contact['contact'];

switch ($brick['vars'][1]) {
	case 'add':
		$zz['access'] = 'add_then_edit';
		$zz['page']['referer'] = '../';
		break;
	case 'edit':
		if (empty($brick['vars'][2])) wrap_quit(404);
		$zz['access'] = 'edit_only';
		if (empty($_SESSION['login_id'])) {
			$zz['revisions_only'] = true;
		}
		$zz['where']['contact_id'] = $brick['vars'][2];
		$zz['page']['referer'] = '../../';
		break;
	default:
		wrap_quit(404);
}

$zz['if']['insert']['explanation'] = '';
$zz['unless']['insert']['explanation'] = '<strong>Hinweis:</strong> Bitte korrigiere hier nur Angaben zu diesem Spielort. Bei <strong>Wechsel</strong> des Spielorts lösche bitte den alten und <a href="../../ort-neu/">ergänze einen neuen</a>!';

foreach ($zz['fields'] as $no => $field) {
	if (empty($zz['fields'][$no])) continue;

	$identifier = zzform_field_identifier($field);
	switch ($identifier) {
	case 'contact':
		$zz['fields'][$no]['title'] = 'Spielort';
		$zz['fields'][$no]['explanation'] = 'Name des Orts, ggf. Vereinsnamen verwenden';
		break;

	case 'description':
		$zz['fields'][$no]['title'] = 'Hinweis <br>Anfahrt';
		$zz['fields'][$no]['explanation'] = 'Anfahrt mit Bahn, Auto, Lage des Spielorts';
		$zz['fields'][$no]['rows'] = 4;
		$zz['fields'][$no]['field_sequence'] = 12;
		break;

	case 'identifier':
		$zz['fields'][$no]['hide_in_form'] = true;
		break;

	case 'contact_category_id':
		$zz['fields'][$no]['type'] = 'hidden';
		$zz['fields'][$no]['type_detail'] = 'select';
		$zz['fields'][$no]['hide_in_form'] = true;
		$zz['fields'][$no]['hide_in_list'] = true;
		$zz['fields'][$no]['value'] = wrap_category_id('contact/place');
		break;

	case 'addresses':
		$zz['fields'][$no]['min_records'] = 1;
		$zz['fields'][$no]['min_records_required'] = 1;
		$zz['fields'][$no]['max_records'] = 1;
		$zz['fields'][$no]['form_display'] = 'inline';
		$zz['fields'][$no]['separator_before'] = false;
		// @todo dont_show_missing?

		// addresses.address
		$zz['fields'][$no]['fields'][3]['field_sequence'] = 3;

		// addresses.postcode
		$zz['fields'][$no]['fields'][4]['field_sequence'] = 4;

		// addresses.place
		$zz['fields'][$no]['fields'][5]['field_sequence'] = 5;

		// addresses.country_id
		$zz['fields'][$no]['fields'][6]['sql'] = sprintf('SELECT country_id
				, country_code, country, main_country_id
			FROM countries
			WHERE country_category_id = %d
			ORDER BY country, country_code3', wrap_category_id('politische-einheiten/staat'));
		$zz['fields'][$no]['fields'][6]['show_hierarchy'] = 'main_country_id';
		$zz['fields'][$no]['fields'][6]['default'] = wrap_id('countries', 'DE');
		$zz['fields'][$no]['fields'][6]['field_sequence'] = 6;

		// addresses.latitude
		$zz['fields'][$no]['fields'][7]['hide_in_form'] = true;

		// addresses.longitude
		$zz['fields'][$no]['fields'][8]['hide_in_form'] = true;

		// addresses.address_category_id
		$zz['fields'][$no]['fields'][9]['type'] = 'hidden';
		$zz['fields'][$no]['fields'][9]['type_detail'] = 'select';
		$zz['fields'][$no]['fields'][9]['value'] = wrap_category_id('adressen/dienstlich');
		$zz['fields'][$no]['fields'][9]['hide_in_form'] = true;
		break;

	case 'published':
		$zz['fields'][$no]['hide_in_form'] = true;
		break;

	case 'created':
		$zz['fields'][$no]['field_sequence'] = 97;
		break;

	case 'last_update':
		$zz['fields'][$no]['hide_in_form'] = true;
		$zz['fields'][$no]['field_sequence'] = 99;
		break;

	case 'contactdetails':
		$zz['fields'][$no]['field_sequence'] = $no;
		break;
	
	case 'contacts_contacts':
		$zz['fields'][$no]['min_records_required'] = 1;
		$zz['fields'][$no]['max_records'] = 1;
		$zz['fields'][$no]['form_display'] = 'inline';

		// contacts_contacts.sequence
		$zz['fields'][$no]['fields'][6]['title'] = 'Reihenfolge';
		$zz['fields'][$no]['fields'][6]['field_sequence'] = 20;
		$zz['fields'][$no]['fields'][6]['explanation'] = '(Sortierung, falls es mehrere Spielorte gibt)';

		// contacts_contacts.main_contact_id
		$zz['fields'][$no]['fields'][3]['type'] = 'hidden';
		$zz['fields'][$no]['fields'][3]['type_detail'] = 'select';
		$zz['fields'][$no]['fields'][3]['value'] = $contact['contact_id'];
		$zz['fields'][$no]['fields'][3]['hide_in_form'] = true;

		// contacts_contacts.remarks
		$zz['fields'][$no]['fields'][9]['title'] = 'Hinweis <br>Verein';
		$zz['fields'][$no]['fields'][9]['hide_in_form'] = false;
		$zz['fields'][$no]['fields'][9]['rows'] = 4;
		$zz['fields'][$no]['fields'][9]['format'] = 'markdown';
		$zz['fields'][$no]['fields'][9]['field_sequence'] = 19;
		unset($zz['fields'][$no]['fields'][9]['explanation']);

		// contacts_contacts.published
		if (empty($_SESSION['login_id'])) {
			$zz['fields'][$no]['if']['insert']['fields'][10]['value'] = 'no';
		} else {
			$zz['fields'][$no]['if']['insert']['fields'][10]['value'] = 'yes';
		}
		break;

	case 'contact_abbr':
	case 'contact_short':
	case 'start_date':
	case 'end_date':
	case 'country_id':
	case 'remarks':
	case 'contacts_identifiers':
	case 'parameters':
		unset($zz['fields'][$no]);
		break;

	}
}

if (empty($_SESSION['login_id'])) {
	$zz['hooks']['after_insert'] = 'mf_clubs_add_revision_public';
}

$zz_conf['revisions_url'] = '/orte/'; // @todo solve differently
$zz['record']['no_timeframe'] = true;
