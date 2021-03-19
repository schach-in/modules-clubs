<?php

/**
 * Zugzwang Project
 * make statistics for the club database
 *
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

	$sql = 'CREATE TABLE vereinsdb_stats AS SELECT org_id
			, COUNT(DISTINCT Mgl_Nr) AS members
			, SUM(IF(Geschlecht = "W",1,0)) AS members_female
			, SUM(IF(Geburtsjahr >= YEAR(NOW() -INTERVAL 25 YEAR), 1, 0)) AS members_u25
			, ROUND(AVG(Geburtsjahr)) AS avg_byear
			, ROUND(SUM(IF(DWZ != 0, DWZ, 0)) / IF(SUM(IF(DWZ != 0, 1, 0)), SUM(IF(DWZ != 0, 1, 0)), 1)) AS avg_rating
		FROM dwz_spieler
		LEFT JOIN organisationen_kennungen
			ON IF(SUBSTRING(dwz_spieler.ZPS, 4, 2) = "00", SUBSTRING(dwz_spieler.ZPS, 1, 3), dwz_spieler.ZPS) = organisationen_kennungen.identifier
			AND organisationen_kennungen.current = "yes"
			AND organisationen_kennungen.identifier_category_id = 197
		GROUP BY org_id';
	$result = wrap_db_query($sql);
	if (!$result) {
		wrap_error('Fehler beim Erstellen der Vereinsstatistiken.');
	}
	$sql = 'ALTER TABLE `vereinsdb_stats` ADD UNIQUE `org_id` (`org_id`)';
	wrap_db_query($sql);

	$data['done'] = true;
	$page['text'] = wrap_template('clubstats', $data);
	return $page;
}