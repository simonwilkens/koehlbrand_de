<?php
/**
 * Brotkrumen-Navigation als dynamischer Block.
 *
 * Dreifacher Nutzen: Orientierung für Leser:innen, interne Verlinkung auf die
 * Rubrik-Archive und – über inc/seo-schema.php – das BreadcrumbList-Markup
 * für die Suchergebnis-Darstellung.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block registrieren. Das Editor-Skript wird von Hand registriert, damit die
 * Abhängigkeiten (wp-blocks etc.) stimmen – ohne Build-Schritt gibt es keine
 * index.asset.php, aus der WordPress sie sonst ableiten würde.
 */
function koehlbrand_register_breadcrumbs_block() {
	wp_register_script(
		'koehlbrand-breadcrumbs-editor',
		get_theme_file_uri( 'blocks/breadcrumbs/index.js' ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ),
		wp_get_theme()->get( 'Version' ),
		true
	);

	register_block_type(
		get_theme_file_path( 'blocks/breadcrumbs' ),
		array( 'render_callback' => 'koehlbrand_render_breadcrumbs' )
	);
}
add_action( 'init', 'koehlbrand_register_breadcrumbs_block' );

/**
 * Frontend-Ausgabe.
 */
function koehlbrand_render_breadcrumbs( $attributes = array(), $content = '', $block = null ) {
	if ( is_front_page() ) {
		return ''; // Auf der Startseite wäre der Pfad nur "Start".
	}

	$crumbs = koehlbrand_breadcrumb_items();

	if ( count( $crumbs ) < 2 ) {
		return '';
	}

	$letzter = count( $crumbs ) - 1;
	$teile   = array();

	foreach ( $crumbs as $i => $crumb ) {
		if ( $i === $letzter || '' === $crumb['url'] ) {
			$teile[] = sprintf(
				'<span class="koehlbrand-breadcrumbs__current" aria-current="page">%s</span>',
				esc_html( $crumb['name'] )
			);
		} else {
			$teile[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $crumb['url'] ),
				esc_html( $crumb['name'] )
			);
		}
	}

	$wrapper = get_block_wrapper_attributes( array( 'class' => 'koehlbrand-breadcrumbs' ) );

	return sprintf(
		'<nav %s aria-label="Brotkrumen-Navigation">%s</nav>',
		$wrapper,
		implode( '<span class="koehlbrand-breadcrumbs__sep" aria-hidden="true">/</span>', $teile )
	);
}
