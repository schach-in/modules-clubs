<?php

/**
 * clubs module
 * make statistics for the club database
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @author Falco Nogatz <nogatz@gmail.com>
 * @copyright Copyright © 2016-2021 Gustaf Mossakowski
 * @copyright Copyright © Falco Nogatz
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Generierung von Statistiken für die Vereinsdatenbank
 *
 * @param void
 * @return array $page
 */
function mod_clubs_make_clubstats() {
	global $zz_setting;
	$zz_setting['cache'] = false;

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		$data['request'] = true;
		$page['text'] = wrap_template('clubstats', $data);
		return $page;
	}
	
	$sql = 'DROP TABLE IF EXISTS vereinsdb_stats';
	$result = wrap_db_query($sql);
	if (!$result) {
		wrap_error('Fehler beim Löschen der bestehenden Vereinsstatistiken.', E_USER_ERROR);
	}

	$sql = 'CREATE TABLE vereinsdb_stats AS SELECT contact_id
			, COUNT(DISTINCT Mgl_Nr) AS members
			, SUM(IF(Geschlecht = "W",1,0)) AS members_female
			, SUM(IF(Geburtsjahr >= YEAR(NOW() -INTERVAL 25 YEAR), 1, 0)) AS members_u25
			, SUM(IF(Status = "P", 1, 0)) AS members_passive
			, ROUND(AVG(Geburtsjahr)) AS avg_byear
			, ROUND(SUM(IF(DWZ != 0, DWZ, 0)) / IF(SUM(IF(DWZ != 0, 1, 0)), SUM(IF(DWZ != 0, 1, 0)), 1)) AS avg_rating
		FROM dwz_spieler
		LEFT JOIN contacts_identifiers
			ON IF(SUBSTRING(dwz_spieler.ZPS, 4, 2) = "00", SUBSTRING(dwz_spieler.ZPS, 1, 3), dwz_spieler.ZPS) = contacts_identifiers.identifier
			AND contacts_identifiers.current = "yes"
			AND contacts_identifiers.identifier_category_id = %d
		GROUP BY contact_id';
	$sql = sprintf($sql, wrap_category_id('kennungen/zps'));
	$result = wrap_db_query($sql);
	if (!$result) {
		wrap_error('Fehler beim Erstellen der Vereinsstatistiken.', E_USER_ERROR);
	}
	$sql = 'ALTER TABLE `vereinsdb_stats` ADD UNIQUE `contact_id` (`contact_id`)';
	wrap_db_query($sql);

	$data['done'] = true;
	$page['text'] = wrap_template('clubstats', $data);
	return $page;
}
