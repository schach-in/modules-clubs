/**
 * clubs module
 * SQL updates
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/* 2023-03-22-1 */	UPDATE _settings SET setting_key = 'club_stats_min_members' WHERE setting_key = 'clubs_statistik_min_mitglieder';
/* 2023-03-28-1 */	UPDATE _settings SET setting_key = 'clubs_stats_min_members' WHERE setting_key = 'club_stats_min_members';
/* 2024-03-14-1 */	ALTER TABLE `wochentermine` ADD INDEX `place_contact_id` (`place_contact_id`), DROP INDEX `ort_id`;
