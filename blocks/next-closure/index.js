/**
 * Editor-Darstellung des Sperrungs-Kastens.
 *
 * Serverseitig gerendert – im Editor genügt ein Beispielwert. Wichtig für die
 * Redaktion: Im Frontend verschwindet der Kasten, sobald kein künftiger Termin
 * mehr in der Liste steht. Der Hinweis steht deshalb im Editor mit im Block.
 */
( function ( blocks, element, blockEditor ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'koehlbrand/next-closure', {
		edit: function () {
			var blockProps = blockEditor.useBlockProps( {
				className: 'koehlbrand-closure',
				style: { opacity: 0.7 }
			} );

			return el(
				'aside',
				blockProps,
				el( 'p', { className: 'koehlbrand-closure__label' }, 'Nächste Vollsperrung' ),
				el( 'p', { className: 'koehlbrand-closure__bauwerk' }, 'Köhlbrandbrücke' ),
				el( 'p', { className: 'koehlbrand-closure__zeit' }, 'Fr 11.09., 21:00 – Mo 14.09., 05:00' ),
				el( 'p', { className: 'koehlbrand-closure__mehr' }, 'Ohne künftigen Termin wird hier nichts ausgegeben.' )
			);
		},

		// Dynamischer Block – die Ausgabe erzeugt PHP.
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor ) );
