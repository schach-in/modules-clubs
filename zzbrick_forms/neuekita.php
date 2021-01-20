<?php

// Zugzwang Project
// deutsche-schachjugend.de
// club module
// Copyright (c) 2017 Gustaf Mossakowski <gustaf@koenige.org>
// add a new kindergarten


require $zz_conf['form_scripts'].'/organisationen.php';

if (empty($_SESSION['login_id'])) {
	$zz['revisions_only'] = true;
}

$zz['access'] = 'add_then_edit';
