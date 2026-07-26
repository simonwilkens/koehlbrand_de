<?php
/**
 * Artikel-Navigation (vorheriger/nächster Beitrag) als dynamischer Block.
 *
 * Am Artikelende steht sonst nur der Werbeplatz und darunter das Ende der
 * Seite. Zwei benannte Nachbarartikel geben dem Lesefluss eine Fortsetzung,
 * ohne dass jemand zur Startseite zurück muss – und sie verketten die Artikel
 * einer Rubrik untereinander, was auch dem Crawler hilft.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nachbarbeitrag holen: bevorzugt aus derselben Rubrik, sonst chronologisch.
 *
 * Die Einschränkung auf die Rubrik hält die Kette thematisch zusammen. Am
 * Anfang oder Ende einer Rubrik gäbe es dann aber gar keinen Nachbarn – dort
 * greift der chronologische Fallback, damit die Zeile nicht halb leer bleibt.
 *
 * @param bool $vorherig true = älterer Beitrag, false = neuerer.
 */
function koehlbrand_adjacent_post( $vorherig ) {
	$post = get_adjacent_post( true, '', $vorherig, 'category' );

	if ( ! $post instanceof WP_Post ) {
		$post = get_adjacent_post( false, '', $vorherig );
	}

	return $post instanceof WP_Post ? $post : null;
}

/**
 * Block registrieren.
 */
function koehlbrand_register_post_nav_block() {
	wp_register_script(
		'koehlbrand-post-nav-editor',
		get_theme_file_uri( 'blocks/post-nav/index.js' ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ),
		wp_get_theme()->get( 'Version' ),
		true
	);

	register_block_type(
		get_theme_file_path( 'blocks/post-nav' ),
		array( 'render_callback' => 'koehlbrand_render_post_nav_block' )
	);
}
add_action( 'init', 'koehlbrand_register_post_nav_block' );

/**
 * Frontend-Ausgabe.
 */
function koehlbrand_render_post_nav_block( $attributes = array(), $content = '', $block = null ) {
	if ( ! is_singular( 'post' ) ) {
		return '';
	}

	$links = array(
		'prev' => array(
			'post'  => koehlbrand_adjacent_post( true ),
			'label' => __( 'Vorheriger Artikel', 'koehlbrand' ),
			'pfeil' => '←',
		),
		'next' => array(
			'post'  => koehlbrand_adjacent_post( false ),
			'label' => __( 'Nächster Artikel', 'koehlbrand' ),
			'pfeil' => '→',
		),
	);

	if ( ! $links['prev']['post'] && ! $links['next']['post'] ) {
		return '';
	}

	$teile = '';

	foreach ( $links as $richtung => $daten ) {
		if ( ! $daten['post'] ) {
			continue;
		}

		$teile .= sprintf(
			'<a class="koehlbrand-postnav__link koehlbrand-postnav__link--%1$s" href="%2$s" rel="%3$s"><span class="koehlbrand-postnav__label"><span class="koehlbrand-postnav__arrow" aria-hidden="true">%4$s</span>%5$s</span><span class="koehlbrand-postnav__title">%6$s</span></a>',
			esc_attr( $richtung ),
			esc_url( get_permalink( $daten['post'] ) ),
			'prev' === $richtung ? 'prev' : 'next',
			esc_html( $daten['pfeil'] ),
			esc_html( $daten['label'] ),
			esc_html( get_the_title( $daten['post'] ) )
		);
	}

	$wrapper = get_block_wrapper_attributes( array( 'class' => 'koehlbrand-postnav' ) );

	return sprintf(
		'<nav %1$s aria-label="%2$s">%3$s</nav>',
		$wrapper,
		esc_attr__( 'Weitere Artikel', 'koehlbrand' ),
		$teile
	);
}
