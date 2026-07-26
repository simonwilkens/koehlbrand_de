/**
 * Editor-Darstellung der Artikel-Navigation.
 *
 * Serverseitig gerendert – welche Nachbarartikel es gibt, weiß erst das
 * Frontend, im Editor steht deshalb eine statische Vorschau.
 */
( function ( blocks, element, blockEditor ) {
	'use strict';

	var el = element.createElement;

	function spalte( richtung, pfeil, label, titel ) {
		return el(
			'span',
			{ className: 'koehlbrand-postnav__link koehlbrand-postnav__link--' + richtung },
			el(
				'span',
				{ className: 'koehlbrand-postnav__label' },
				el( 'span', { className: 'koehlbrand-postnav__arrow' }, pfeil ),
				label
			),
			el( 'span', { className: 'koehlbrand-postnav__title' }, titel )
		);
	}

	blocks.registerBlockType( 'koehlbrand/post-nav', {
		edit: function () {
			var blockProps = blockEditor.useBlockProps( {
				className: 'koehlbrand-postnav',
				style: { opacity: 0.7 }
			} );

			return el(
				'nav',
				blockProps,
				spalte( 'prev', '←', 'Vorheriger Artikel', 'Titel des älteren Beitrags' ),
				spalte( 'next', '→', 'Nächster Artikel', 'Titel des neueren Beitrags' )
			);
		},

		// Dynamischer Block – die Ausgabe erzeugt PHP.
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
