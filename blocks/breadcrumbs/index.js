/**
 * Editor-Darstellung der Brotkrumen-Navigation.
 *
 * Bewusst ohne Build-Schritt: der Block wird serverseitig gerendert, im Editor
 * genügt eine statische Vorschau. Die Blockdefinition selbst kommt aus
 * block.json (serverseitig registriert), hier wird nur "edit" ergänzt.
 */
( function ( blocks, element, blockEditor ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'koehlbrand/breadcrumbs', {
		edit: function () {
			var blockProps = blockEditor.useBlockProps( {
				className: 'koehlbrand-breadcrumbs',
				style: { opacity: 0.7 }
			} );

			return el(
				'nav',
				blockProps,
				'Start',
				el( 'span', { className: 'koehlbrand-breadcrumbs__sep' }, '/' ),
				'Rubrik',
				el( 'span', { className: 'koehlbrand-breadcrumbs__sep' }, '/' ),
				'Titel der aktuellen Seite'
			);
		},

		// Dynamischer Block – die Ausgabe erzeugt PHP.
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
