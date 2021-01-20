<?php

// Zugzwang Project
// deutsche-schachjugend.de
// club module
// Copyright (c) 2016, 2019 Gustaf Mossakowski <gustaf@koenige.org>
// edit an organization


if (empty($brick['vars'])) wrap_quit(404);

$sql = 'SELECT org_id, organisation
		, SUBSTRING_INDEX(categories.path, "/", -1) AS path
	FROM organisationen
	LEFT JOIN categories USING (category_id)
	WHERE org_id = %d';
$sql = sprintf($sql, $brick['vars'][0]);
$verein = wrap_db_fetch($sql);
if (!$verein) wrap_quit(404);

require $zz_conf['form_scripts'].'/organisationen.php';

unset($zz['filter']);
unset($zz['details']);

$zz['title'] = $verein['organisation'];
$zz['where']['org_id'] = $verein['org_id'];
$zz['access'] = 'edit_only';
if (empty($_SESSION['login_id'])) {
	$zz['revisions_only'] = true;
}

$zz['fields'][6]['hide_in_form'] = true;
$zz['fields'][3]['type'] = 'hidden'; 
$zz['fields'][4]['hide_in_form'] = true;
$zz['fields'][34]['hide_in_form'] = true;
$zz['fields'][13]['hide_in_form'] = true;
$zz['fields'][2]['hide_in_form'] = true;
$zz['fields'][7]['hide_in_form'] = true;
$zz['fields'][40]['append_next'] = false;
$zz['fields'][40]['title_append'] = false;
$zz['fields'][12]['hide_in_form'] = true;
$zz['fields'][10]['hide_in_form'] = true;
$zz['fields'][15]['hide_in_form'] = true;
$zz['fields'][20]['hide_in_form'] = true;
if ($verein['path'] !== 'verein') {
	$zz['fields'][5]['hide_in_form'] = true; // Schachabteilung
	$zz['fields'][40]['hide_in_form'] = true; // Gründungsdatum
}

$zz['fields'][11]['explanation'] = 'Etwas über Ihren Verein (optional)';
$zz['fields'][9]['title'] = 'Bundesland';
$zz['fields'][40]['explanation'] = 'Falls bekannt: Datum oder Jahr der Gründung';

// Spielorte
$zz['fields'][24]['hide_in_form'] = true;

$zz_conf['referer'] = '../';
