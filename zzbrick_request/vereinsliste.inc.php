<?php

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2016-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Ausgabe einer Vereinsliste pro Verband


function mod_clubs_vereinsliste($params) {
	global $zz_setting;
	if (count($params) !== 1) return false;

	$sql = 'SELECT org_id, organisation, mutter_org_id, 0 AS _level
			, organisationen.kennung, website, organisationen.beschreibung
		FROM organisationen
		LEFT JOIN categories USING (category_id)
		WHERE organisationen.kennung = "%s"
		AND SUBSTRING_INDEX(categories.path, "/", -1) = "verband"';
	$sql = sprintf($sql, wrap_db_escape($params[0]));
	$verband = wrap_db_fetch($sql);
	if ($verband) {
		$condition = sprintf('mutter_org_id = %d
			AND SUBSTRING_INDEX(categories.path, "/", -1) = "verein" 
			AND ISNULL(aufloesung)', $verband['org_id']);
		$top = $verband;
		$categories = false;
	} else {
		$categories = clubs_from_category($params[0]);
		if (!$categories) return false;
		$category = reset($categories);
		$top = $category;
		$top['organisation'] = $category['category'];
		$condition = sprintf('auszeichnung_category_id IN (%s)', implode(',', array_keys($categories)));
	}
	$top['members'] = 0;
	$top['members_u25'] = 0;
	$top['members_female'] = 0;
	
	$sql = 'SELECT org_id, organisation, organisationen.kennung
			, organisationen_kennungen.identifier AS zps_code
			, members, members_female, members_u25
			, members_u25/members AS anteil_members_u25
			, members_female/members AS anteil_members_female
			, IF((SELECT COUNT(main_contact_id) FROM organisationen_orte
				WHERE organisationen_orte.org_id = organisationen.org_id AND organisationen_orte.published = "yes"), "ja", "nein") AS spielort
			, 1 AS _level
			, aufloesung
		FROM organisationen
		LEFT JOIN categories USING (category_id)
		LEFT JOIN organisationen_kennungen USING (org_id)
		LEFT JOIN vereinsdb_stats USING (org_id)
		LEFT JOIN auszeichnungen USING (org_id)
		WHERE %s
		ORDER BY organisationen_kennungen.identifier, organisationen.kennung';
	$sql = sprintf($sql, $condition);
	$data['vereine'] = wrap_db_fetch($sql, 'org_id');
	if (!$data['vereine']) return false;
	
	if ($categories) {
		$sql = 'SELECT auszeichnung_id, org_id, dauer_von, dauer_bis, anzeigename
			FROM auszeichnungen
			WHERE org_id IN (%s)
			AND %s
			ORDER BY dauer_von ASC';
		$sql = sprintf($sql, implode(',', array_keys($data['vereine'])), $condition);
		$auszeichnungen = wrap_db_fetch($sql, ['org_id', 'auszeichnung_id']);
		foreach ($auszeichnungen as $org_id => $auszeichnungen_pro_org) {
			$anzeigenamen = [];
			foreach ($auszeichnungen_pro_org as $auszeichnung) {
				$anzeigenamen[$auszeichnung['anzeigename']]['anzeigename'] = $auszeichnung['anzeigename'];
			}
			$data['vereine'][$org_id]['anzeigenamen'] = array_values($anzeigenamen); 
			$data['vereine'][$org_id]['auszeichnungen'] = $auszeichnungen_pro_org;
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
		$data['parent_orgs'] = clubs_parent_orgs($top['org_id']);
	}
	if (!empty($top['beschreibung'])) $data['beschreibung'] = $top['beschreibung'];
	if (!empty($top['website'])) $data['website'] = $top['website'];
	
	if ($verband) {
		$page['title'] = $verband['organisation'];
		$page['breadcrumbs'][] = $verband['organisation'];
	} else {
		$page['title'] = $category['category'];
		$page['breadcrumbs'][] = $category['category'];
	}
	$page['text'] = wrap_template('vereinsliste', $data);
	return $page;
}
