/**
 * Editor-Darstellung des Bildnachweises.
 *
 * Serverseitig gerendert – im Editor genügt ein Beispielwert. Die
 * Blockdefinition kommt aus block.json (PHP-seitig registriert), hier wird nur
 * "edit" ergänzt.
 */
( function ( blocks, element, blockEditor ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'koehlbrand/featured-credit', {
		edit: function () {
			var blockProps = blockEditor.useBlockProps( {
				className: 'koehlbrand-featured-credit',
				style: { opacity: 0.7 }
			} );

			return el(
				'p',
				blockProps,
				'Foto: Name des:der Fotograf:in (CC BY-SA 3.0)'
			);
		},

		// Dynamischer Block – die Ausgabe erzeugt PHP.
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
