/*
 * clubs module
 * filter functions for maps
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/clubs
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * create filter controls
 */
function clubsCreateFilterControls() {
	var filtersDiv = document.getElementById('filters');
	filterProperties.forEach(prop => {
		var slider = document.createElement('input');
		slider.type = 'range';
		slider.id = prop.name + 'Slider';
		slider.min = 0;
		slider.max = prop.max;
		slider.value = 0;
		slider.step = prop.step;

		var label = document.createElement('label');
		label.htmlFor = prop.name + 'Slider';
		label.innerHTML = prop.label + ': <span id="' + prop.name + 'Value">0</span>';

		filtersDiv.appendChild(label);
		filtersDiv.appendChild(slider);

		slider.addEventListener('input', clubsFilterMap);
	});
}

/**
 * filter map
 */
function clubsFilterMap() {
	if (geoJsonLayer && geojsonData) {
		markers.clearLayers();
		var filteredData = {
			type: "FeatureCollection",
			features: geojsonData.features.filter(feature => {
				return filterProperties.every(prop => {
					var value = parseInt(document.getElementById(prop.name + 'Slider').value);
					document.getElementById(prop.name + 'Value').textContent = value;
					return feature.properties[prop.name] >= value;
				});
			})
		};
		geoJsonLayer.clearLayers();
		geoJsonLayer.addData(filteredData);
		markers.addLayer(geoJsonLayer);
	}
}
