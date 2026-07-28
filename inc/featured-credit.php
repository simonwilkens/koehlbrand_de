<?php
/**
 * Bildnachweis zum Beitragsbild als dynamischer Block.
 *
 * Warum es diesen Block braucht: `wp:post-featured-image` rendert keine
 * Bildunterschrift. Die Pipeline schreibt den Credit zwar in die Caption des
 * Medienobjekts, sichtbar war er dort aber nur in der Mediathek – und eine
 * Namensnennung, die nur im Backend steht, ist für CC-BY und CC-BY-SA keine
 * Namensnennung. Solange dieser Block fehlte, durften Beitragsbilder deshalb
 * ausschließlich aus Quellen ohne Attributionspflicht kommen.
 *
 * Der Credit kommt aus der Caption (`post_excerpt`) des Anhangs, nicht aus
 * einem eigenen Meta-Feld: Das ist das Feld, das WordPress selbst für
 * Bildunterschriften vorsieht, es lässt sich in der Mediathek pflegen, und die
 * Pipeline füllt es bereits.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credit-Text zum Beitragsbild eines Beitrags.
 *
 * Rückgabe: Roh-HTML aus der Caption (WordPress erlaubt dort Links – bei
 * CC-Lizenzen ist der Link auf Urheber und Lizenz Teil der Auflage), oder ein
 * leerer String, wenn kein Beitragsbild oder keine Caption existiert.
 */
function koehlbrand_featured_image_credit( $post = null ) {
	$post_id = get_post( $post )->ID ?? 0;

	if ( ! $post_id || ! has_post_thumbnail( $post_id ) ) {
		return '';
	}

	$anhang = get_post( get_post_thumbnail_id( $post_id ) );

	if ( ! $anhang ) {
		return '';
	}

	return trim( (string) $anhang->post_excerpt );
}

/**
 * Block registrieren. Editor-Skript von Hand, weil es ohne Build-Schritt keine
 * index.asset.php mit den Abhängigkeiten gibt (wie bei Lesezeit und Brotkrumen).
 */
function koehlbrand_register_featured_credit_block() {
	wp_register_script(
		'koehlbrand-featured-credit-editor',
		get_theme_file_uri( 'blocks/featured-credit/index.js' ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ),
		wp_get_theme()->get( 'Version' ),
		true
	);

	register_block_type(
		get_theme_file_path( 'blocks/featured-credit' ),
		array( 'render_callback' => 'koehlbrand_render_featured_credit_block' )
	);
}
add_action( 'init', 'koehlbrand_register_featured_credit_block' );

/**
 * Frontend-Ausgabe.
 *
 * Ohne Caption rendert der Block nichts – kein leerer Kasten unter dem Bild.
 * Das ist Absicht: Bilder aus Quellen ohne Attributionspflicht (Unsplash,
 * Pexels) sollen keine leere Zeile erzeugen.
 *
 * `postId` kommt aus dem Kontext, damit der Block auch dort den richtigen
 * Beitrag meint, wo er innerhalb einer Query steht.
 */
function koehlbrand_render_featured_credit_block( $attributes = array(), $content = '', $block = null ) {
	$post_id = $block->context['postId'] ?? get_the_ID();
	$credit  = koehlbrand_featured_image_credit( $post_id );

	if ( '' === $credit ) {
		return '';
	}

	$wrapper = get_block_wrapper_attributes( array( 'class' => 'koehlbrand-featured-credit' ) );

	// `<p>` und nicht `<figcaption>`: Der Block steht als Geschwister neben dem
	// `<figure>` von `wp:post-featured-image`, und ein `figcaption` außerhalb
	// eines `figure` ist ungültiges HTML.
	return sprintf(
		'<p %s>%s</p>',
		$wrapper,
		wp_kses_post( $credit )
	);
}
