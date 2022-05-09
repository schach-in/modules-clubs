<?php

/**
 * clubs module
 * output of a list of all organisations
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_clubs_stateorglist($params, $settings) {
	global $zz_setting;
	if (count($params) !== 1) return false;

	$sql = 'SELECT country_id, country AS contact, 0 AS _level
			, 1 AS without_members
			, CONCAT("%s", identifier) AS identifier
		FROM countries
		WHERE identifier = "%s"';
	$sql = sprintf($sql
		, 'schulen/' // @todo
		, wrap_db_escape($params[0])
	);
	$top = wrap_db_fetch($sql);
	if (!$top) return false;
	
	$condition = '';
	if (!empty($settings['category'])) {
		$condition = sprintf('AND contact_category_id = %d', wrap_category_id('contact/'.$settings['category']));
		$sql = 'SELECT category FROM categories WHERE category_id = %d';
		$sql = sprintf($sql, wrap_category_id('contact/'.$settings['category']));
		$data['org_category'] = wrap_db_fetch($sql, '', 'single value');
	}
	
	$top['members'] = 0;
	$top['members_u25'] = 0;
	$top['members_female'] = 0;
	
	// @todo sort by place
	$sql = 'SELECT contact_id, contact, contacts.identifier
			, contacts_identifiers.identifier AS zps_code
			, members, members_female, members_u25
			, members_u25/members AS anteil_members_u25
			, members_female/members AS anteil_members_female
			, IF((SELECT COUNT(*) FROM contacts_contacts
				WHERE contacts_contacts.main_contact_id = contacts.contact_id
				AND contacts_contacts.published = "yes"), "ja", "nein"
			) AS spielort
			, 1 AS _level
			, end_date
		FROM contacts
		LEFT JOIN contacts_identifiers USING (contact_id)
		LEFT JOIN vereinsdb_stats USING (contact_id)
		LEFT JOIN auszeichnungen USING (contact_id)
		WHERE country_id = %d
		%s
		ORDER BY contacts_identifiers.identifier, contacts.identifier';
	$sql = sprintf($sql, $top['country_id'], $condition);
	$data['vereine'] = wrap_db_fetch($sql, 'contact_id');
	if (!$data['vereine']) return false;

	$sql = 'SELECT auszeichnung_id, contact_id, dauer_von, dauer_bis, anzeigename, category
		FROM auszeichnungen
		LEFT JOIN contacts USING (contact_id)
		LEFT JOIN categories
			ON auszeichnungen.auszeichnung_category_id = categories.category_id
		WHERE contact_id IN (%s)
		%s
		ORDER BY dauer_von ASC';
	$sql = sprintf($sql, implode(',', array_keys($data['vereine'])), str_replace('WHERE ', 'AND ', $condition));
	$auszeichnungen = wrap_db_fetch($sql, ['contact_id', 'auszeichnung_id']);
	foreach ($auszeichnungen as $contact_id => $auszeichnungen_pro_org) {
		$anzeigenamen = [];
		foreach ($auszeichnungen_pro_org as $auszeichnung) {
			$anzeigenamen[$auszeichnung['anzeigename']]['anzeigename'] = $auszeichnung['anzeigename'];
		}
		$data['vereine'][$contact_id]['anzeigenamen'] = array_values($anzeigenamen); 
		$data['vereine'][$contact_id]['auszeichnungen'] = $auszeichnungen_pro_org;
	}
	$data['mit_auszeichnungen'] = true;
	$data['without_members'] = true;

	foreach ($data['vereine'] as $contact_id => $verein) {
		if (!empty($data['mit_auszeichnungen']))
			$data['vereine'][$contact_id]['mit_auszeichnungen'] = true;
		if (!empty($data['with_usernames']))
			$data['vereine'][$contact_id]['with_usernames'] = true;
		if (!empty($data['without_members']))
			$data['vereine'][$contact_id]['without_members'] = true;
		if (!empty($verein['auszeichnungen'])) {
			$last_category = false;
			foreach ($verein['auszeichnungen'] as $auszeichnung_id => $auszeichnung) {
				if ($last_category === $auszeichnung['category'])
					unset($data['vereine'][$contact_id]['auszeichnungen'][$auszeichnung_id]['category']);
				$last_category = $auszeichnung['category'];
			}
		}
		$top['members'] += $verein['members'];
		$top['members_u25'] += $verein['members_u25'];
		$top['members_female'] += $verein['members_female'];
	}
	if ($top['members']) {
		$top['anteil_members_u25'] = $top['members_u25'] / $top['members'];
		$top['anteil_members_female'] = $top['members_female'] / $top['members'];
	} else {
		$top['members'] = '';
		$top['members_u25'] = '';
		$top['members_female'] = '';
	}
	if (!empty($data['mit_auszeichnungen']))
		$top['mit_auszeichnungen'] = true;
	if (!empty($data['with_usernames']))
		$top['with_usernames'] = true;
	array_unshift($data['vereine'], $top);

	if (!empty($top['description'])) $data['description'] = $top['description'];
	
	$page['title'] = $top['contact'];
	$page['breadcrumbs'][] = $top['contact'];
	$page['text'] = wrap_template('vereinsliste', $data);
	return $page;
}
