<?php

/**
 * clubs module
 * form script: delete a weekly event
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017, 2019, 2021, 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


mf_clubs_deny_bots();
if (count($brick['vars']) !== 2) wrap_quit(404);
$contact = mf_clubs_club($brick['vars'][0]);
if (!$contact) wrap_quit(404);

$zz = zzform_include('wochentermine');

$sql = 'SELECT wochentermin_id
	FROM wochentermine
	WHERE contact_id = %d
	AND wochentermin_id = %d';
$sql = sprintf($sql, $brick['vars'][0], $brick['vars'][1]);
$zz['where']['wochentermin_id'] = wrap_db_fetch($sql, '', 'single value');
if (!$zz['where']['wochentermin_id']) {
	if (wrap_db_auto_increment('wochentermine') > $brick['vars'][1]) {
		wrap_quit(410, 'Der Eintrag wurde bereits gelöscht.');
	}
	wrap_quit(404);
}

// @todo: $zz['access'] = 'delete_only';
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
