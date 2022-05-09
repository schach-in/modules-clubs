<?php

/**
 * clubs module
 * output of a map with all clubs
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2015-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_clubs_vereine($params, $settings = []) {
	global $zz_setting;
	if (count($params) > 2) return false;

	// divert?
	if (end($params) === 'liste') {
		array_pop($params);
		if (empty($params)) $params[0] = 'dsb';
		if ($params[0] === 'twitter')
			return brick_format('%%% request vereinsliste '.$params[0].' %%%');
		return brick_format('%%% request verbandsliste '.$params[0].' %%%');
	}
	if (count($params) > 1) return false;

	if (count($params) AND str_ends_with($params[0], '.geojson')) {
		return brick_format('%%% request clubsgeojson * %%%');
	}

	if ($_SERVER['REQUEST_URI'] === '/' AND empty($_GET)) {
		return wrap_redirect('/deutschland', 307);
	}
	if (empty($params))
		$page['query_strings'][] = 'q';

	// check if lat or lon are both set or not set and if they are numeric values
	// if not numeric, still show output, but send 404 page status
	if (!empty($_GET['lat']) AND empty($_GET['lon'])) return false;
	if (!empty($_GET['lon']) AND empty($_GET['lat'])) return false;
	if (isset($_GET['lat']) AND !is_numeric($_GET['lat'])) {
		$_GET['lat'] = filter_var($_GET['lat'], FILTER_SANITIZE_NUMBER_FLOAT);
		$page['status'] = 404;
	}
	if (isset($_GET['lon']) AND !is_numeric($_GET['lon'])) {
		$_GET['lon'] = filter_var($_GET['lon'], FILTER_SANITIZE_NUMBER_FLOAT);
		$page['status'] = 404;
	}

	$data = brick_request_data('clubs', $params, $settings);
	if (!empty($data['url_ending'])) $page['url_ending'] = $data['url_ending'];

	if (!$data['coordinates']) {
		if (!empty($_GET['q'])) {
			$qs = explode(' ', wrap_db_escape($_GET['q']));
			// Verein direkt?
			$sql = 'SELECT contact_id, identifier
				FROM contacts
				LEFT JOIN categories
					ON contacts.contact_category_id = categories.category_id
				WHERE contact LIKE "%%%s%%"
				AND categories.parameters LIKE "%%&organisation=1%%"
				AND ISNULL(end_date)';
			$sql = sprintf($sql, implode('%', $qs));
			$verein = wrap_db_fetch($sql);
			if (!$verein) {
				$q = wrap_filename($_GET['q'], '', ['-' => '']);
				$sql = 'SELECT contact_id, identifier
				FROM contacts
				LEFT JOIN categories
					ON contacts.contact_category_id = categories.category_id
				WHERE REPLACE(identifier, "-", "") LIKE "%%%s%%"
				AND categories.parameters LIKE "%%&organisation=1%%"
				AND ISNULL(end_date)';
				$sql = sprintf($sql, wrap_db_escape($q));
				$verein = wrap_db_fetch($sql);
			}
			if (!$verein) {
				$change = false;
				foreach ($qs as $index => $qstring) {
					if (strlen($qstring) > 3) continue;
					unset ($qs[$index]);
					$change = true;
				}
				if ($change) {
					$sql = 'SELECT contact_id, identifier
						FROM contacts
						LEFT JOIN categories
							ON contacts.contact_category_id = categories.category_id
						WHERE contact LIKE "%%%s%%"
						AND categories.parameters LIKE "%%&organisation=1%%"
						AND ISNULL(end_date)';
					$sql = sprintf($sql, implode('%', $qs));
					$verein = wrap_db_fetch($sql);
				}
			}
			if ($verein) {
				return wrap_redirect(sprintf('/%s/', $verein['identifier']));
			}
		}
		
		if (!empty($data['federation_with_clubs'])) {
			return wrap_redirect(sprintf('/%s/liste/', $params[0]), 307);
		}
		$page['status'] = 404;
		$data['not_found'] = true;
	}

	if (!empty($data['categories'])) {
		if (count($data['categories']) === 1) {
			$data['links'][] = [
				'url' => '../auszeichnung-und-foerderung/',
				'title' => 'Übersichtskarte: Alle Auszeichnungen und Förderungen'
			];
		} else {
			foreach ($data['categories'] as $category) {
				if (empty($category['auszeichnungen'])) continue;
				$data['links'][] = [
					'url' => '../'.$category['path'].'/',
					'title' => $category['category'].' ('.$category['auszeichnungen'].')'
				];
			}
		}
	}
	
	if (empty($data['q']))
		$data['q'] = isset($_GET['q']) ? $_GET['q'] : false;
	if ($data['q'] === '0') $data['q'] = 0;
	$data['lat'] = isset($_GET['lat']) ? $_GET['lat'] : false;
	$data['lon'] = isset($_GET['lon']) ? $_GET['lon'] : false;
	$data['places'] = count($data['coordinates']);
	if (empty($data['title'])) {
		$data['verbaende'] = !empty($_GET['q']) ? mod_clubs_vereine_verbaende($_GET['q'], $data['coordinates']) : [];
	}
	
	$sql = 'SELECT COUNT(*) FROM contacts
		WHERE contact_category_id IN (%d, %d) AND ISNULL(end_date)';
	$sql = sprintf($sql
		, wrap_category_id('contact/club')
		, wrap_category_id('contact/chess-department')
	);
	$data['vereine'] = wrap_db_fetch($sql, '', 'single value');

	$page['dont_show_h1'] = true;
	if (!empty($data['title'])) {
		$page['title'] = 'Schachvereine: '.$data['title'];
		$page['breadcrumbs'][] = $data['title'];
	} else {
		$page['title'] = 'Schachvereine und Schulschachgruppen';
		if (!empty($params[0])) {
			$page['breadcrumbs'][] = 'Suche: '.wrap_html_escape($params[0]);
		}
	}
	if ($data['q'] OR $data['q'] === '0' OR $data['q'] === 0)
		$page['title'] .= sprintf(': Suche nach »%s«', wrap_html_escape($data['q']));
	if ($data['lat'] AND $data['lon']) $page['title'] .= sprintf(', Koordinaten %s/%s', wrap_latitude($data['lat']), wrap_longitude($data['lon']));
	$page['query_strings'][] = 'lat';
	$page['query_strings'][] = 'lon';
	$page['query_strings'][] = 'embed';
	$page['head'] = wrap_template('clubs-head');
	$page['extra']['body_attributes'] = 'id="map"';
	if (!empty($data['noindex'])) {
		$page['meta'][] = [
			'name' => 'robots', 'content' => 'noindex,follow'
		];
	}
	if (!empty($_GET) AND array_key_exists('embed', $_GET)) {
		$data['embed'] = true;
		$page['extra']['body_attributes'] = 'id="map" class="embed"';
	}
	$page['text'] = wrap_template('vereine', $data);
	return $page;
}

/**
 * Suche nach Verbänden
 *
 * @param string $q
 * @param array $coordinates
 * @return array
 */
function mod_clubs_vereine_verbaende($q, $coordinates) {
	$sql = 'SELECT o.contact_id, o.identifier, o.contact
				, h.contact AS main_contact
				, o.contact_category_id
				, (SELECT COUNT(*) FROM contacts WHERE mother_contact_id = o.contact_id) AS rang
		FROM contacts o
		LEFT JOIN categories
			ON o.contact_category_id = categories.category_id
		LEFT JOIN contacts h
			ON o.mother_contact_id = h.contact_id
		WHERE o.contact LIKE "%%%s%%"
		AND categories.parameters LIKE "%%&organisation=1%%"
		AND ISNULL(o.end_date)
		ORDER BY rang DESC, o.identifier
	';
	$sql = sprintf($sql,
		wrap_db_escape($q)
	);
	$verbaende = wrap_db_fetch($sql, 'contact_id');
	foreach ($coordinates as $spielort) {
		if (in_array($spielort['contact_id'], array_keys($verbaende))) {
			// sind schon auf Karte
			unset($verbaende[$spielort['contact_id']]);
		}
	}
	// zuviele? dann nur Verbände anzeigen
	if (count($verbaende) > 5) {
		foreach ($verbaende as $id => $verband) {
			if ($verband['contact_category_id'] !== wrap_category_id('contact/federation'))
				unset($verbaende[$id]);
		}
	}
	
	return $verbaende;
}
