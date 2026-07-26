/**
 * Editor-Darstellung der Lesezeit.
 *
 * Serverseitig gerendert – im Editor genügt ein Beispielwert. Die
 * Blockdefinition kommt aus block.json (PHP-seitig registriert), hier wird nur
 * "edit" ergänzt.
 */
( function ( blocks, element, blockEditor ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'koehlbrand/reading-time', {
		edit: function () {
			var blockProps = blockEditor.useBlockProps( {
				className: 'koehlbrand-reading-time',
				style: { opacity: 0.7 }
			} );

			return el( 'span', blockProps, '4 Min. Lesezeit' );
		},

		// Dynamischer Block – die Ausgabe erzeugt PHP.
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
