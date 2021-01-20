<?php

// Zugzwang Project
// deutsche-schachjugend.de
// club module
// Copyright (c) 2017, 2019 Gustaf Mossakowski <gustaf@koenige.org>
// delete a weekly event


if (empty($brick['vars'])) wrap_quit(404);
if (count($brick['vars']) !== 2) wrap_quit(404);

$sql = 'SELECT org_id, organisation, categories.path
	FROM organisationen
	LEFT JOIN categories USING (category_id)
	WHERE org_id = %d';
$sql = sprintf($sql, $brick['vars'][0]);
$verein = wrap_db_fetch($sql);
if (!$verein) wrap_quit(404);

$sql = 'SELECT wochentermin_id
	FROM wochentermine
	WHERE org_id = %d
	AND wochentermin_id = %d';
$sql = sprintf($sql, $brick['vars'][0], $brick['vars'][1]);
$zz['where']['wochentermin_id'] = wrap_db_fetch($sql, '', 'single value');
if (!$zz['where']['wochentermin_id']) {
	if (wrap_db_auto_increment('wochentermine') > $brick['vars'][1]) {
		wrap_quit(410, 'Der Eintrag wurde bereits gelöscht.');
	}
	wrap_quit(404);
}

require $zz_conf['form_scripts'].'/wochentermine.php';

// @todo: $zz['access'] = 'delete_only';
if (empty($_POST)) $_GET['mode'] = 'delete';
elseif (empty($_POST['zz_action']) OR $_POST['zz_action'] !== 'delete') wrap_quit(403);

$zz_conf['referer'] = '../../';
$url = explode('/', $_SERVER['REQUEST_URI']);
array_pop($url); // slash
array_pop($url); // ID
array_pop($url); // ort-loeschen
$zz_conf['redirect']['successful_delete'] = implode('/', $url).'/';

if (empty($_SESSION['login_id'])) {
	$zz['revisions_only'] = true;
}
