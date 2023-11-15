<?php

/**
 * clubs module
 * delete an organisation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016, 2019-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


mf_clubs_deny_bots();
if (count($brick['vars']) !== 2) wrap_quit(404);
$contact = mf_clubs_club($brick['vars'][0]);
if (!$contact) wrap_quit(404);

$sql = 'SELECT cc_id
	FROM contacts_contacts
	WHERE main_contact_id = %d
	AND contact_id = %d';
$sql = sprintf($sql, $brick['vars'][0], $brick['vars'][1]);
$cc_id = wrap_db_fetch($sql, '', 'single value');
if (!$cc_id) {
	if (wrap_db_auto_increment('contacts') > $brick['vars'][1]) {
		wrap_quit(410, 'Der Eintrag wurde bereits gelöscht.');
	}
	wrap_quit(404);
}

$zz = zzform_include('contacts-contacts');
$zz['where']['cc_id'] = $cc_id;

// sequence
$zz['fields'][6]['title'] = 'Reihenfolge'; // @todo remove, is in contacts module text

// contact_id
$zz['fields'][2]['title'] = 'Spielort';

// relation_category_id
$zz['fields'][4]['hide_in_form'] = true;

// main_contact_id
$zz['fields'][3]['hide_in_form'] = true;

// role
$zz['fields'][11]['hide_in_form'] = true;

// remarks
$zz['fields'][9]['title'] = 'Hinweis<br> Verein';

// published
$zz['fields'][10]['hide_in_form'] = true;

// @todo $zz['access'] = 'delete_only';
if (empty($_POST)) $_GET['mode'] = 'delete';
elseif (empty($_POST['zz_action']) OR $_POST['zz_action'] !== 'delete') wrap_quit(403);

$zz['page']['referer'] = '../../';
$url = explode('/', $_SERVER['REQUEST_URI']);
array_pop($url); // slash
array_pop($url); // ID
array_pop($url); // ort-loeschen
$zz['record']['redirect']['successful_delete'] = implode('/', $url).'/';

if (empty($_SESSION['login_id'])) {
	$zz['revisions_only'] = true;
}

$zz['record']['no_timeframe'] = true;
