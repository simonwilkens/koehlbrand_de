<?php
/**
 * Inhaltsverzeichnis als dynamischer Block – plus die dazugehörigen
 * Sprungmarken im Artikeltext.
 *
 * Zwei Gründe: Leser:innen springen zu dem Abschnitt, wegen dem sie gekommen
 * sind, statt abzuspringen; und Google zeigt für Seiten mit sauberen
 * Sprungmarken gelegentlich Untertitel-Links direkt im Suchergebnis an.
 *
 * Das Verzeichnis kommt ohne JavaScript aus. Auf- und zuklappen erledigt
 * `<details>`, das Scrollen der Browser.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ab wie vielen Überschriften sich ein Verzeichnis lohnt.
 *
 * Bei zwei Zwischenüberschriften ist der Kasten größer als der Nutzen – der
 * Block rendert dann gar nichts.
 */
function koehlbrand_toc_min_headings() {
	return max( 1, (int) apply_filters( 'koehlbrand_toc_min_headings', 3 ) );
}

/**
 * Eindeutige Sprungmarke aus einem Überschriftentext.
 *
 * @param string $text    Überschriftentext (bereits ohne Tags).
 * @param array  $genutzt Referenz auf die bisher vergebenen Marken.
 */
function koehlbrand_heading_anchor( $text, &$genutzt ) {
	$basis = sanitize_title( $text );

	if ( '' === $basis ) {
		$basis = 'abschnitt';
	}

	$marke = $basis;
	$n     = 2;

	while ( isset( $genutzt[ $marke ] ) ) {
		$marke = $basis . '-' . $n;
		$n++;
	}

	$genutzt[ $marke ] = true;

	return $marke;
}

/**
 * Überschriften (H2/H3) aus einem HTML-Schnipsel mit ihren Sprungmarken.
 *
 * Dieselbe Funktion liefert die Einträge fürs Verzeichnis (aus dem
 * gespeicherten Beitragstext) und die Marken für den Fließtext (aus dem
 * gerenderten Text). Weil beide Male derselbe Algorithmus in derselben
 * Reihenfolge läuft, zeigen die Links des Verzeichnisses auf genau die IDs, die
 * im Text landen. Eine im Editor gesetzte eigene Sprungmarke (`id="…"`) hat
 * Vorrang und wird nicht überschrieben.
 *
 * H4 und tiefer bleiben außen vor: ein dreistufiges Verzeichnis ist auf dem
 * Handy länger als der Abschnitt, zu dem es führt.
 *
 * @return array[] Je Eintrag: level, text, id.
 */
function koehlbrand_extract_headings( $html ) {
	$eintraege = array();
	$genutzt   = array();

	if ( ! preg_match_all( '#<h([23])\b([^>]*)>(.*?)</h\1>#is', (string) $html, $treffer, PREG_SET_ORDER ) ) {
		return $eintraege;
	}

	foreach ( $treffer as $t ) {
		$text = trim( wp_strip_all_tags( $t[3] ) );

		if ( '' === $text ) {
			continue;
		}

		if ( preg_match( '/\bid=(["\'])(.*?)\1/i', $t[2], $id_treffer ) && '' !== $id_treffer[2] ) {
			$id             = $id_treffer[2];
			$genutzt[ $id ] = true;
		} else {
			$id = koehlbrand_heading_anchor( $text, $genutzt );
		}

		$eintraege[] = array(
			'level' => (int) $t[1],
			'text'  => $text,
			'id'    => $id,
		);
	}

	return $eintraege;
}

/**
 * Einträge des Verzeichnisses für einen Beitrag, einmal je Aufruf berechnet.
 *
 * Grundlage ist der gespeicherte Beitragstext, nicht der gerenderte: Der Block
 * steht im Template **über** `post-content`, der `the_content`-Filter ist zu
 * diesem Zeitpunkt also noch nicht gelaufen. Bei Kern-Überschriftenblöcken
 * steht das `<h2>` schon im gespeicherten Markup, beide Quellen sind deshalb
 * deckungsgleich. Überschriften, die erst ein dynamischer Block erzeugt,
 * tauchen im Verzeichnis nicht auf – für die automatisiert erzeugten Artikel
 * ist das kein Fall.
 */
function koehlbrand_toc_items( $post = null ) {
	static $cache = array();

	$post = get_post( $post );

	if ( ! $post ) {
		return array();
	}

	if ( ! isset( $cache[ $post->ID ] ) ) {
		$cache[ $post->ID ] = koehlbrand_extract_headings( $post->post_content );
	}

	return $cache[ $post->ID ];
}

/**
 * Sprungmarken in den Artikeltext setzen.
 *
 * Priorität 12: nach `do_blocks()` (9) und `wpautop()` (10), damit die
 * Überschriften als HTML vorliegen, aber vor der Anzeigen-Einspeisung (20) –
 * die verschiebt zwar keine Überschriften, aber die Reihenfolge macht die
 * Absicht deutlich.
 */
function koehlbrand_add_heading_anchors( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || is_feed() ) {
		return $content;
	}

	if ( count( koehlbrand_toc_items() ) < koehlbrand_toc_min_headings() ) {
		return $content;
	}

	$genutzt = array();

	return preg_replace_callback(
		'#<h([23])\b([^>]*)>(.*?)</h\1>#is',
		function ( $t ) use ( &$genutzt ) {
			$text = trim( wp_strip_all_tags( $t[3] ) );

			if ( '' === $text ) {
				return $t[0];
			}

			// Eigene Sprungmarke aus dem Editor bleibt unangetastet.
			if ( preg_match( '/\bid=(["\'])(.*?)\1/i', $t[2], $id_treffer ) && '' !== $id_treffer[2] ) {
				$genutzt[ $id_treffer[2] ] = true;
				return $t[0];
			}

			return sprintf(
				'<h%1$s id="%2$s"%3$s>%4$s</h%1$s>',
				$t[1],
				esc_attr( koehlbrand_heading_anchor( $text, $genutzt ) ),
				$t[2],
				$t[3]
			);
		},
		$content
	);
}
add_filter( 'the_content', 'koehlbrand_add_heading_anchors', 12 );

/**
 * Block registrieren.
 */
function koehlbrand_register_toc_block() {
	wp_register_script(
		'koehlbrand-toc-editor',
		get_theme_file_uri( 'blocks/toc/index.js' ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ),
		wp_get_theme()->get( 'Version' ),
		true
	);

	register_block_type(
		get_theme_file_path( 'blocks/toc' ),
		array( 'render_callback' => 'koehlbrand_render_toc_block' )
	);
}
add_action( 'init', 'koehlbrand_register_toc_block' );

/**
 * Frontend-Ausgabe.
 */
function koehlbrand_render_toc_block( $attributes = array(), $content = '', $block = null ) {
	if ( ! is_singular( 'post' ) ) {
		return '';
	}

	$post_id   = $block->context['postId'] ?? get_the_ID();
	$eintraege = koehlbrand_toc_items( $post_id );

	if ( count( $eintraege ) < koehlbrand_toc_min_headings() ) {
		return '';
	}

	$titel = isset( $attributes['title'] ) && '' !== trim( (string) $attributes['title'] )
		? (string) $attributes['title']
		: __( 'Inhalt dieses Artikels', 'koehlbrand' );

	$zeilen = '';

	foreach ( $eintraege as $eintrag ) {
		$zeilen .= sprintf(
			'<li class="koehlbrand-toc__item koehlbrand-toc__item--h%1$d"><a href="#%2$s">%3$s</a></li>',
			$eintrag['level'],
			esc_attr( $eintrag['id'] ),
			esc_html( $eintrag['text'] )
		);
	}

	$wrapper = get_block_wrapper_attributes( array( 'class' => 'koehlbrand-toc' ) );

	return sprintf(
		'<nav %1$s aria-label="%2$s"><details class="koehlbrand-toc__box" open><summary class="koehlbrand-toc__title">%3$s</summary><ol class="koehlbrand-toc__list">%4$s</ol></details></nav>',
		$wrapper,
		esc_attr__( 'Inhaltsverzeichnis', 'koehlbrand' ),
		esc_html( $titel ),
		$zeilen
	);
}
