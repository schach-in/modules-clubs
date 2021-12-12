<?php

/**
 * clubs module
 * missing data
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_clubs_missingdata($params) {
	if (count($params) !== 1) return false;
	
	if (!wrap_category_id('provider/'.$params[0], 'check')) return false;

	$sql = 'SELECT contacts.contact_id, contact, identifier
		FROM contacts
		LEFT JOIN contactdetails
			ON contacts.contact_id = contactdetails.contact_id
			AND contactdetails.provider_category_id = %d
		WHERE contact_category_id IN (%d, %d)
		AND ISNULL(contactdetails.contactdetail_id)
		AND ISNULL(end_date)
		ORDER BY identifier';
	$sql = sprintf($sql
		, wrap_category_id('provider/'.$params[0])
		, wrap_category_id('contact/club')
		, wrap_category_id('contact/chess-department')
	);
	$data = wrap_db_fetch($sql, 'contact_id');
	$data['missing'] = count($data);
	$data['category_path'] = $params[0];

	$page['text'] = wrap_template('missingdata', $data);
	$page['dont_show_h1'] = true;
	return $page;
}
