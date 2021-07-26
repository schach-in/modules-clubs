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
	$extra_field = '';

	$sql = 'SELECT contact_id, contact, mother_contact_id, 0 AS _level
			, contacts.identifier, contacts.description
		FROM contacts
		WHERE contacts.identifier = "%s"
		AND contact_category_id = %d';
	$sql = sprintf($sql
		, wrap_db_escape($params[0])
		, wrap_category_id('contact/federation')
	);
	$verband = wrap_db_fetch($sql);
	if ($verband) {
		$condition = sprintf('WHERE mother_contact_id = %d
			AND contact_category_id IN (%d, %d) 
			AND ISNULL(aufloesung)'
			, $verband['contact_id']
			, wrap_category_id('contact/club')
			, wrap_category_id('contact/chess-department')
		);
		$top = $verband;
		$categories = false;
	} elseif ($params[0] === 'twitter') {
		$extra_field = sprintf(', (SELECT COUNT(*) FROM contactdetails
			WHERE contactdetails.contact_id = contacts.contact_id
			AND provider_category_id = %d) AS website_username', wrap_category_id('provider/twitter'));
		$condition = 'HAVING website_username > 0';
		$top['contact'] = 'Twitter';
		$top['identifier'] = 'twitter';
		$categories = false;
		$category['category'] = 'Twitter';
		$data['with_usernames'] = true;
	} else {
		$categories = mf_clubs_from_category($params[0]);
		if (!$categories) return false;
		$category = reset($categories);
		$top = $category;
		$top['contact'] = $category['category'];
		$condition = sprintf('WHERE auszeichnung_category_id IN (%s)', implode(',', array_keys($categories)));
	}
	$top['members'] = 0;
	$top['members_u25'] = 0;
	$top['members_female'] = 0;
	
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
			, aufloesung
			%s
		FROM contacts
		LEFT JOIN contacts_identifiers USING (contact_id)
		LEFT JOIN vereinsdb_stats USING (contact_id)
		LEFT JOIN auszeichnungen USING (contact_id)
		%s
		ORDER BY contacts_identifiers.identifier, contacts.identifier';
	$sql = sprintf($sql, $extra_field, $condition);
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

	if (!empty($data['with_usernames'])) {
		$contactdetails = mf_contacts_contactdetails(array_keys($data['vereine']));
		foreach ($contactdetails as $contact_id => $details) {
			if (empty($details['username'])) continue;
			foreach ($details['username'] as $username) {
				if ($username['category'] !== 'Twitter') continue;
				$data['vereine'][$contact_id]['usernames'][] = [
					'username_url' => $username['username_url'],
					'username' => $username['identification']
				];
			}
		}
	}
	
	foreach ($data['vereine'] as $contact_id => $verein) {
		if (!empty($data['mit_auszeichnungen']))
			$data['vereine'][$contact_id]['mit_auszeichnungen'] = true;
		if (!empty($data['with_usernames']))
			$data['vereine'][$contact_id]['with_usernames'] = true;
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

	if ($verband) {
		$data += mf_contacts_contactdetails($verband['contact_id']);
		$data['parent_orgs'] = mf_clubs_parent_orgs($top['contact_id']);
	}
	if (!empty($top['description'])) $data['description'] = $top['description'];
	
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
