<?php

/**
 * clubs module
 * hook functions that are called before or after changing a record
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Hinzufügen einer Update-Revision für einen Ort zu einer Organisation,
 * oder einen Wochentermin, so dass oeffentlich = "ja" wird
 *
 * @param array $ops
 * @return array
 */
function mf_clubs_add_revision_public($ops) {
	$my_ops = [];
	foreach ($ops['return'] as $index => $table) {
		if (!in_array($table['table'], ['contacts_contacts', 'wochentermine'])) continue;
		if ($table['action'] !== 'insert') continue;
		if (!empty($ops['record_new'][$index]['oeffentlich']) AND $ops['record_new'][$index]['oeffentlich'] === 'ja') continue;
		if (!empty($ops['record_new'][$index]['published']) AND $ops['record_new'][$index]['published'] === 'yes') continue;
		
		$my_ops['return'][$index] = $ops['return'][$index];
		$my_ops['return'][$index]['action'] = 'update';
		$my_ops['return'][$index]['table_name'] = $my_ops['return'][$index]['table_name'];
		$my_ops['record_diff'][$index] = $ops['record_diff'][$index];
		$my_ops['record_new'][$index] = $ops['record_new'][$index];
		foreach (array_keys($my_ops['record_diff'][$index]) as $field_name) {
			if ($field_name === 'published') {
				$my_ops['record_new'][$index][$field_name] = 'yes';
				continue;
			}
			if ($field_name === 'oeffentlich') {
				$my_ops['record_new'][$index][$field_name] = 'ja';
				continue;
			}
			$my_ops['record_diff'][$index][$field_name] = 'same';
		}
	}
	if (!array_key_exists(0, $my_ops['return'])) {
		$my_ops['return'][0] = $ops['return'][0];
		$my_ops['record_diff'][0] = $ops['record_diff'][0];
		$my_ops['record_new'][0] = $ops['record_new'][0];
		foreach (array_keys($my_ops['record_diff'][0]) as $field_name) {
			$my_ops['record_diff'][0][$field_name] = 'same';
		}
	}
	if ($my_ops) {
		wrap_include_files('revisions', 'zzform');
		return zz_revisions($my_ops, [], true);
	}
}
