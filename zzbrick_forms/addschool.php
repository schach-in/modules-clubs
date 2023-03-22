<?php

/**
 * clubs module
 * form script: add a new school
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017, 2019-2021, 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


wrap_setting('cache', false);

$values['contactdetails_restrict_to'] = 'school';
$values['relations_restrict_to'] = 'school';
$zz = zzform_include_table('contacts/contacts', $values);

$zz['title'] = 'Add school chess group';

if (empty($_SESSION['login_id'])) {
	$zz['revisions_only'] = true;
}

// contact
$zz['fields'][2]['title'] = 'Name of the school';

// identifier
$zz['fields'][3]['hide_in_form'] = true;

// contact_short
unset($zz['fields'][10]);

// contact_category_id
$zz['fields'][4]['type'] = 'hidden';
$zz['fields'][4]['class'] = 'hidden';
$zz['fields'][4]['value'] = wrap_category_id('contact/school');

// address
$zz['fields'][5]['min_records'] = 1;
$zz['fields'][5]['max_records'] = 1;

$zz['fields'][5]['fields'][6]['type'] = 'hidden';
$zz['fields'][5]['fields'][6]['type_detail'] = 'select';
$zz['fields'][5]['fields'][6]['value'] = wrap_id('countries', 'DE');

$zz['fields'][5]['fields'][9]['type'] = 'hidden';
$zz['fields'][5]['fields'][9]['type_detail'] = 'select';
$zz['fields'][5]['fields'][9]['value'] = wrap_category_id('address/work');
$zz['fields'][5]['fields'][9]['hide_in_form'] = true;

// 30+ contactdetails

// descripton
$zz['fields'][12]['title'] = 'About us';

// start_date
$zz['fields'][16]['hide_in_form'] = true;

// end_date
$zz['fields'][17]['hide_in_form'] = true;

// country_id
$zz['fields'][18]['title'] = 'State';
$zz['fields'][18]['sql'] = 'SELECT country_id, country, main_country_id
	FROM countries
	ORDER BY country_code3';
$zz['fields'][18]['character_set'] = 'latin1';
$zz['fields'][18]['show_hierarchy'] = 'main_country_id';
$zz['fields'][18]['show_hierarchy_subtree'] = wrap_id('countries', 'DE');

// remarks
$zz['fields'][13]['hide_in_form'] = true;

// published
$zz['fields'][14]['hide_in_form'] = true;
$zz['fields'][14]['type'] = 'hidden';
$zz['fields'][14]['value'] = 'no';

// parameters
$zz['fields'][15]['hide_in_form'] = true;

$zz['access'] = 'add_then_edit';
$zz_conf['no_timeframe'] = true;
