<?php

/**
 * clubs module
 * output of a list of clubs per state
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022, 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_clubs_statelist($params, $settings) {
	$sql = 'SELECT country_id, country, identifier
			, (SELECT COUNT(*) FROM contacts
				WHERE contacts.country_id = countries.country_id
				%s) AS contact_count
			, 1 as _level
		FROM countries
		WHERE country_category_id IN (
			/*_ID categories politische-einheiten/staat/bundesland _*/,
			/*_ID categories politische-einheiten/staat/bundesland/teil _*/
		)
		HAVING contact_count > 0
		ORDER BY country';
	$sql = sprintf($sql
		, (!empty($settings['category']) ? sprintf('AND contact_category_id = /*_ID categories contact/%s _*/', $settings['category']) : '')
	);
	$data = wrap_db_fetch($sql, 'country_id');
	
	$page['text'] = wrap_template('statelist', $data);
	return $page;
}
