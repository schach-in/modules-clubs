<?php

/**
 * clubs module
 * form script: edit places
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2017, 2019-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (empty($brick['vars'])) wrap_quit(404);
$club = mf_clubs_club($brick['vars'][0]);
if (!$club) wrap_quit(404);

$values['contactdetails_restrict_to'] = 'places';
$values['relations_restrict_to'] = 'places';
$zz = zzform_include('contacts/contacts', $values);

$zz['title'] = $club['contact'];

switch ($brick['vars'][1]) {
	case 'add':
		$zz['access'] = 'add_then_edit';
		$zz_conf['referer'] = '../';
		break;
	case 'edit':
		if (empty($brick['vars'][2])) wrap_quit(404);
		$zz['access'] = 'edit_only';
		if (empty($_SESSION['login_id'])) {
			$zz['revisions_only'] = true;
		}
		$zz['where']['contact_id'] = $brick['vars'][2];
		$zz_conf['referer'] = '../../';
		break;
	default:
		wrap_quit(404);
}

$zz['if']['insert']['explanation'] = '';
$zz['unless']['insert']['explanation'] = '<strong>Hinweis:</strong> Bitte korrigiere hier nur Angaben zu diesem Spielort. Bei <strong>Wechsel</strong> des Spielorts lösche bitte den alten und <a href="../../ort-neu/">ergänze einen neuen</a>!';

// contact
$zz['fields'][2]['title'] = 'Spielort';
$zz['fields'][2]['explanation'] = 'Name des Orts, ggf. Vereinsnamen verwenden';
$zz['fields'][97]['field_sequence'] = 1;

// contact_short
unset($zz['fields'][10]);

// identifier
$zz['fields'][3]['fields'] = [
	'addresses.country_id[country_code]', 'addresses.place', 'contact'
];
$zz['fields'][3]['conf_identifier']['concat'] = '/';
$zz['fields'][3]['conf_identifier']['ignore_this_if_identical']['place'] = 'contact';
$zz['fields'][3]['hide_in_form'] = true;

// contact_category_id
$zz['fields'][4]['type'] = 'hidden';
$zz['fields'][4]['type_detail'] = 'select';
$zz['fields'][4]['hide_in_form'] = true;
$zz['fields'][4]['hide_in_list'] = true;
$zz['fields'][4]['value'] = wrap_category_id('contact/place');

// addresses
$zz['fields'][5]['min_records'] = 1;
$zz['fields'][5]['min_records_required'] = 1;
$zz['fields'][5]['max_records'] = 1;
$zz['fields'][5]['form_display'] = 'inline';
// @todo dont_show_missing?

// addresses.address
$zz['fields'][5]['fields'][3]['field_sequence'] = 3;

// addresses.postcode
$zz['fields'][5]['fields'][4]['field_sequence'] = 4;

// addresses.place
$zz['fields'][5]['fields'][5]['field_sequence'] = 5;

// addresses.country_id
$zz['fields'][5]['fields'][6]['sql'] = sprintf('SELECT country_id
		, country_code, country, main_country_id
	FROM countries
	WHERE country_category_id = %d
	ORDER BY country, country_code3', wrap_category_id('politische-einheiten/staat'));
$zz['fields'][5]['fields'][6]['show_hierarchy'] = 'main_country_id';
$zz['fields'][5]['fields'][6]['default'] = wrap_id('countries', 'DE');
$zz['fields'][5]['fields'][6]['field_sequence'] = 6;

// addresses.latitude
$zz['fields'][5]['fields'][7]['hide_in_form'] = true;

// addresses.longitude
$zz['fields'][5]['fields'][8]['hide_in_form'] = true;

// addresses.address_category_id
$zz['fields'][5]['fields'][9]['type'] = 'hidden';
$zz['fields'][5]['fields'][9]['type_detail'] = 'select';
$zz['fields'][5]['fields'][9]['value'] = wrap_category_id('adressen/dienstlich');
$zz['fields'][5]['fields'][9]['hide_in_form'] = true;

// contactdetails
// e-mail
$zz['fields'][30]['hide_in_form'] = true;

// website
$zz['fields'][31]['fields'][3]['explanation']
	= 'Nur Website des Spielortes, falls vorhanden, nicht Vereinswebsite.';

// work phone
$zz['fields'][32]['title'] = 'Telefon';
$zz['fields'][32]['fields'][3]['explanation'] = 'Festnetz vor Ort. '
	.$zz['fields'][32]['fields'][3]['explanation'];

for ($i = 30; $i < 40; $i++) {
	if (!isset($zz['fields'][$i])) break;
	$zz['fields'][$i]['field_sequence'] = $i;
}

// description
$zz['fields'][12]['title'] = 'Hinweis <br>Anfahrt';
$zz['fields'][12]['explanation'] = 'Anfahrt mit Bahn, Auto, Lage des Spielorts';
$zz['fields'][12]['rows'] = 4;
$zz['fields'][12]['field_sequence'] = 12;

// contacts_contacts
$zz['fields'][60]['min_records'] = 1;
$zz['fields'][60]['min_records_required'] = 1;
$zz['fields'][60]['max_records'] = 1;
$zz['fields'][60]['form_display'] = 'inline';

// contacts_contacts.sequence
$zz['fields'][60]['fields'][6]['title'] = 'Reihenfolge';
$zz['fields'][60]['fields'][6]['field_sequence'] = 20;
$zz['fields'][60]['fields'][6]['explanation'] = '(Sortierung, falls es mehrere Spielorte gibt)';

// contacts_contacts.main_contact_id
$zz['fields'][60]['fields'][3]['type'] = 'hidden';
$zz['fields'][60]['fields'][3]['type_detail'] = 'select';
$zz['fields'][60]['fields'][3]['value'] = $club['contact_id'];
$zz['fields'][60]['fields'][3]['hide_in_form'] = true;

// contacts_contacts.remarks
$zz['fields'][60]['fields'][9]['title'] = 'Hinweis <br>Verein';
$zz['fields'][60]['fields'][9]['hide_in_form'] = false;
$zz['fields'][60]['fields'][9]['rows'] = 4;
$zz['fields'][60]['fields'][9]['format'] = 'markdown';
$zz['fields'][60]['fields'][9]['field_sequence'] = 19;
unset($zz['fields'][60]['fields'][9]['explanation']);

// contacts_contacts.published
if (empty($_SESSION['login_id'])) {
	$zz['fields'][60]['if']['insert']['fields'][10]['value'] = 'no';
} else {
	$zz['fields'][60]['if']['insert']['fields'][10]['value'] = 'yes';
}

// start_date
unset($zz['fields'][16]);

// end_date
unset($zz['fields'][17]);

// country_id
unset($zz['fields'][18]);

// remarks
unset($zz['fields'][13]);

// published
$zz['fields'][14]['hide_in_form'] = true;

// contacts_identifiers
unset($zz['fields'][19]);

// parameters
unset($zz['fields'][15]);

// created
$zz['fields'][97]['field_sequence'] = 97;

// last_update
$zz['fields'][99]['hide_in_form'] = true;
$zz['fields'][99]['field_sequence'] = 99;

if (empty($_SESSION['login_id'])) {
	$zz['hooks']['after_insert'] = 'mf_clubs_add_revision_public';
}

$zz_conf['revisions_url'] = '/orte/'; // @todo solve differently
$zz_conf['no_timeframe'] = true;
