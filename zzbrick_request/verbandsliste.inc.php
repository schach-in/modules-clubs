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

	$sql = 'SELECT org_id, contact, category_id, category, mutter_org_id
			, 1 AS aktiv
			, contacts.identifier
			, website
		FROM contacts
		LEFT JOIN categories
			ON contacts.contact_category_id = categories.category_id
		WHERE contacts.identifier = "%s"';
	$sql = sprintf($sql, wrap_db_escape($params[0]));
	$data = wrap_db_fetch($sql);
	if (!$data) {
		$categories = mf_clubs_from_category($params[0]);
		if (!$categories) {
			$sql = sprintf(wrap_sql('redirects'), '/'.$params[0], '/'.$params[0], '/'.$params[0]);
			$redirect = wrap_db_fetch($sql);
			if (!$redirect) return false;
			return wrap_redirect(sprintf('%sliste/', $redirect['new_url']));
		}
		return brick_format('%%% request vereinsliste '.$params[0].' %%%');
	}

	$sql = 'SELECT contacts.org_id, contact, category, mutter_org_id, contacts.identifier
			, (SELECT COUNT(*) FROM organisationen_orte
				WHERE organisationen_orte.org_id = contacts.org_id
				AND organisationen_orte.published = "yes"
			) AS spielorte
			, members, members_female, members_u25, category_id
		FROM contacts
		LEFT JOIN categories
			ON contacts.contact_category_id = categories.category_id
		LEFT JOIN vereinsdb_stats USING (org_id)
		LEFT JOIN organisationen_kennungen
			ON organisationen_kennungen.org_id = contacts.org_id
			AND organisationen_kennungen.current = "yes"
		WHERE mutter_org_id IN (%s)
		AND ISNULL(aufloesung)
		ORDER BY categories.sequence, contact_short, organisationen_kennungen.identifier';
	$children = wrap_db_children([$data], $sql, 'org_id', 'hierarchy');
	if (count($children['ids']) === 1) return false; // only main club
	
	$federations = [
		wrap_category_id('contact/federation'),
		wrap_category_id('contact/youth-federation'),
		wrap_category_id('contact/other-organisation')
	];
	
	foreach ($children['flat'] as $org) {
		if (in_array($org['category_id'], $federations)) {
			$data['children'][$org['org_id']] = $org;
		} else {
			$parent = false;
			for ($i = $org['_level']; $i > 0; $i--) {
				if (!$parent) $parent = $org['mutter_org_id'];
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
				if (isset($data['children'][$parent]['mutter_org_id'])) {
					$parent = $data['children'][$parent]['mutter_org_id'];
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

	$data['parent_orgs'] = mf_clubs_parent_orgs($data['org_id']);

	// remove empty values
	foreach ($children['flat'] as $org) {
		if (in_array($org['category_id'], $federations)) {
			if (!empty($data['children'][$org['org_id']]['vereine'])) continue;
			if (!empty($data['children'][$org['org_id']]['members'])) continue;
			unset($data['children'][$org['org_id']]['members']);
			unset($data['children'][$org['org_id']]['members_female']);
			unset($data['children'][$org['org_id']]['members_u25']);
			unset($data['children'][$org['org_id']]['vereine']);
			unset($data['children'][$org['org_id']]['spielorte']);
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
