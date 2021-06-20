<?php

/**
 * clubs module
 * form script: edit places
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2017, 2019-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (empty($brick['vars'])) wrap_quit(404);
$verein = mf_clubs_club($brick['vars'][0]);
if (!$verein) wrap_quit(404);

require $zz_setting['custom'].'/zzbrick_forms/places.php';
$zz_conf['revisions_url'] = '/intern/orte/';

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

$zz['title'] = $verein['contact'];
$zz['where']['organisationen_orte.org_id'] = $verein['org_id'];
$zz['sql'] = wrap_edit_sql($zz['sql'], 'JOIN', 'LEFT JOIN organisationen_orte
	ON organisationen_orte.contact_id = contacts.contact_id');
$zz['unless']['add']['explanation'] = '<strong>Hinweis:</strong> Bitte korrigiere hier nur Angaben zu diesem Spielort. Bei <strong>Wechsel</strong> des Spielorts lösche bitte den alten und <a href="../../ort-neu/">ergänze einen neuen</a>!';
$zz['if']['add']['explanation'] = '';
$zz['geo_map_html'] = false;
$zz_conf['export'] = false;
if (empty($_SESSION['login_id'])) {
	$zz['hooks']['after_insert'] = 'mf_clubs_add_revision_public';
}

$zz['fields'][2]['title'] = 'Spielort';
$zz['fields'][2]['explanation'] = 'Name des Orts, ggf. Vereinsnamen verwenden';

$zz['fields'][5]['fields'][7]['hide_in_form'] = true; // latitude
$zz['fields'][5]['fields'][8]['hide_in_form'] = true; // longitude
unset($zz['fields'][5]['fields'][6]['add_details']); // keine Länder hinzufügen

$zz['fields'][12]['title'] = 'Hinweis <br>Anfahrt';
$zz['fields'][12]['explanation'] = 'Anfahrt mit Bahn, Auto, Lage des Spielorts';
$zz['fields'][12]['rows'] = 4;

$zz['fields'][24]['fields'][2]['hide_in_form'] = true;
$zz['fields'][24]['min_records'] = 1;
$zz['fields'][24]['max_records'] = 1;
$zz['fields'][24]['sql'] .= sprintf(' WHERE org_id = %d', $verein['org_id']);
$zz['fields'][44] = $zz['fields'][24];
$zz['fields'][24]['fields'][2]['suffix'] = '';
$zz['fields'][24]['fields'][9]['prefix'] = '';
$zz['fields'][24]['fields'][6]['prefix'] = '';
$zz['fields'][24]['fields'][6]['suffix'] = '';

unset($zz['fields'][24]['fields'][6]);
$zz['fields'][24]['fields'][9]['type'] = 'memo';
$zz['fields'][24]['fields'][9]['rows'] = 4;
$zz['fields'][24]['fields'][9]['format'] = 'markdown';
$zz['fields'][24]['hide_in_list'] = true;
$zz['fields'][24]['title'] = 'Hinweis <br>Verein';
if (empty($_SESSION['login_id'])) {
	$zz['fields'][24]['if']['add']['fields'][10]['value'] = 'no';
}

unset($zz['fields'][44]['fields'][9]);
$zz['fields'][44]['table_name'] = 'reihenfolgen';
$zz['fields'][44]['title'] = 'Reihenfolge';
$zz['fields'][44]['title_tab'] = 'Folge';
$zz['fields'][44]['subselect']['sql'] = 'SELECT contact_id, reihenfolge
	FROM organisationen_orte
	LEFT JOIN organisationen USING (org_id)
	ORDER BY contact';

// Add geht nicht
$zz['fields'][44]['if']['add'] = false;

$zz['fields'][30]['title'] = 'Telefon';
$zz['fields'][30]['fields'][3]['explanation'] = 'Festnetz vor Ort. '.$zz['fields'][30]['fields'][3]['explanation'];

$zz['fields'][32]['hide_in_form'] = true; // E-Mail

$zz['fields'][99]['hide_in_form'] = true;

$zz['sqlorder'] = ' ORDER BY reihenfolge, postcode, contact';

$zz_conf['no_timeframe'] = true;
