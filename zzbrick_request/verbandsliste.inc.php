<?php

/**
 * clubs module
 * output of a list of clubs per federation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_clubs_verbandsliste($params) {
	global $zz_setting;

	$sql = 'SELECT contact_id, contact, category_id, category, mother_contact_id
			, 1 AS aktiv
			, contacts.identifier
		FROM contacts
		LEFT JOIN categories
			ON contacts.contact_category_id = categories.category_id
		WHERE contacts.identifier = "%s"';
	$sql = sprintf($sql, wrap_db_escape($params[0]));
	$data = wrap_db_fetch($sql);
	if (!$data) {
		$categories = mf_clubs_from_category($params[0]);
		if (!$categories) {
			$sql = sprintf(wrap_sql('redirects')
				, '/'.wrap_db_escape($params[0])
				, '/'.wrap_db_escape($params[0])
				, '/'.wrap_db_escape($params[0])
			);
			$redirect = wrap_db_fetch($sql);
			if (!$redirect) return false;
			return wrap_redirect(sprintf('%sliste/', $redirect['new_url']));
		}
		return brick_format('%%% request vereinsliste '.$params[0].' %%%');
	}
	$data += mf_contacts_contactdetails($data['contact_id']);

	$sql = 'SELECT contacts.contact_id, contact, category, contacts.identifier
			, (SELECT COUNT(*) FROM contacts_contacts
				WHERE contacts_contacts.main_contact_id = contacts.contact_id
				AND contacts_contacts.published = "yes"
			) AS spielorte
			, members, members_female, members_u25, category_id
		FROM contacts
		LEFT JOIN categories
			ON contacts.contact_category_id = categories.category_id
		LEFT JOIN vereinsdb_stats USING (contact_id)
		LEFT JOIN contacts_identifiers
			ON contacts_identifiers.contact_id = contacts.contact_id
			AND contacts_identifiers.current = "yes"
		WHERE mother_contact_id IN (%s)
		AND ISNULL(end_date)
		ORDER BY categories.sequence, contact_short, contacts_identifiers.identifier';
	$children = wrap_db_children([$data], $sql, 'contact_id', 'mother_contact_id');
	if (count($children['ids']) === 1) return false; // only main club
	
	$federations = [
		wrap_category_id('contact/federation'),
		wrap_category_id('contact/youth-federation'),
		wrap_category_id('contact/other-organisation')
	];
	
	foreach ($children['flat'] as $org) {
		if (in_array($org['category_id'], $federations)) {
			$data['children'][$org['contact_id']] = $org;
		} else {
			$parent = false;
			for ($i = $org['_level']; $i > 0; $i--) {
				if (!$parent) $parent = $org['mother_contact_id'];
				$data['children'][$parent]['members'] = $data['children'][$parent]['members'] ?? 0;
				$data['children'][$parent]['members_female'] = $data['children'][$parent]['members_female'] ?? 0;
				$data['children'][$parent]['members_u25'] = $data['children'][$parent]['members_u25'] ?? 0;
				$data['children'][$parent]['vereine'] = $data['children'][$parent]['vereine'] ?? 0;
				$data['children'][$parent]['spielorte'] = $data['children'][$parent]['spielorte'] ?? 0;
				$data['children'][$parent]['members'] += $org['members'];
				$data['children'][$parent]['members_female'] += $org['members_female'];
				$data['children'][$parent]['members_u25'] += $org['members_u25'];
				if (in_array($org['category_id'], [
					wrap_category_id('contact/club'), wrap_category_id('contact/chess-department')
				])) {
					$data['children'][$parent]['vereine']++;
					if ($org['spielorte']) {
						$data['children'][$parent]['spielorte']++;
					}
				}
				if (isset($data['children'][$parent]['mother_contact_id'])) {
					$parent = $data['children'][$parent]['mother_contact_id'];
				}
			}
		}
	}
	foreach ($data['children'] as $id => $org) {
		if (!empty($org['vereine'])) {
			$data['children'][$id]['anteil_spielorte'] = $org['spielorte'] / $org['vereine'];
		}
		if (!empty($org['members'])) {
			$data['children'][$id]['anteil_members_female'] = $org['members_female'] / $org['members'];
			$data['children'][$id]['anteil_members_u25'] = $org['members_u25'] / $org['members'];
		}
	}
	if (count($data['children']) <= 2) {
		// federation + youth federation = 2, no federation has only one club
		return brick_format('%%% request vereinsliste '.$params[0].' %%%');
	}

	$data['parent_orgs'] = mf_clubs_parent_orgs($data['contact_id']);

	// remove empty values
	foreach ($children['flat'] as $org) {
		if (in_array($org['category_id'], $federations)) {
			if (!empty($data['children'][$org['contact_id']]['vereine'])) continue;
			if (!empty($data['children'][$org['contact_id']]['members'])) continue;
			unset($data['children'][$org['contact_id']]['members']);
			unset($data['children'][$org['contact_id']]['members_female']);
			unset($data['children'][$org['contact_id']]['members_u25']);
			unset($data['children'][$org['contact_id']]['vereine']);
			unset($data['children'][$org['contact_id']]['spielorte']);
		}
	}

	$page['title'] = 'Liste '.$data['contact'];
	if ($params[0] !== 'dsb') {
		$page['breadcrumbs'][] = '<a href="../">'.$data['contact'].'</a>';
	}
	$page['breadcrumbs'][] = 'Liste';
	$page['text'] = wrap_template('verbandsliste', $data);
	return $page;
}
