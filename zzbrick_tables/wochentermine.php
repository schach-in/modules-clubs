<?php 

/**
 * clubs module
 * table definition: Weekly recurring events 
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2006-2012, 2016-2017, 2019-2021, 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Wöchentliche Termine';
$zz['table'] = '/*_PREFIX_*/wochentermine';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'wochentermin_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['title'] = 'Verein';
$zz['fields'][2]['field_name'] = 'contact_id';
$zz['fields'][2]['type'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT contact_id, contact
	FROM /*_PREFIX_*/contacts
	LEFT JOIN /*_PREFIX_*/categories
		ON /*_PREFIX_*/contacts.contact_category_id = /*_PREFIX_*/categories.category_id
	WHERE /*_PREFIX_*/categories.parameters LIKE "%&weekly_events=1%"';
$zz['fields'][2]['display_field'] = 'contact';
$zz['fields'][2]['search'] = 'organisationen.contact';
$zz['fields'][2]['character_set'] = 'utf8';
$zz['fields'][2]['if']['where']['class'] = 'hidden';
$zz['fields'][2]['if']['where']['hide_in_list'] = true;
$zz['fields'][2]['group_in_list'] = true;

$zz['fields'][9]['title_tab'] = 'Woche';
$zz['fields'][9]['title'] = 'Woche im Monat';
$zz['fields'][9]['field_name'] = 'woche_im_monat';
$zz['fields'][9]['type'] = 'select';
$zz['fields'][9]['enum'] = ['1', '2', '3', '4', '5', 'letzte'];
$zz['fields'][9]['enum_title'] = ['1.', '2.', '3.', '4.', '5.', 'letzte (4. oder 5.)'];
$zz['fields'][9]['explanation'] = 'für Termine wie z.&nbsp;B. 1. Donnerstag im Monat';

$zz['fields'][3]['field_name'] = 'wochentag';
$zz['fields'][3]['type'] = 'select';
$zz['fields'][3]['enum'] = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Sonnabend', 'Sonntag'];

$zz['fields'][4]['title'] = 'Beginn';
$zz['fields'][4]['field_name'] = 'uhrzeit_beginn';
$zz['fields'][4]['type'] = 'time';
$zz['fields'][4]['unit'] = 'Uhr';
$zz['fields'][4]['list_append_next'] = true;

$zz['fields'][5]['title'] = 'Ende';
$zz['fields'][5]['field_name'] = 'uhrzeit_ende';
$zz['fields'][5]['type'] = 'time';
$zz['fields'][5]['unit'] = 'Uhr';
$zz['fields'][5]['list_prefix'] = '–';

$zz['fields'][6]['title'] = 'Kategorie';
$zz['fields'][6]['field_name'] = 'wochentermin_category_id';
$zz['fields'][6]['type'] = 'select';
$zz['fields'][6]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories
	ORDER BY sequence';
$zz['fields'][6]['show_hierarchy_subtree'] = wrap_category_id('wochentermine');
$zz['fields'][6]['show_hierarchy'] = 'main_category_id';
$zz['fields'][6]['display_field'] = 'category';

$zz['fields'][7]['field_name'] = 'beschreibung';
$zz['fields'][7]['type'] = 'memo';
$zz['fields'][7]['format'] = 'markdown';

$zz['fields'][8]['title'] = 'Spielort';
$zz['fields'][8]['field_name'] = 'place_contact_id';
$zz['fields'][8]['key_field_name'] = 'contact_id';
$zz['fields'][8]['id_field_name'] = 'contacts.contact_id';
$zz['fields'][8]['type'] = 'select';
$zz['fields'][8]['sql'] = 'SELECT contact_id, postcode, contact AS place_contact
	FROM contacts
	LEFT JOIN addresses USING (contact_id)
	WHERE contact_category_id = /*_ID categories contact/place _*/
	ORDER BY postcode';
$zz['fields'][8]['display_field'] = 'place_contact';
$zz['fields'][8]['search'] = 'places.contact';
$zz['fields'][8]['character_set'] = 'utf8';
$zz['fields'][8]['explanation'] = 'Nur angeben, falls Spielort vom normalen Vereinslokal abweicht';

$zz['fields'][10]['title'] = 'Öffentlich?';
$zz['fields'][10]['field_name'] = 'oeffentlich';
$zz['fields'][10]['type'] = 'select';
$zz['fields'][10]['enum'] = ['ja', 'nein'];
$zz['fields'][10]['default'] = 'ja';
$zz['fields'][10]['hide_in_form'] = true;
$zz['fields'][10]['prefix'] = '<br>Veröffentlicht: ';
$zz['fields'][10]['if']['revise']['hide_in_form'] = false;
$zz['fields'][10]['def_val_ignore'] = true;

$zz['fields'][99]['field_name'] = 'last_update';
$zz['fields'][99]['type'] = 'timestamp';
$zz['fields'][99]['hide_in_list'] = true;

$zz['sql'] = 'SELECT /*_PREFIX_*/wochentermine.* 
		, TIME_FORMAT(uhrzeit_beginn, "%H:%i") AS uhrzeit_beginn
		, TIME_FORMAT(uhrzeit_ende, "%H:%i") AS uhrzeit_ende
		, places.contact AS place_contact
		, /*_PREFIX_*/organisationen.contact
		, /*_PREFIX_*/categories.category
	FROM /*_PREFIX_*/wochentermine
	LEFT JOIN /*_PREFIX_*/contacts organisationen USING (contact_id)
	LEFT JOIN contacts places
		ON wochentermine.place_contact_id = places.contact_id
	LEFT JOIN /*_PREFIX_*/categories
		ON /*_PREFIX_*/wochentermine.wochentermin_category_id = /*_PREFIX_*/categories.category_id
';
$zz['sqlorder'] = ' ORDER BY organisationen.contact, wochentag, uhrzeit_beginn	';

$zz['subtitle']['contact_id']['sql'] = $zz['fields'][2]['sql'];
$zz['subtitle']['contact_id']['var'] = ['contact'];
