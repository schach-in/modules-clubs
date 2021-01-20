<?php

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2016-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Ausgabe der Vereinsliste pro Verband


function mod_clubs_verbandsliste($params) {
	global $zz_setting;

	$sql = 'SELECT org_id, organisation, category, mutter_org_id
			, 1 AS aktiv
			, organisationen.kennung
			, website
		FROM organisationen
		LEFT JOIN categories USING (category_id)
		WHERE organisationen.kennung = "%s"';
	$sql = sprintf($sql, wrap_db_escape($params[0]));
	$data = wrap_db_fetch($sql);
	if (!$data) {
		$categories = clubs_from_category($params[0]);
		if (!$categories) {
			$sql = sprintf(wrap_sql('redirects'), '/'.$params[0], '/'.$params[0], '/'.$params[0]);
			$redirect = wrap_db_fetch($sql);
			if (!$redirect) return false;
			return brick_format('%%% redirect '.$redirect['new_url'].'liste/ %%%');
		}
		return brick_format('%%% request vereinsliste '.$params[0].' %%%');
	}

	$sql = 'SELECT organisationen.org_id, organisation, category, mutter_org_id, organisationen.kennung
			, (SELECT COUNT(main_contact_id) FROM organisationen_orte WHERE organisationen_orte.org_id = organisationen.org_id AND organisationen_orte.published = "yes") AS spielorte
			, members, members_female, members_u25
		FROM organisationen
		LEFT JOIN categories USING (category_id)
		LEFT JOIN vereinsdb_stats USING (org_id)
		LEFT JOIN organisationen_kennungen
			ON organisationen_kennungen.org_id = organisationen.org_id
			AND organisationen_kennungen.current = "yes"
		WHERE mutter_org_id IN (%s)
		AND ISNULL(aufloesung)
		ORDER BY categories.sequence, org_kurz, organisationen_kennungen.identifier';
	$children = wrap_db_children([$data], $sql, 'org_id', 'hierarchy');
	if (count($children['ids']) === 1) return false; // only main club
	
	foreach ($children['flat'] as $org) {
		if (in_array($org['category'], ['Verband', 'Jugendverband'])) {
			$data['children'][$org['org_id']] = $org;
			$data['children'][$org['org_id']]['members'] = 0;
			$data['children'][$org['org_id']]['members_female'] = 0;
			$data['children'][$org['org_id']]['members_u25'] = 0;
			$data['children'][$org['org_id']]['vereine'] = 0;
			$data['children'][$org['org_id']]['spielorte'] = 0;
		} else {
			$parent = false;
			for ($i = $org['_level']; $i > 0; $i--) {
				if (!$parent) $parent = $org['mutter_org_id'];
				$data['children'][$parent]['members'] += $org['members'];
				$data['children'][$parent]['members_female'] += $org['members_female'];
				$data['children'][$parent]['members_u25'] += $org['members_u25'];
				if ($org['category'] === 'Verein') {
					$data['children'][$parent]['vereine']++;
					if ($org['spielorte']) {
						$data['children'][$parent]['spielorte']++;
					}
				}
				$parent = $data['children'][$parent]['mutter_org_id'];
			}
		}
	}
	foreach ($data['children'] as $id => $org) {
		if (!$org['vereine']) {
			unset($data['children'][$id]);
			continue;
		}
		$data['children'][$id]['anteil_spielorte'] = $org['spielorte'] / $org['vereine'];
		$data['children'][$id]['anteil_members_female'] = $org['members_female'] / $org['members'];
		$data['children'][$id]['anteil_members_u25'] = $org['members_u25'] / $org['members'];
	}
	if (count($data['children']) === 1) {
		return brick_format('%%% request vereinsliste '.$params[0].' %%%');
	}

	$data['parent_orgs'] = clubs_parent_orgs($data['org_id']);

	$page['title'] = 'Liste '.$data['organisation'];
	if ($params[0] !== 'dsb') {
		$page['breadcrumbs'][] = '<a href="../">'.$data['organisation'].'</a>';
	}
	$page['breadcrumbs'][] = 'Liste';
	$page['text'] = wrap_template('verbandsliste', $data);
	return $page;
}
