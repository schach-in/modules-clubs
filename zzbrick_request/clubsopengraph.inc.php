<?php

/**
 * clubs module
 * output opengraph image for organisations
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Falco Nogatz <fnogatz@gmail.com>
 * @copyright Copyright © 2023 Falco Nogatz
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * output opengraph image for clubs, schools, etc.
 *
 * @param array $params
 * @return array $page
 */
function mod_clubs_clubsopengraph($params, $settings = []) {
	if (count($params) !== 2) return false;

	$sql = 'SELECT org.contact_id, org.contact
			, YEAR(org.end_date) AS end_date, org.start_date
			, ok.identifier AS zps_code
			, members, members_female, members_u25, (YEAR(CURDATE()) - avg_byear) AS avg_age, avg_rating
			, members_passive
			, SUBSTRING_INDEX(categories.path, "/", -1) AS category
			, (SELECT COUNT(*) FROM contacts members WHERE members.mother_contact_id = org.contact_id) AS member_orgs
			, categories.parameters
			, countries.country, countries.identifier AS country_identifier
		FROM contacts org
		LEFT JOIN categories
			ON org.contact_category_id = categories.category_id
		LEFT JOIN vereinsdb_stats USING (contact_id)
		LEFT JOIN contacts_identifiers ok
			ON ok.contact_id = org.contact_id
			AND ok.identifier_category_id = %d
			AND NOT ISNULL(ok.current)
		LEFT JOIN countries
			ON org.country_id = countries.country_id
		WHERE org.identifier = "%s"
		AND categories.parameters LIKE "%%&clubpage=1%%"
	';
	$sql = sprintf($sql
		, wrap_category_id('identifiers/zps')
		, wrap_db_escape($params[0])
	);
	$org = wrap_db_fetch($sql);
	if (!$org) {
		return brick_format('%%% request clubs '.$params[0].' %%%');
	}
	if (!in_array($org['category'], ['verein', 'schachabteilung'])) {
		// as of now, we only support clubs
		return false;
	}

	$page['content_type'] = 'png';
	$img = imagecreatefrompng(__DIR__ . '/../layout/opengraph/verein.png');

	// settings
	$image_width = 1200;
	$darkColor = imagecolorallocate($img, 47, 54, 61);
	$lightColor = imagecolorallocate($img, 150, 156, 164);
	$boldFont = __DIR__ . '/../../../themes/chess16/layout/fonts/FiraSans-Bold.ttf';
	$regularFont = __DIR__ . '/../../../themes/chess16/layout/fonts/FiraSans-Regular.ttf';
	$title_fontSize = 99;
	$subtitle_fontSize = 38;
	$stats_fontSize = 38;
	$label_fontSize = 32;

	// title & subtitle
	$title = $org['contact'];
	$title_leftPad = 74;
	$title_rightPad = 72;
	do {
		$title_fontSize = $title_fontSize - 1;
		$title_box = imagettfbbox($title_fontSize, 0, $boldFont, $title);
		$title_width = $title_box[2] - $title_box[0];
		$title_height = $title_box[1] - $title_box[7];
	} while ($title_width > $image_width - $title_leftPad - $title_rightPad);
	imagettftext($img, $title_fontSize, 0, $title_leftPad, 490, $darkColor, $boldFont, $title);
	imagettftext($img, $subtitle_fontSize, 0, $title_leftPad + floor($title_fontSize/40), 555, $darkColor, $regularFont, $org['country']);

	// stats
	imagettftext($img, $label_fontSize, 0, 130, 160, $lightColor, $regularFont, 'Mitglieder');
	imagettftext($img, $stats_fontSize, 0, 130, 118, $darkColor, $boldFont, $org['members']);
	imagettftext($img, $label_fontSize, 0, 572, 160, $lightColor, $regularFont, '∅ Alter');
	imagettftext($img, $stats_fontSize, 0, 572, 118, $darkColor, $boldFont, $org['avg_age']);
	imagettftext($img, $label_fontSize, 0, 130, 300, $lightColor, $regularFont, 'Jugendliche');
	imagettftext($img, $stats_fontSize, 0, 130, 258, $darkColor, $boldFont, $org['members_u25']);
	imagettftext($img, $label_fontSize, 0, 572, 300, $lightColor, $regularFont, '∅ DWZ');
	imagettftext($img, $stats_fontSize, 0, 572, 258, $darkColor, $boldFont, $org['avg_rating']);

	// output png
	ob_start();
	imagepng($img);
	$page['text'] = ob_get_clean();
	imagedestroy($img);

	return $page;
}
