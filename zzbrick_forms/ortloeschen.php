<?php

/**
 * clubs module
 * delete an organisation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016, 2019-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (empty($brick['vars'])) wrap_quit(404);
if (count($brick['vars']) !== 2) wrap_quit(404);

$sql = 'SELECT org_id, organisation, categories.path
	FROM organisationen
	LEFT JOIN categories
		ON organisationen.contact_category_id = categories.category_id
	WHERE org_id = %d';
$sql = sprintf($sql, $brick['vars'][0]);
$verein = wrap_db_fetch($sql);
if (!$verein) wrap_quit(404);

$sql = 'SELECT cc_id
	FROM organisationen_orte
	WHERE org_id = %d
	AND contact_id = %d';
$sql = sprintf($sql, $brick['vars'][0], $brick['vars'][1]);
$cc_id = wrap_db_fetch($sql, '', 'single value');
if (!$cc_id) {
	if (wrap_db_auto_increment('contacts') > $brick['vars'][1]) {
		wrap_quit(410, 'Der Eintrag wurde bereits gelöscht.');
	}
	wrap_quit(404);
}

$zz = zzform_include_table('organisationen-orte');
$zz['where']['cc_id'] = $cc_id;

// @todo $zz['access'] = 'delete_only';
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

$zz_conf['no_timeframe'] = true;
