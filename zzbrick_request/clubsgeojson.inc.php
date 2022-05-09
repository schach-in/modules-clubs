<?php

/**
 * clubs module
 * output GeoJSON data for organisations
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2015-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * output GeoJSON data for clubs, schools, etc.
 *
 * @param array $params
 * @return array $page
 */
function mod_clubs_clubsgeojson($params, $settings = []) {
	$last = end($params);
	$last = key($params);
	if (str_ends_with($params[$last], '.geojson'))
		$params[$last] = substr($params[$last], 0, -8);

	$source = brick_request_data('clubs', $params, $settings);
	if (!$source['coordinates']) return false;

	$page['content_type'] = 'geojson';
	$page['query_strings'][] = 'q';
	$page['ending'] = 'none';
	$page['headers']['filename'] = sprintf('%s.geojson', $params[0]);

	$conditional_properties = [
		'members', 'u25', 'female', 'avg_age', 'avg_rating'
	];
	$data = [];
	$data['type'] = 'FeatureCollection';
	foreach ($source['coordinates'] as $index => $coordinate) {
		$properties = [
			'org' => $coordinate['title'],
			'identifier' => $coordinate['identifier'],
			'category' => $coordinate['category'],
			'awards' => intval($coordinate['auszeichnungen']),
		];
		foreach ($conditional_properties as $prop) {
			if (!$coordinate[$prop]) continue;
			$properties[$prop] = intval($coordinate[$prop]);
		}
		$data['features'][] = [
			'type' => 'Feature',
			'id' => $index,
			'properties' => $properties,
			'geometry' => [
				'type' => 'Point',
				'coordinates' => [
					floatval($coordinate['y_longitude']),
					floatval($coordinate['x_latitude'])
				]
			]
		];
	}
	$page['text'] = json_encode($data);
	return $page;
}
