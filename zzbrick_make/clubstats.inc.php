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
 * @copyright Copyright © 2016-2025 Gustaf Mossakowski
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
	wrap_include('syndication', 'zzwrap');
	wrap_setting('cache', false);

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		$data['request'] = true;
		$page['text'] = wrap_template('clubstats', $data);
		return $page;
	}

	$wait_seconds = 300;
	$lock = wrap_lock('clubstats', 'wait', $wait_seconds);
	if ($lock) {
		$data['wait'] = $wait_seconds;
		$page['text'] = wrap_template('clubstats', $data);
		return $page;
	}
	
	$sql = 'DROP TABLE IF EXISTS clubstats';
	$result = wrap_db_query($sql);
	if (!$result) {
		wrap_error('Fehler beim Löschen der bestehenden Vereinsstatistiken.', E_USER_ERROR);
	}

	$sql = 'CREATE TABLE clubstats AS SELECT contact_id
			, COUNT(DISTINCT Mgl_Nr) AS members
			, SUM(IF(Geschlecht = "W",1,0)) AS members_female
			, SUM(IF(Geburtsjahr >= YEAR(NOW() -INTERVAL 25 YEAR), 1, 0)) AS members_u25
			, SUM(IF(Status = "P", 1, 0)) AS members_passive
			, ROUND(AVG(IF(Geburtsjahr = "0000", NULL, Geburtsjahr))) AS avg_byear
			, ROUND(SUM(IF(DWZ != 0, DWZ, 0)) / IF(SUM(IF(DWZ != 0, 1, 0)), SUM(IF(DWZ != 0, 1, 0)), 1)) AS avg_rating
		FROM dwz_spieler
		LEFT JOIN contacts_identifiers
			ON IF(
				FIND_IN_SET(SUBSTRING(dwz_spieler.ZPS, 1, 1), "/*_SETTING ratings_dsb_federations_are_clubs _*/")
				, SUBSTRING(dwz_spieler.ZPS, 1, 3)
				, IF(SUBSTRING(dwz_spieler.ZPS, 4, 2) = "00"
					, SUBSTRING(dwz_spieler.ZPS, 1, 3)
					, dwz_spieler.ZPS)
				) = contacts_identifiers.identifier
			AND contacts_identifiers.current = "yes"
			AND contacts_identifiers.identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
		GROUP BY contact_id';
	$result = wrap_db_query($sql);
	if (!$result) {
		wrap_error('Fehler beim Erstellen der Vereinsstatistiken.', E_USER_ERROR);
	}
	$sql = 'ALTER TABLE `clubstats` ADD UNIQUE `contact_id` (`contact_id`)';
	wrap_db_query($sql);

	mod_clubs_make_clubstats_new();
	mod_clubs_make_clubstats_deleted();

	$data['done'] = true;
	$page['text'] = wrap_template('clubstats', $data);
	return $page;
}

/**
 * check if there are new clubs
 *
 * read from dwz_spieler instead of dwz_vereine to check if there are already members
 *
 */
function mod_clubs_make_clubstats_new() {
	$sql = 'SELECT DISTINCT ZPS AS code, Vereinname AS club
		FROM dwz_spieler
		LEFT JOIN dwz_vereine USING (ZPS)
		LEFT JOIN contacts_identifiers
			ON IF(
				FIND_IN_SET(SUBSTRING(dwz_spieler.ZPS, 1, 1), "/*_SETTING ratings_dsb_federations_are_clubs _*/")
				, SUBSTRING(dwz_spieler.ZPS, 1, 3)
				, IF(SUBSTRING(dwz_spieler.ZPS, 4, 2) = "00"
					, SUBSTRING(dwz_spieler.ZPS, 1, 3)
					, dwz_spieler.ZPS)
				) = contacts_identifiers.identifier
			AND contacts_identifiers.current = "yes"
			AND contacts_identifiers.identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
		WHERE ISNULL(contact_id)';
	$data = wrap_db_fetch($sql, 'code');
	if (!$data) return false;
	$data = array_values($data); // numerical keys

	$mail = [
		'to' => [
			'name' => wrap_setting('project'),
			'e_mail' => wrap_setting('own_e_mail')
		],
		'message' => wrap_template('clubstats-new-mail', $data)
	];
	$success = wrap_mail($mail);
	if (!$success)
		wrap_error('Unable to send mail: '.json_encode($mail));
}

/**
 * check if there are active clubs with no members
 *
 */
function mod_clubs_make_clubstats_deleted() {
	$sql = 'SELECT DISTINCT ZPS AS code, Vereinname AS club
		FROM dwz_vereine
		LEFT JOIN dwz_spieler USING (ZPS)
		WHERE ISNULL(dwz_spieler.PID)';
	$data = wrap_db_fetch($sql, 'code');
	if (!$data) return false;
	$data = array_values($data); // numerical keys

	$mail = [
		'to' => [
			'name' => wrap_setting('project'),
			'e_mail' => wrap_setting('own_e_mail')
		],
		'message' => wrap_template('clubstats-deleted-mail', $data)
	];
	$success = wrap_mail($mail);
	if (!$success)
		wrap_error('Unable to send mail: '.json_encode($mail));
}
