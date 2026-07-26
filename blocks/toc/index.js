/**
 * Editor-Darstellung des Inhaltsverzeichnisses.
 *
 * Serverseitig gerendert – im Editor steht eine statische Vorschau, weil das
 * Verzeichnis erst beim Ausliefern aus dem Beitragstext entsteht.
 */
( function ( blocks, element, blockEditor ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'koehlbrand/toc', {
		edit: function ( props ) {
			var blockProps = blockEditor.useBlockProps( {
				className: 'koehlbrand-toc',
				style: { opacity: 0.7 }
			} );

			var titel = props.attributes.title || 'Inhalt dieses Artikels';

			return el(
				'nav',
				blockProps,
				el(
					'div',
					{ className: 'koehlbrand-toc__box' },
					el( 'span', { className: 'koehlbrand-toc__title' }, titel ),
					el(
						'ol',
						{ className: 'koehlbrand-toc__list' },
						el( 'li', { className: 'koehlbrand-toc__item koehlbrand-toc__item--h2' }, 'Erste Zwischenüberschrift' ),
						el( 'li', { className: 'koehlbrand-toc__item koehlbrand-toc__item--h3' }, 'Unterpunkt' ),
						el( 'li', { className: 'koehlbrand-toc__item koehlbrand-toc__item--h2' }, 'Zweite Zwischenüberschrift' )
					)
				)
			);
		},

		// Dynamischer Block – die Ausgabe erzeugt PHP.
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
