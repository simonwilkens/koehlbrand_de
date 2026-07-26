/**
 * Editor-Darstellung des Werbeplatzes.
 *
 * Wie bei den Brotkrumen bewusst ohne Build-Schritt: der Block wird
 * serverseitig gerendert, im Editor genügt eine Vorschau mit den Reglern für
 * Platz, Intervall und Sticky. Die Blockdefinition kommt aus block.json.
 */
( function ( blocks, element, blockEditor, components ) {
	'use strict';

	var el = element.createElement;

	// Muss zu koehlbrand_ad_slots() in inc/ads.php passen.
	var PLAETZE = [
		{ value: 'header', label: 'Banner unter dem Header' },
		{ value: 'sidebar', label: 'Sidebar (sticky)' },
		{ value: 'in-content', label: 'Im Artikel' },
		{ value: 'end-of-article', label: 'Artikelende' },
		{ value: 'in-feed', label: 'Zwischen den Artikel-Karten' },
		{ value: 'anchor', label: 'Anchor (nur mobil)' }
	];

	function label( slot ) {
		for ( var i = 0; i < PLAETZE.length; i++ ) {
			if ( PLAETZE[ i ].value === slot ) {
				return PLAETZE[ i ].label;
			}
		}
		return slot;
	}

	blocks.registerBlockType( 'koehlbrand/ad-slot', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var blockProps = blockEditor.useBlockProps( {
				className: 'koehlbrand-ad koehlbrand-ad--' + attributes.slot
			} );

			var hinweis = attributes.interval > 0
				? 'Erscheint nach jeder ' + attributes.interval + '. Karte'
				: 'Platz wird in voller Höhe reserviert';

			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: 'Werbeplatz' },
						el( components.SelectControl, {
							label: 'Platz',
							value: attributes.slot,
							options: PLAETZE,
							onChange: function ( wert ) {
								setAttributes( { slot: wert } );
							}
						} ),
						el( components.RangeControl, {
							label: 'Nur nach jeder n-ten Karte (0 = immer)',
							help: 'Nur sinnvoll innerhalb einer Beitragsschleife.',
							value: attributes.interval,
							min: 0,
							max: 12,
							onChange: function ( wert ) {
								setAttributes( { interval: wert || 0 } );
							}
						} ),
						el( components.ToggleControl, {
							label: 'Beim Scrollen mitlaufen (sticky)',
							checked: !! attributes.sticky,
							onChange: function ( wert ) {
								setAttributes( { sticky: wert } );
							}
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( 'span', { className: 'koehlbrand-ad__label' }, 'Anzeige' ),
					el(
						'div',
						{ className: 'koehlbrand-ad__inner' },
						el(
							'span',
							{ className: 'koehlbrand-ad__placeholder' },
							label( attributes.slot ),
							el( 'br' ),
							el( 'small', null, hinweis )
						)
					)
				)
			);
		},

		// Dynamischer Block – die Ausgabe erzeugt PHP.
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components );
