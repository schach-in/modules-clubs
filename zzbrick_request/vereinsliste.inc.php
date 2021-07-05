<?php

/**
 * clubs module
 * output of a list of all clubs
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_clubs_vereinsliste($params) {
	global $zz_setting;
	if (count($params) !== 1) return false;

	$sql = 'SELECT contact_id, contact, mother_contact_id, 0 AS _level
			, contacts.identifier, website, contacts.description
		FROM contacts
		WHERE contacts.identifier = "%s"
		AND contact_category_id = %d';
	$sql = sprintf($sql
		, wrap_db_escape($params[0])
		, wrap_category_id('contact/federation')
	);
	$verband = wrap_db_fetch($sql);
	if ($verband) {
		$condition = sprintf('mother_contact_id = %d
			AND contact_category_id IN (%d, %d) 
			AND ISNULL(aufloesung)'
			, $verband['contact_id']
			, wrap_category_id('contact/club')
			, wrap_category_id('contact/chess-department')
		);
		$top = $verband;
		$categories = false;
	} else {
		$categories = mf_clubs_from_category($params[0]);
		if (!$categories) return false;
		$category = reset($categories);
		$top = $category;
		$top['contact'] = $category['category'];
		$condition = sprintf('auszeichnung_category_id IN (%s)', implode(',', array_keys($categories)));
	}
	$top['members'] = 0;
	$top['members_u25'] = 0;
	$top['members_female'] = 0;
	
	$sql = 'SELECT contact_id, contact, contacts.identifier
			, organisationen_kennungen.identifier AS zps_code
			, members, members_female, members_u25
			, members_u25/members AS anteil_members_u25
			, members_female/members AS anteil_members_female
			, IF((SELECT COUNT(*) FROM contacts_contacts
				WHERE contacts_contacts.main_contact_id = contacts.contact_id
				AND contacts_contacts.published = "yes"), "ja", "nein"
			) AS spielort
			, 1 AS _level
			, aufloesung
		FROM contacts
		LEFT JOIN organisationen_kennungen USING (contact_id)
		LEFT JOIN vereinsdb_stats USING (contact_id)
		LEFT JOIN auszeichnungen USING (contact_id)
		WHERE %s
		ORDER BY organisationen_kennungen.identifier, contacts.identifier';
	$sql = sprintf($sql, $condition);
	$data['vereine'] = wrap_db_fetch($sql, 'contact_id');
	if (!$data['vereine']) return false;
	
	if ($categories) {
		$sql = 'SELECT auszeichnung_id, contact_id, dauer_von, dauer_bis, anzeigename
			FROM auszeichnungen
			WHERE contact_id IN (%s)
			AND %s
			ORDER BY dauer_von ASC';
		$sql = sprintf($sql, implode(',', array_keys($data['vereine'])), $condition);
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
	}
	
	foreach ($data['vereine'] as $verein) {
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
	array_unshift($data['vereine'], $top);

	if ($verband) {
		$data['parent_orgs'] = mf_clubs_parent_orgs($top['contact_id']);
	}
	if (!empty($top['description'])) $data['description'] = $top['description'];
	if (!empty($top['website'])) $data['website'] = $top['website'];
	
	if ($verband) {
		$page['title'] = $verband['contact'];
		$page['breadcrumbs'][] = $verband['contact'];
	} else {
		$page['title'] = $category['category'];
		$page['breadcrumbs'][] = $category['category'];
	}
	$page['text'] = wrap_template('vereinsliste', $data);
	return $page;
}
