<?php

/**
 * clubs module
 * page element: show number of clubs
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2020-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function page_clubcount($params) {
	$sql = 'SELECT COUNT(*) FROM organisationen
		WHERE contact_category_id IN (%d, %d) AND ISNULL(aufloesung)';
	$sql = sprintf($sql
		, wrap_category_id('contact/club')
		, wrap_category_id('contact/chess-department')
	);
	$clubs = wrap_db_fetch($sql, '', 'single value');
	return $clubs;
}
