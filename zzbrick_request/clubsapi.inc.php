<?php

/**
 * clubs module
 * output JSON data for organisations
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * output JSON data for clubs, schools, etc.
 *
 * @param array $params
 * @return array $page
 */
function mod_clubs_clubsapi($params, $settings = []) {
	if (count($params) !== 1) return false;
	if (!isset($settings['search'])) $settings['search'] = NULL;
	
	switch ($settings['search']) {
	case 'zps':
		$sql = 'SELECT contact_id
			FROM contacts_identifiers
			LEFT JOIN contacts USING (contact_id)
			WHERE contacts_identifiers.identifier LIKE "%s%%"
			AND identifier_category_id = %d
			AND current = "yes"
			AND contact_category_id != %d
			ORDER BY contacts_identifiers.identifier';
		$sql = sprintf($sql
			, wrap_db_escape($params[0])
			, wrap_category_id('identifiers/pass_dsb')
			, wrap_category_id('contact/person')
		);
		break;
	default:
		return false;
	}

	$ids = wrap_db_fetch($sql, 'contact_id');
	wrap_include_files('zzbrick_request_get/contactdata', 'contacts');
	$data = mod_contacts_get_contactdata($ids);
	if (!$data) return false;
	$data = wrap_data_cleanup($data);

	$page['content_type'] = 'json';
	$page['ending'] = 'none';
	$page['headers']['filename'] = sprintf('%s.json', $params[0]);
	$page['text'] = json_encode($data, JSON_PRETTY_PRINT);
	return $page;
}
