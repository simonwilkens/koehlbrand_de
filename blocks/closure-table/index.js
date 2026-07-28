/**
 * Editor-Darstellung der Sperrtermin-Tabelle.
 *
 * Serverseitig gerendert – im Editor genügt ein Beispielwert.
 */
( function ( blocks, element, blockEditor ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'koehlbrand/closure-table', {
		edit: function () {
			var blockProps = blockEditor.useBlockProps( {
				className: 'koehlbrand-closure-table',
				style: { opacity: 0.7 }
			} );

			return el(
				'p',
				blockProps,
				'Tabelle aller angekündigten Vollsperrungen – gespeist aus der Terminliste, ' +
				'dieselbe Quelle wie der Kasten in der Seitenleiste.'
			);
		},

		// Dynamischer Block – die Ausgabe erzeugt PHP.
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor ) );
