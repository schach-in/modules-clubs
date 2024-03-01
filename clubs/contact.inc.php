<?php 

/**
 * clubs module
 * contact functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mf_clubs_contact($data, $ids) {
	$sql = 'SELECT contact_id, members, members_female, members_u25, members_passive
			, avg_byear, avg_rating
	    FROM vereinsdb_stats
	    WHERE contact_id IN (%s)';
	$sql = sprintf($sql, implode(',', $ids));
	$stats = wrap_db_fetch($sql, 'contact_id');
	
	foreach ($stats as $contact_id => $stat)
		$data[$contact_id] += $stat;
	
	$data['templates']['contact_5'][] = 'contact-clubstats';
	return $data;
}
