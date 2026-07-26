<?php
/**
 * Lesezeit als dynamischer Block.
 *
 * Eine Lesezeit-Angabe in der Autorenzeile ist die billigste Maßnahme gegen
 * Absprünge: Sie beantwortet vor dem Scrollen die Frage „lohnt sich das
 * jetzt?“. Der Wert wird bei jedem Aufruf aus dem Beitragstext gerechnet, nicht
 * gespeichert – so bleibt er auch dann richtig, wenn die REST-API-Pipeline
 * einen Artikel später umschreibt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lesegeschwindigkeit in Wörtern pro Minute.
 *
 * 200 ist der übliche Ansatz für deutschsprachige Online-Texte; deutsche
 * Komposita lesen sich langsamer als englische Sätze, weshalb die häufig
 * zitierten 230–250 wpm hier zu optimistisch wären.
 */
function koehlbrand_words_per_minute() {
	return max( 1, (int) apply_filters( 'koehlbrand_words_per_minute', 200 ) );
}

/**
 * Wortzahl eines Beitrags.
 *
 * `strip_tags()` räumt die Block-Kommentare (`<!-- wp:paragraph -->`) gleich
 * mit weg, weil HTML-Kommentare für die Funktion nichts anderes sind als
 * Markup. Gezählt wird über `\S+`, nicht mit `str_word_count()` – letzteres
 * zerlegt „Köhlbrandbrücke“ an den Umlauten in drei Wörter.
 */
function koehlbrand_word_count( $post = null ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return 0;
	}

	return (int) preg_match_all( '/\S+/u', wp_strip_all_tags( $post->post_content ) );
}

/**
 * Lesezeit in vollen Minuten, mindestens 1.
 */
function koehlbrand_reading_time( $post = null ) {
	$minuten = (int) ceil( koehlbrand_word_count( $post ) / koehlbrand_words_per_minute() );

	return max( 1, $minuten );
}

/**
 * Beschrifteter Text, z. B. „4 Min. Lesezeit“.
 */
function koehlbrand_reading_time_text( $post = null ) {
	return sprintf(
		/* translators: %d: Lesezeit in Minuten. */
		__( '%d Min. Lesezeit', 'koehlbrand' ),
		koehlbrand_reading_time( $post )
	);
}

/**
 * Block registrieren. Editor-Skript von Hand, weil es ohne Build-Schritt keine
 * index.asset.php mit den Abhängigkeiten gibt (wie bei den Brotkrumen).
 */
function koehlbrand_register_reading_time_block() {
	wp_register_script(
		'koehlbrand-reading-time-editor',
		get_theme_file_uri( 'blocks/reading-time/index.js' ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ),
		wp_get_theme()->get( 'Version' ),
		true
	);

	register_block_type(
		get_theme_file_path( 'blocks/reading-time' ),
		array( 'render_callback' => 'koehlbrand_render_reading_time_block' )
	);
}
add_action( 'init', 'koehlbrand_register_reading_time_block' );

/**
 * Frontend-Ausgabe.
 *
 * `postId` kommt aus dem Kontext, damit der Block auch im Aufmacher und in den
 * Karten der Startseite den jeweiligen Beitrag meint und nicht die Seite, auf
 * der er steht.
 */
function koehlbrand_render_reading_time_block( $attributes = array(), $content = '', $block = null ) {
	$post_id = $block->context['postId'] ?? get_the_ID();

	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		return '';
	}

	$wrapper = get_block_wrapper_attributes( array( 'class' => 'koehlbrand-reading-time' ) );

	return sprintf(
		'<span %s>%s</span>',
		$wrapper,
		esc_html( koehlbrand_reading_time_text( $post_id ) )
	);
}
