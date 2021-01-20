<?php

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2020 Gustaf Mossakowski <gustaf@koenige.org>
// Anzahl der Vereine


function page_clubcount($params) {
	$sql = 'SELECT COUNT(org_id) FROM organisationen
		WHERE category_id = %d AND ISNULL(aufloesung)';
	$sql = sprintf($sql, wrap_category_id('organisationen/verein'));
	$clubs = wrap_db_fetch($sql, '', 'single value');
	return $clubs;
}
