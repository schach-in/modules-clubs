/**
 * clubs module
 * SQL for installation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


CREATE TABLE `wochentermine` (
  `wochentermin_id` int unsigned NOT NULL AUTO_INCREMENT,
  `org_id` int unsigned NOT NULL,
  `wochentag` set('Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Sonnabend','Sonntag') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `uhrzeit_beginn` time NOT NULL,
  `uhrzeit_ende` time DEFAULT NULL,
  `wochentermin_category_id` int unsigned NOT NULL,
  `beschreibung` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `place_contact_id` int unsigned DEFAULT NULL,
  `woche_im_monat` set('1','2','3','4','5','letzte') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL,
  `oeffentlich` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT 'nein',
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`wochentermin_id`),
  KEY `org_id` (`org_id`),
  KEY `wochentermin_kategorie_id` (`wochentermin_category_id`),
  KEY `ort_id` (`place_contact_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'organisationen', 'org_id', (SELECT DATABASE()), 'wochentermine', 'wochentermin_id', 'org_id', 'delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'categories', 'category_id', (SELECT DATABASE()), 'wochentermine', 'wochentermin_id', 'wochentermin_category_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'contacts', 'contact_id', (SELECT DATABASE()), 'wochentermine', 'wochentermin_id', 'place_contact_id', 'no-delete');
