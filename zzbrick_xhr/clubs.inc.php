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
	$data = [];
	$text = mb_strtolower($request['text']);
	$limit = $request['limit'] + 1;

	$concat = ' | ';
	$equal = substr($text, -1) === ' ' ? true : false;
	$text = trim($text);
	if (strstr($text, $concat)) {
		$text = explode($concat, $text);
	} else {
		$text = [$text];
	}

	$sql = sprintf('SELECT contacts.contact_id, contact
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
	$collation = '_utf8';

	$sql_fields = ['contact', 'contacts_identifiers.identifier'];
	foreach ($sql_fields as $no => $sql_field) {
		foreach ($text as $index => $value) {
			$query = $equal ? 'LOWER(%s) = %s"%s"' : 'LOWER(%s) LIKE %s"%%%s%%"';
			$where[$index][] = sprintf($query, $sql_field, $collation, wrap_db_escape($value));
		}
	}
	$conditions = [];
	foreach ($where as $condition) {
		$conditions[] = sprintf('(%s)', implode(' OR ', $condition));
	}
	if (str_starts_with(trim($sql), 'SHOW')) {
		$sql .= sprintf(' LIKE "%%%s%%"', $text[0]);
	} else {
		$sql = wrap_edit_sql($sql, 'WHERE', implode(' AND ', $conditions));
	}
	if ($sql_fields) {
	 	$id_field_name = $sql_fields[0];
		if (strstr($id_field_name, '.'))
			$id_field_name = substr($id_field_name, strrpos($id_field_name, '.') + 1);
		$records = wrap_db_fetch($sql, $id_field_name);
	} else {
		$records = wrap_db_fetch($sql, '_dummy_', 'numeric');
	}
	$records = array_values($records);
	if (count($records) > $limit) {
		// more records than we might show
		$data['entries'] = [];
		$data['entries'][] = ['text' => htmlspecialchars($request['text'])];
		$data['entries'][] = [
			'text' => wrap_text('Please enter more characters.'),
			'elements' => [
				0 => [
					'node' => 'div',
					'properties' => [
						'className' => 'xhr_foot',
						'text' => wrap_text('Please enter more characters.')
					]
				]
			]
		];
		return $data;
	}

	if (!$records) {
		$data['entries'][] = ['text' => htmlspecialchars($request['text'])];
		$data['entries'][] = [
			'text' => wrap_text('No record was found.'),
			'elements' => [
				0 => [
					'node' => 'div',
					'properties' => [
						'className' => 'xhr_foot',
						'text' => wrap_text('No record was found.')
					]
				]
			]
		];
		return $data;
	}
	
	$i = 0;
	foreach ($records as $record) {
		$j = 0;
		$text = [];
		unset($record['contact_id']);
		// search entry for zzform, concatenated and space at the end
		$data['entries'][$i]['text'] = implode($concat, $record).' ';
		$i++;
	}
	return $data;
}
