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
 * @copyright Copyright © 2016-2026 Gustaf Mossakowski
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
	ini_set('max_execution_time', 0);

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

	mod_clubs_make_clubstats_path_website();
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
 * For each candidate, skip nuLiga HTTP when nuliga_clubs already has the ZPS
 * (LEFT JOIN in the query); otherwise verify on nuLiga (individual ZPS lookup).
 * When the club is gone from nuLiga, set contacts.end_date from the last memberstats
 * snapshot month and report the result in the admin mail.
 *
 */
function mod_clubs_make_clubstats_deleted() {
	wrap_include('nuliga', 'ratings');
	$sql = 'SELECT DISTINCT dwz_vereine.ZPS AS code, dwz_vereine.Vereinname AS club
		, nc.nuliga_club_id
		FROM dwz_vereine
		LEFT JOIN dwz_spieler USING (ZPS)
		LEFT JOIN nuliga_clubs nc ON nc.zps = dwz_vereine.ZPS
		WHERE ISNULL(dwz_spieler.PID)';
	$clubs = wrap_db_fetch($sql, 'code');
	if (!$clubs) return false;

	$data = [];
	foreach ($clubs as $club)
		$data[] = mod_clubs_make_clubstats_deleted_club($club);

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

/**
 * process one dissolved club candidate
 *
 * @param array $club code, club, optional nuliga_club_id from query
 * @return array mail row
 */
function mod_clubs_make_clubstats_deleted_club($club) {
	$line = ['code' => $club['code'], 'club' => $club['club']];

	if (!empty($club['nuliga_club_id'])) {
		$line['still_on_nuliga'] = true;
		return $line;
	}

	$zps = mf_ratings_zps_normalize($club['code']);
	if (mf_ratings_nuliga_fetch_club_by_zps($zps)) {
		$line['still_on_nuliga'] = true;
		return $line;
	}

	$contact = mod_clubs_make_clubstats_contact_by_zps($club['code']);
	if (!$contact) {
		$line['no_contact'] = true;
		return $line;
	}
	$line['contact'] = $contact['contact'];

	if ($contact['end_date']) {
		$line['already_closed'] = true;
		$line['end_date'] = $contact['end_date'];
		return $line;
	}

	$last_snapshot = mod_clubs_make_clubstats_last_memberstats_snapshot($club['code']);
	if (!$last_snapshot) {
		$line['no_memberstats'] = true;
		return $line;
	}

	$end_date = substr($last_snapshot, 0, 7).'-00';
	zzform_update('contacts', [
		'contact_id' => $contact['contact_id'],
		'end_date' => $end_date,
	]);
	$line['closed_end_date'] = $end_date;
	return $line;
}

/**
 * schach.in club contact for a dwz_vereine ZPS code
 *
 * @param string $code
 * @return array|null contact_id, contact, end_date
 */
function mod_clubs_make_clubstats_contact_by_zps($code) {
	$sql = 'SELECT c.contact_id, c.contact, c.end_date
		FROM contacts c
		INNER JOIN contacts_identifiers ci ON ci.contact_id = c.contact_id
		WHERE ci.identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
		AND ci.current = "yes"
		AND c.contact_category_id IN (
			/*_ID categories contact/club _*/,
			/*_ID categories contact/chess-department _*/
		)
		AND ci.identifier = IF(
			FIND_IN_SET(SUBSTRING("%s", 1, 1), "/*_SETTING ratings_dsb_federations_are_clubs _*/"),
			SUBSTRING("%s", 1, 3),
			IF(SUBSTRING("%s", 4, 2) = "00", SUBSTRING("%s", 1, 3), "%s")
		)';
	$sql = sprintf($sql
		, wrap_db_escape($code)
		, wrap_db_escape($code)
		, wrap_db_escape($code)
		, wrap_db_escape($code)
		, wrap_db_escape($code)
	);
	return wrap_db_fetch($sql);
}

/**
 * last memberstats snapshot date for a club code
 *
 * @param string $code dwz_vereine ZPS
 * @return string|null YYYY-MM-DD
 */
function mod_clubs_make_clubstats_last_memberstats_snapshot($code) {
	$codes = array_unique(array_filter([
		$code,
		mf_ratings_zps_normalize($code),
	]));
	$escaped = array_map('wrap_db_escape', $codes);
	$sql = 'SELECT MAX(snapshot_date) AS last_snapshot
		FROM memberstats
		WHERE club_code IN ("'.implode('","', $escaped).'")';
	$line = wrap_db_fetch($sql);
	if (empty($line['last_snapshot']))
		return null;
	return $line['last_snapshot'];
}

/**
 * resolve paths from admin website when it differs from this hostname
 *
 */
function mod_clubs_make_clubstats_path_website() {
	if (!wrap_setting('admin_hostname')) return false;
	$admin_hostname = wrap_url_dev_remove(wrap_setting('admin_hostname'));
	if ($admin_hostname === wrap_url_dev_remove(wrap_setting('hostname')))
		return false;
	$website_id = wrap_id('websites', $admin_hostname);
	if (!$website_id) return false;
	wrap_setting('path_website_id', $website_id);
}
