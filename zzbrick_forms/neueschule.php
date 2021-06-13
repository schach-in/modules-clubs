<?php

/**
 * clubs module
 * form script: add a new school
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017, 2019-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz_setting['cache'] = false;
require $zz_conf['form_scripts'].'/organisationen.php';

if (empty($_SESSION['login_id'])) {
	$zz['revisions_only'] = true;
}

$zz['title'] = 'Schulschachgruppen';
$zz['access'] = 'add_then_edit';

$zz['fields'][3]['title'] = 'Name der Schule';

$zz['fields'][11]['title'] = 'Über uns';

$zz['fields'][6]['type'] = 'hidden';
$zz['fields'][6]['class'] = 'hidden';
$zz['fields'][6]['value'] = wrap_category_id('organisationen/schulschachgruppe');

unset($zz['fields'][34]);
unset($zz['fields'][4]);

$zz['fields'][9]['title'] = 'Land';

$zz['fields'][24] = zzform_include_table('../zzbrick_forms/places');
unset($zz['fields'][24]['fields'][24]);
$zz['fields'][24]['type'] = 'subtable';
$zz['fields'][24]['form_display'] = 'inline';
$zz['fields'][24]['list_display'] = 'inline';
$zz['fields'][24]['title'] = 'Ort';
$zz['fields'][24]['min_records'] = 1;
$zz['fields'][24]['min_records_required'] = 1;
$zz['fields'][24]['max_records'] = 1;
unset($zz['fields'][24]['explanation']);
//$zz['fields'][24]['fields'][25]['type'] = 'foreign_key';

unset($zz['fields'][13]);
unset($zz['fields'][2]);
$zz['fields'][7]['class'] = 'hidden';
unset($zz['fields'][5]);

unset($zz['fields'][40]['title_append']);
$zz['fields'][40]['append_next'] = false;

unset($zz['fields'][10]);
unset($zz['fields'][12]);
unset($zz['fields'][15]);

$zz_conf['no_timeframe'] = true;
