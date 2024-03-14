<?php

/**
 * clubs module
 * output of a list of all clubs
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_clubs_clublist($params) {
	if (count($params) !== 1) return false;
	$extra_field = '';

	$sql = 'SELECT contacts.contact_id, contact, main_contact_id, 0 AS _level
			, contacts.identifier, contacts.description
		FROM contacts
		LEFT JOIN contacts_contacts
			ON contacts_contacts.contact_id = contacts.contact_id
			AND relation_category_id = %d
		WHERE contacts.identifier = "%s"
		AND contact_category_id = %d';
	$sql = sprintf($sql
		, wrap_category_id('relation/member')
		, wrap_db_escape($params[0])
		, wrap_category_id('contact/federation')
	);
	$verband = wrap_db_fetch($sql);
	if ($verband) {
		$condition = sprintf('WHERE main_contact_id = %d
			AND contact_category_id IN (%d, %d) 
			AND ISNULL(end_date)'
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
		$condition = sprintf('WHERE award_category_id IN (%s)', implode(',', array_keys($categories)));
	}
	$top['members'] = 0;
	$top['members_u25'] = 0;
	$top['members_female'] = 0;
	
	$sql = 'SELECT contacts.contact_id, contact, contacts.identifier
			, contacts_identifiers.identifier AS zps_code
			, members, members_female, members_u25
			, members_u25/members AS share_members_u25
			, members_female/members AS share_members_female
			, IF((SELECT COUNT(*) FROM contacts_contacts
				WHERE contacts_contacts.main_contact_id = contacts.contact_id
				AND contacts_contacts.relation_category_id = %d
				AND contacts_contacts.published = "yes"), "ja", "nein"
			) AS has_venue
			, 1 AS _level
			, end_date
			%s
		FROM contacts
		LEFT JOIN contacts_identifiers USING (contact_id)
		LEFT JOIN vereinsdb_stats USING (contact_id)
		LEFT JOIN awards USING (contact_id)
		LEFT JOIN contacts_contacts
			ON contacts_contacts.contact_id = contacts.contact_id
			AND contacts_contacts.relation_category_id = %d
		%s
		ORDER BY contacts_identifiers.identifier, contacts.identifier';
	$sql = sprintf($sql
		, wrap_category_id('relation/venue')
		, $extra_field
		, wrap_category_id('relation/member')
		, $condition
	);
	$data['vereine'] = wrap_db_fetch($sql, 'contact_id');
	if (!$data['vereine']) return false;
	
	if ($categories) {
		$sql = 'SELECT award_id, contact_id, award_year, award_year_to, contact_display_name
			FROM awards
			WHERE contact_id IN (%s)
			%s
			ORDER BY award_year ASC';
		$sql = sprintf($sql, implode(',', array_keys($data['vereine'])), str_replace('WHERE ', 'AND ', $condition));
		$awards = wrap_db_fetch($sql, ['contact_id', 'award_id']);
		foreach ($awards as $contact_id => $awards_pro_org) {
			$contact_display_names = [];
			foreach ($awards_pro_org as $auszeichnung) {
				$contact_display_names[$auszeichnung['contact_display_name']]['contact_display_name'] = $auszeichnung['contact_display_name'];
			}
			$data['vereine'][$contact_id]['contact_display_names'] = array_values($contact_display_names); 
			$data['vereine'][$contact_id]['awards'] = $awards_pro_org;
		}
		$data['with_awards'] = true;
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
		if (!empty($data['with_awards']))
			$data['vereine'][$contact_id]['with_awards'] = true;
		if (!empty($data['with_usernames']))
			$data['vereine'][$contact_id]['with_usernames'] = true;
		$top['members'] += $verein['members'];
		$top['members_u25'] += $verein['members_u25'];
		$top['members_female'] += $verein['members_female'];
	}
	if ($top['members']) {
		$top['share_members_u25'] = $top['members_u25'] / $top['members'];
		$top['share_members_female'] = $top['members_female'] / $top['members'];
	} else {
		$top['members'] = '';
		$top['members_u25'] = '';
		$top['members_female'] = '';
	}
	if (!empty($data['with_awards']))
		$top['with_awards'] = true;
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
		$page['breadcrumbs'][]['title'] = $verband['contact'];
	} else {
		$page['title'] = $category['category'];
		$page['breadcrumbs'][]['title'] = $category['category'];
	}
	$page['text'] = wrap_template('clublist', $data);
	return $page;
}
