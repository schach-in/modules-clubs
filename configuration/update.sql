/**
 * clubs module
 * SQL updates
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023-2024, 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/* 2023-03-22-1 */	UPDATE _settings SET setting_key = 'club_stats_min_members' WHERE setting_key = 'clubs_statistik_min_mitglieder';
/* 2023-03-28-1 */	UPDATE _settings SET setting_key = 'clubs_stats_min_members' WHERE setting_key = 'club_stats_min_members';
/* 2024-03-14-1 */	ALTER TABLE `wochentermine` ADD INDEX `place_contact_id` (`place_contact_id`), DROP INDEX `ort_id`;
/* 2026-03-18-1 */	DELETE FROM _settings WHERE setting_key = 'clubs_edit_path';
/* 2026-03-18-2 */	DELETE FROM _settings WHERE setting_key = 'clubs_geojson_path';
/* 2026-08-10-1 */	UPDATE categories SET parameters = REPLACE(parameters, '&weekly_events=', '&clubs_weekly_events=') WHERE parameters LIKE '%&weekly_events=%';
/* 2026-08-10-2 */	UPDATE categories SET parameters = REPLACE(parameters, '&clubpage=', '&clubs_public_page=') WHERE parameters LIKE '%&clubpage=%';
/* 2026-08-10-3 */	UPDATE categories SET parameters = REPLACE(parameters, '&foundation_date=', '&contacts_start_date=') WHERE parameters LIKE '%&foundation_date=%';
/* 2026-08-13-1 */	UPDATE categories SET parameters = REPLACE(parameters, '&organisation=1', '&clubs_map=1') WHERE path LIKE 'tags/%' AND parameters LIKE '%&organisation=1%';
