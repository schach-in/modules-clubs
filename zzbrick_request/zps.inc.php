<?php

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2017-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Umleitung von ZPS-Codes


function mod_clubs_zps($params) {
	if (count($params) !== 1) return false;
	
	switch (strlen($params[0])) {
	case 1:
		$code = sprintf('%s00', $params[0]);
		break;
	case 2:
		$code = sprintf('%s0', $params[0]);
		break;
	case 3:
	case 5:
		$code = $params[0];
		break;
	default:
		return false;
	}
	$sql = 'SELECT organisationen.kennung
		FROM organisationen
		LEFT JOIN organisationen_kennungen ok USING (org_id)
		WHERE identifier_category_id = %d
		AND ok.identifier = "%s"';
	$sql = sprintf($sql, wrap_category_id('kennungen/zps'), $code);
	$kennung = wrap_db_fetch($sql, '', 'single value');
	if (!$kennung) return false;
	return brick_format('%%% redirect 307 /'.$kennung.'/ %%%');
}
