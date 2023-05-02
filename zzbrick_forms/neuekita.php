<?php

/**
 * clubs module
 * form script: add a new kindergarten
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017, 2021, 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz = zzform_include('organisationen'); // @todo use contacts directly

if (empty($_SESSION['login_id']))
	$zz['revisions_only'] = true;

$zz['access'] = 'add_then_edit';

$zz_conf['no_timeframe'] = true;
