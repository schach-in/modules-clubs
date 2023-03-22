/**
 * clubs module
 * SQL updates
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/* 2023-03-22-1 */	UPDATE _settings SET setting_key = 'club_stats_min_members' WHERE setting_key = 'clubs_statistik_min_mitglieder';
