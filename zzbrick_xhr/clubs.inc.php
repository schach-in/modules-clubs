<?php

/**
 * clubs module
 * XHR for clubs
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2020-2021, 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * get clubs for selection
 *
 * @param array $request
 *		int 'limit': max records
 *		string 'text': entered text
 * @return array
 */
function mod_clubs_xhr_clubs($request, $parameters) {
	$def['sql'] = sprintf('SELECT contacts.contact_id, contact
			, contacts_identifiers.identifier AS zps_code
		FROM contacts
		LEFT JOIN contacts_identifiers
			ON contacts_identifiers.contact_id = contacts.contact_id
			AND contacts_identifiers.current = "yes"
		WHERE ISNULL(end_date)
		AND contact_category_id IN (%d, %d)
		ORDER BY contacts_identifiers.identifier, contact_abbr'
		, wrap_category_id('contact/club')
		, wrap_category_id('contact/chess-department')
	);
	$def['sql_fields'] = ['contact', 'contacts_identifiers.identifier'];

	wrap_include_files('zzbrick_xhr/autosuggest', 'default');
	return mod_default_xhr_autosuggest($request, $def);
}
