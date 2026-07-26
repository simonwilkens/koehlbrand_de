<?php
/**
 * Ad-Infrastruktur (Paket 3)
 *
 * Grundgedanke: Werbeplätze sind Theme-Bausteine mit **reservierter Höhe**.
 * Ein Ad-Slot belegt seinen Platz, bevor irgendein Skript geladen hat – sonst
 * springt das Layout beim Nachladen, der CLS-Wert kippt (Ranking-Faktor) und
 * der RPM sinkt, weil Anzeigen unter dem Daumen wegrutschen.
 *
 * Die Slots rendern in drei Zuständen:
 *
 * 1. **inaktiv**  – keine Publisher-ID hinterlegt, kein Vorschaumodus: es wird
 *                   gar nichts ausgegeben (kein leerer Kasten auf der Seite).
 * 2. **Vorschau** – Option `koehlbrand_ads_preview` an: gestrichelter
 *                   Platzhalter in exakt der später reservierten Höhe. Damit
 *                   lässt sich das Layout abnehmen, ohne ein AdSense-Konto zu
 *                   haben (Zustand der lokalen Testinstanz).
 * 3. **aktiv**    – Publisher-ID gesetzt: echtes `<ins class="adsbygoogle">`.
 *
 * Konfiguration ausschließlich über Optionen/Filter, nicht über Konstanten –
 * die Live-Werte kommen später aus dem wp-admin bzw. per WP-CLI, ohne dass das
 * Theme angefasst werden muss.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Breakpoint, ab dem die Desktop-Reservierung gilt (px). Muss zu den
 * Media-Queries in style.css passen.
 */
define( 'KOEHLBRAND_AD_BP_DESKTOP', 782 );


/* -------------------------------------------------------------------------
 * Konfiguration
 * ---------------------------------------------------------------------- */

/**
 * AdSense-Publisher-ID ("ca-pub-…"). Leerer String = Werbung aus.
 */
function koehlbrand_adsense_client() {
	$client = (string) apply_filters( 'koehlbrand_adsense_client', get_option( 'koehlbrand_adsense_client', '' ) );

	return trim( $client );
}

/**
 * Zuordnung Slot-Name => AdSense-Anzeigenblock-ID (die numerische
 * `data-ad-slot`). Ohne Eintrag rendert der Slot nur die Reservierung, aber
 * kein `<ins>` – ein `<ins>` ohne Slot-ID wirft in der Konsole einen Fehler.
 */
function koehlbrand_adsense_slot_ids() {
	$ids = apply_filters( 'koehlbrand_adsense_slot_ids', get_option( 'koehlbrand_adsense_slot_ids', array() ) );

	return is_array( $ids ) ? $ids : array();
}

/**
 * Vorschaumodus: zeigt die Platzhalter statt echter Anzeigen.
 */
function koehlbrand_ads_preview() {
	return (bool) apply_filters( 'koehlbrand_ads_preview', get_option( 'koehlbrand_ads_preview', false ) );
}

/**
 * Echte Anzeigenauslieferung aktiv?
 */
function koehlbrand_ads_active() {
	return '' !== koehlbrand_adsense_client();
}

/**
 * Werden überhaupt Slots ausgegeben (echt oder als Vorschau)?
 */
function koehlbrand_ads_visible() {
	if ( is_admin() || is_feed() || is_embed() ) {
		return false;
	}

	return (bool) apply_filters( 'koehlbrand_ads_visible', koehlbrand_ads_active() || koehlbrand_ads_preview() );
}

/**
 * Die Werbeplätze des Portals.
 *
 * `reserve` ist die Höhe in px, die **vor** dem Laden freigehalten wird –
 * getrennt nach mobil und Desktop, weil dieselbe Fläche unterschiedlich hoch
 * ausgeliefert wird. Ein Wert von 0 bedeutet: dieser Slot existiert auf dem
 * Breakpoint nicht (siehe display-Regeln in style.css).
 *
 * `format` steuert das `data-ad-format`. Feste Größen (`width`/`height`)
 * gewinnen gegenüber `format` – nötig beim 300×600 der Sidebar, sonst füllt
 * AdSense responsiv und die Reservierung stimmt nicht mehr.
 *
 * `max_width` begrenzt die Fläche, in die ein responsiver Block einrückt.
 * Das ist der Hebel für die Formatwahl: ein responsiver Block füllt sonst die
 * volle Spaltenbreite (720px) und wird zum breiten Banner. Mit 336px bzw.
 * 300px Deckel liefert AdSense stattdessen Rechtecke – Large Rectangle
 * (336×280) auf dem Desktop, Medium Rectangle (300×250) mobil. Genau die
 * beiden Formate erzielen im Fließtext die höchsten Klickraten und den besten
 * RPM (deckt sich mit den Zahlen von hamburg-kulinarisch.de).
 */
function koehlbrand_ad_slots() {
	$slots = array(
		'header'         => array(
			'label'   => 'Banner unter dem Header',
			'reserve' => array( 'mobile' => 100, 'desktop' => 90 ),
			'format'  => 'horizontal',
		),
		'sidebar'        => array(
			'label'   => 'Sidebar (sticky)',
			'reserve' => array( 'mobile' => 0, 'desktop' => 600 ),
			'width'   => 300,
			'height'  => 600,
		),
		'in-content'     => array(
			'label'     => 'Im Artikel (automatisch)',
			'reserve'   => array( 'mobile' => 250, 'desktop' => 280 ),
			'max_width' => array( 'mobile' => 300, 'desktop' => 336 ),
			'format'    => 'rectangle',
		),
		'end-of-article' => array(
			'label'     => 'Artikelende',
			'reserve'   => array( 'mobile' => 250, 'desktop' => 280 ),
			'max_width' => array( 'mobile' => 300, 'desktop' => 336 ),
			'format'    => 'rectangle',
		),
		'in-feed'        => array(
			'label'     => 'Zwischen den Artikel-Karten',
			'reserve'   => array( 'mobile' => 250, 'desktop' => 250 ),
			'max_width' => array( 'mobile' => 300, 'desktop' => 300 ),
			'format'    => 'rectangle',
		),
		'anchor'         => array(
			'label'   => 'Anchor (nur mobil)',
			'reserve' => array( 'mobile' => 60, 'desktop' => 0 ),
			'format'  => 'horizontal',
		),
	);

	return apply_filters( 'koehlbrand_ad_slots', $slots );
}


/* -------------------------------------------------------------------------
 * Ausgabe eines Slots
 * ---------------------------------------------------------------------- */

/**
 * Markup eines Werbeplatzes.
 *
 * Die Kennzeichnung „Anzeige“ steckt fest im Markup: das Trennungsgebot
 * (§ 6 TMG / Pressegesetze) verlangt sie, und sie hier zu verankern ist
 * sicherer, als sie der Redaktion oder der Content-Pipeline zu überlassen.
 *
 * @param string $slot        Slot-Name aus koehlbrand_ad_slots().
 * @param array  $args        wrapper_attributes, extra_class.
 * @return string HTML oder leerer String.
 */
function koehlbrand_ad_markup( $slot, $args = array() ) {
	$slots = koehlbrand_ad_slots();

	if ( ! isset( $slots[ $slot ] ) || ! koehlbrand_ads_visible() ) {
		return '';
	}

	/**
	 * Einzelne Plätze abschalten, ohne die ganze Registry zu ersetzen.
	 */
	if ( ! apply_filters( 'koehlbrand_ad_slot_enabled', true, $slot ) ) {
		return '';
	}

	$config  = $slots[ $slot ];
	$classes = array( 'koehlbrand-ad', 'koehlbrand-ad--' . $slot );

	if ( ! empty( $args['extra_class'] ) ) {
		$classes[] = $args['extra_class'];
	}

	$wrapper = isset( $args['wrapper_attributes'] )
		? $args['wrapper_attributes']
		: sprintf( 'class="%s"', esc_attr( implode( ' ', $classes ) ) );

	$inner = koehlbrand_ads_active()
		? koehlbrand_ad_unit( $slot, $config )
		: koehlbrand_ad_placeholder( $slot, $config );

	// Bewusst ein <div> und kein <aside>: jeder Werbeplatz wäre sonst eine
	// eigene ARIA-Landmarke und würde die Landmarken-Übersicht im Screenreader
	// zumüllen. Die sichtbare Kennzeichnung steht ohnehin im Text.
	return sprintf(
		'<div %1$s><span class="koehlbrand-ad__label">Anzeige</span><div class="koehlbrand-ad__inner">%2$s</div></div>',
		$wrapper,
		$inner
	);
}

/**
 * Echter AdSense-Block.
 *
 * Ohne hinterlegte Anzeigenblock-ID bleibt der Platz leer (aber reserviert) –
 * ein `<ins>` ohne `data-ad-slot` quittiert AdSense mit einer Konsolenfehler-
 * meldung und liefert nichts aus.
 */
function koehlbrand_ad_unit( $slot, $config ) {
	$ids = koehlbrand_adsense_slot_ids();

	if ( empty( $ids[ $slot ] ) ) {
		return '';
	}

	$attr = array(
		'class'             => 'adsbygoogle',
		'data-ad-client'    => koehlbrand_adsense_client(),
		'data-ad-slot'      => (string) $ids[ $slot ],
	);

	if ( ! empty( $config['width'] ) && ! empty( $config['height'] ) ) {
		$attr['style'] = sprintf( 'display:inline-block;width:%dpx;height:%dpx', $config['width'], $config['height'] );
	} else {
		$attr['style']          = 'display:block';
		$attr['data-ad-format'] = $config['format'] ?? 'auto';

		// Nur ohne Breitendeckel: `full-width-responsive` lässt den Block auf
		// dem Handy über die volle Displaybreite laufen. Bei den Rechtecken
		// ist genau das nicht gewollt – die sollen 300 bzw. 336px breit
		// bleiben, sonst liefert AdSense wieder Banner statt Rectangles.
		if ( empty( $config['max_width'] ) ) {
			$attr['data-full-width-responsive'] = 'true';
		}
	}

	if ( ! empty( $config['layout_key'] ) ) {
		$attr['data-ad-layout-key'] = $config['layout_key'];
	}

	$html = '<ins';
	foreach ( $attr as $name => $value ) {
		$html .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
	}
	$html .= '></ins>';

	$html .= wp_get_inline_script_tag( '(adsbygoogle = window.adsbygoogle || []).push({});' );

	return $html;
}

/**
 * Platzhalter für den Vorschaumodus – gleiche Reservierung, sichtbare Kante.
 */
function koehlbrand_ad_placeholder( $slot, $config ) {
	if ( ! empty( $config['width'] ) ) {
		$groesse = sprintf( '%d×%d', $config['width'], $config['height'] );
	} elseif ( ! empty( $config['max_width'] ) ) {
		$groesse = sprintf(
			'%d×%d mobil / %d×%d Desktop',
			$config['max_width']['mobile'],
			$config['reserve']['mobile'],
			$config['max_width']['desktop'],
			$config['reserve']['desktop']
		);
	} else {
		$groesse = sprintf( '%dpx mobil / %dpx Desktop', $config['reserve']['mobile'], $config['reserve']['desktop'] );
	}

	return sprintf(
		'<span class="koehlbrand-ad__placeholder">%s<br><small>%s reserviert</small></span>',
		esc_html( $config['label'] ),
		esc_html( $groesse )
	);
}

/**
 * Bequemer Direkt-Ausdruck (für Template-Hooks).
 */
function koehlbrand_the_ad( $slot, $args = array() ) {
	echo koehlbrand_ad_markup( $slot, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup wird oben aufgebaut und escaped.
}


/* -------------------------------------------------------------------------
 * Block "koehlbrand/ad-slot"
 * ---------------------------------------------------------------------- */

/**
 * Werbeplatz als Block, damit er in den Templates (und im Site-Editor)
 * platzierbar ist. Serverseitig gerendert – die Sichtbarkeit hängt an der
 * Konfiguration, nicht am gespeicherten Markup.
 */
function koehlbrand_register_ad_slot_block() {
	wp_register_script(
		'koehlbrand-ad-slot-editor',
		get_theme_file_uri( 'blocks/ad-slot/index.js' ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
		wp_get_theme()->get( 'Version' ),
		true
	);

	register_block_type(
		get_theme_file_path( 'blocks/ad-slot' ),
		array( 'render_callback' => 'koehlbrand_render_ad_slot_block' )
	);
}
add_action( 'init', 'koehlbrand_register_ad_slot_block' );

/**
 * Frontend-Ausgabe des Blocks.
 *
 * `interval` ist der In-Feed-Fall: liegt der Block innerhalb eines
 * post-template, rendert er einmal pro Beitrag. Mit `interval` = 4 erscheint er
 * nur nach jeder vierten Karte. Der Zähler hängt an der queryId, damit mehrere
 * Query-Loops auf derselben Seite (Startseite!) sich nicht gegenseitig
 * hochzählen.
 */
function koehlbrand_render_ad_slot_block( $attributes = array(), $content = '', $block = null ) {
	static $zaehler = array();

	$slot     = isset( $attributes['slot'] ) ? sanitize_key( $attributes['slot'] ) : 'in-content';
	$interval = isset( $attributes['interval'] ) ? (int) $attributes['interval'] : 0;

	if ( $interval > 0 ) {
		$key             = ( $block->context['queryId'] ?? 0 ) . '-' . $slot;
		$zaehler[ $key ] = ( $zaehler[ $key ] ?? 0 ) + 1;

		if ( 0 !== $zaehler[ $key ] % $interval ) {
			return '';
		}
	}

	$classes = array( 'koehlbrand-ad', 'koehlbrand-ad--' . $slot );

	if ( ! empty( $attributes['sticky'] ) ) {
		$classes[] = 'koehlbrand-ad--sticky';
	}

	return koehlbrand_ad_markup(
		$slot,
		array(
			'wrapper_attributes' => get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) ),
		)
	);
}


/* -------------------------------------------------------------------------
 * Auto-Injection im Artikeltext
 * ---------------------------------------------------------------------- */

/**
 * Mindest-Wortzahl, ab der überhaupt In-Content-Werbung eingesetzt wird.
 *
 * AdSense verlangt „Inhalt ≥ Werbung“; ein 300-Wörter-Text mit drei Anzeigen
 * ist ein klassischer Ablehnungsgrund bei der Prüfung.
 */
function koehlbrand_ad_min_words() {
	return (int) apply_filters( 'koehlbrand_ad_min_words', 600 );
}

/**
 * Positionen aller Absatzenden, die **nicht** in einem anderen Element liegen.
 *
 * Nötig, weil ein `</p>` genauso gut im Zitat, in der Bildunterschrift oder in
 * einer der Theme-Patterns (Fakten-Box, CTA-Kasten) stecken kann – ein Ad
 * mitten in einem gerahmten Kasten zerlegt dessen Layout.
 *
 * @return int[] Byte-Offsets direkt hinter dem jeweiligen `</p>`.
 */
function koehlbrand_top_level_paragraph_ends( $content ) {
	$positions = array();
	$tiefe     = 0;
	$container = array( 'div', 'blockquote', 'figure', 'table', 'ul', 'ol', 'dl', 'aside', 'details', 'form', 'section', 'article' );

	if ( ! preg_match_all( '#<(/?)([a-z][a-z0-9]*)\b[^>]*?(/?)>#i', $content, $tags, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
		return $positions;
	}

	foreach ( $tags as $tag ) {
		$name        = strtolower( $tag[2][0] );
		$ist_schluss = '/' === $tag[1][0];
		$ist_leer    = '/' === $tag[3][0];

		if ( in_array( $name, $container, true ) ) {
			if ( ! $ist_leer ) {
				$tiefe = max( 0, $tiefe + ( $ist_schluss ? -1 : 1 ) );
			}
			continue;
		}

		if ( 'p' === $name && $ist_schluss && 0 === $tiefe ) {
			$positions[] = $tag[0][1] + strlen( $tag[0][0] );
		}
	}

	return $positions;
}

/**
 * In-Content-Anzeigen einsetzen: nach Absatz 2, danach alle N Absätze.
 *
 * Läuft auf Priorität 20, also nach den Block-Renderern, aber vor
 * `do_shortcode` (11) … – entscheidend ist nur, dass der Text zu dem Zeitpunkt
 * bereits als HTML vorliegt, sonst gibt es keine Absätze zum Zählen.
 */
function koehlbrand_inject_content_ads( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	if ( is_feed() || is_embed() || post_password_required() || ! koehlbrand_ads_visible() ) {
		return $content;
	}

	// Umlaute: str_word_count() zerlegt „Köhlbrandbrücke“ in drei Wörter.
	$woerter = preg_match_all( '/\S+/u', wp_strip_all_tags( $content ) );

	if ( $woerter < koehlbrand_ad_min_words() ) {
		return $content;
	}

	$enden = koehlbrand_top_level_paragraph_ends( $content );

	$start    = (int) apply_filters( 'koehlbrand_ad_start_paragraph', 2 );
	$interval = (int) apply_filters( 'koehlbrand_ad_paragraph_interval', 4 );
	$max      = (int) apply_filters( 'koehlbrand_ad_max_in_content', 3 );

	if ( count( $enden ) < $start + 2 || $interval < 1 ) {
		return $content;
	}

	$markup = koehlbrand_ad_markup( 'in-content' );

	if ( '' === $markup ) {
		return $content;
	}

	// Einfügepunkte sammeln: nach Absatz $start, dann alle $interval. Die
	// letzten beiden Absätze bleiben frei – dort sitzt schon der
	// End-of-Article-Slot, zwei Anzeigen direkt untereinander sind sowohl
	// Regelverstoß als auch schlechte Leseerfahrung.
	$grenze  = count( $enden ) - 2;
	$treffer = array();

	for ( $i = $start; $i <= $grenze && count( $treffer ) < $max; $i += $interval ) {
		$treffer[] = $enden[ $i - 1 ];
	}

	// Von hinten einsetzen, sonst verschieben sich die Offsets.
	foreach ( array_reverse( $treffer ) as $offset ) {
		$content = substr_replace( $content, $markup, $offset, 0 );
	}

	return $content;
}
add_filter( 'the_content', 'koehlbrand_inject_content_ads', 20 );


/* -------------------------------------------------------------------------
 * Anchor-Anzeige (mobil)
 * ---------------------------------------------------------------------- */

/**
 * Am unteren Rand fixierte Anzeige auf Artikel- und Archivseiten.
 *
 * `position: fixed` erzeugt selbst keinen Layout-Shift; damit die Leiste den
 * Seitenfuß nicht überdeckt, bekommt `.wp-site-blocks` per Body-Klasse einen
 * passenden Innenabstand (siehe style.css).
 */
function koehlbrand_anchor_ad_visible() {
	if ( ! koehlbrand_ads_visible() ) {
		return false;
	}

	return (bool) apply_filters( 'koehlbrand_anchor_ad_visible', is_singular( 'post' ) || is_archive() || is_home() );
}

function koehlbrand_render_anchor_ad() {
	if ( ! koehlbrand_anchor_ad_visible() ) {
		return;
	}

	koehlbrand_the_ad( 'anchor' );
}
add_action( 'wp_footer', 'koehlbrand_render_anchor_ad' );

function koehlbrand_anchor_body_class( $classes ) {
	if ( koehlbrand_anchor_ad_visible() ) {
		$classes[] = 'koehlbrand-has-anchor-ad';
	}

	return $classes;
}
add_filter( 'body_class', 'koehlbrand_anchor_body_class' );


/* -------------------------------------------------------------------------
 * Skripte, Reservierungs-CSS, Verbindungsaufbau
 * ---------------------------------------------------------------------- */

/**
 * Reservierungs-CSS aus der Slot-Registry erzeugen.
 *
 * Bewusst generiert statt in style.css gepflegt: die Höhen stehen damit an
 * genau einer Stelle, und ein Filter auf koehlbrand_ad_slots() ändert Markup
 * und Reservierung gemeinsam. Alles Übrige (Layout, Farben, sticky) steckt
 * statisch in style.css.
 */
function koehlbrand_ad_reservation_css() {
	$mobil   = '';
	$desktop = '';

	foreach ( koehlbrand_ad_slots() as $slot => $config ) {
		$sel   = sprintf( '.koehlbrand-ad--%s .koehlbrand-ad__inner', $slot );
		$rahmen = sprintf( '.koehlbrand-ad--%s', $slot );

		if ( ! empty( $config['reserve']['mobile'] ) ) {
			$mobil .= sprintf( '%s{min-height:%dpx}', $sel, $config['reserve']['mobile'] );
		}

		if ( ! empty( $config['reserve']['desktop'] ) ) {
			$desktop .= sprintf( '%s{min-height:%dpx}', $sel, $config['reserve']['desktop'] );
		}

		// Breitendeckel am äußeren Rahmen, nicht am Inner: sonst stünde die
		// Kennzeichnung „Anzeige“ über der vollen Spaltenbreite, während die
		// Anzeige darunter nur 336px breit ist.
		if ( ! empty( $config['max_width']['mobile'] ) ) {
			$mobil .= sprintf( '%s{max-width:%dpx;margin-inline:auto}', $rahmen, $config['max_width']['mobile'] );
		}

		if ( ! empty( $config['max_width']['desktop'] ) ) {
			$desktop .= sprintf( '%s{max-width:%dpx}', $rahmen, $config['max_width']['desktop'] );
		}
	}

	$css = $mobil;

	if ( '' !== $desktop ) {
		$css .= sprintf( '@media(min-width:%dpx){%s}', KOEHLBRAND_AD_BP_DESKTOP, $desktop );
	}

	return $css;
}

function koehlbrand_enqueue_ad_assets() {
	if ( ! koehlbrand_ads_visible() ) {
		return;
	}

	wp_add_inline_style( 'koehlbrand-style', koehlbrand_ad_reservation_css() );

	if ( ! koehlbrand_ads_active() ) {
		return;
	}

	wp_enqueue_script(
		'koehlbrand-adsense',
		add_query_arg( 'client', koehlbrand_adsense_client(), 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js' ),
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Fremdskript, Version darf nicht angehängt werden.
		array( 'strategy' => 'async' )
	);
}
// Priorität 20: wp_add_inline_style() greift nur, wenn das Ziel-Stylesheet
// bereits eingereiht ist. inc/ads.php wird oben in der functions.php geladen,
// registriert seinen Hook also VOR koehlbrand_enqueue_styles() – bei gleicher
// Priorität liefe es zu früh und die Reservierungen fielen ersatzlos weg.
add_action( 'wp_enqueue_scripts', 'koehlbrand_enqueue_ad_assets', 20 );

/**
 * `crossorigin="anonymous"` ergänzen – von Google so dokumentiert, sorgt für
 * verwertbare Fehlermeldungen statt eines nackten "Script error".
 */
function koehlbrand_adsense_script_tag( $tag, $handle ) {
	if ( 'koehlbrand-adsense' === $handle && false === strpos( $tag, 'crossorigin' ) ) {
		$tag = str_replace( ' src=', ' crossorigin="anonymous" src=', $tag );
	}

	return $tag;
}
add_filter( 'script_loader_tag', 'koehlbrand_adsense_script_tag', 10, 2 );

/**
 * Verbindungsaufbau zu den Anzeigen-Hosts vorziehen. Spart pro Host einen
 * DNS-Lookup plus TLS-Handshake, bevor der erste Anzeigen-Request rausgeht.
 */
function koehlbrand_ad_resource_hints( $hints, $relation ) {
	if ( 'preconnect' !== $relation || ! koehlbrand_ads_active() ) {
		return $hints;
	}

	$hints[] = array(
		'href'        => 'https://pagead2.googlesyndication.com',
		'crossorigin' => 'anonymous',
	);
	$hints[] = array(
		'href'        => 'https://googleads.g.doubleclick.net',
		'crossorigin' => 'anonymous',
	);

	return $hints;
}
add_filter( 'wp_resource_hints', 'koehlbrand_ad_resource_hints', 10, 2 );


/* -------------------------------------------------------------------------
 * CMP-Slot (Consent, TCF 2.2)
 * ---------------------------------------------------------------------- */

/**
 * Einhängepunkt für das Consent-Skript.
 *
 * Google liefert für EWR-Traffic ohne zertifizierte CMP (TCF 2.2) gar keine
 * oder nur nicht-personalisierte Anzeigen aus – das ist keine Abwägung,
 * sondern eine Auslieferungssperre. Der Hook läuft auf wp_head-Priorität 1 und
 * damit vor wp_print_head_scripts() (Priorität 9), das den AdSense-Loader
 * ausgibt. Die CMP muss zuerst da sein, sonst startet AdSense ohne Consent-
 * Signal.
 *
 * Einbinden später z. B. so:
 *
 *     add_action( 'koehlbrand_cmp', function () { echo '<script …></script>'; } );
 */
function koehlbrand_cmp_slot() {
	do_action( 'koehlbrand_cmp' );
}
add_action( 'wp_head', 'koehlbrand_cmp_slot', 1 );

/**
 * Ist eine CMP eingehängt?
 */
function koehlbrand_cmp_present() {
	return (bool) apply_filters( 'koehlbrand_cmp_present', has_action( 'koehlbrand_cmp' ) );
}

/**
 * Warnung im Backend: Anzeigen scharf, aber keine CMP eingehängt.
 */
function koehlbrand_cmp_notice() {
	if ( ! current_user_can( 'manage_options' ) || ! koehlbrand_ads_active() || koehlbrand_cmp_present() ) {
		return;
	}

	echo '<div class="notice notice-error"><p><strong>Köhlbrand-Theme:</strong> AdSense ist aktiv, aber es ist keine CMP eingehängt (Hook <code>koehlbrand_cmp</code>). Google liefert für EWR-Traffic ohne zertifizierte CMP nach TCF 2.2 keine oder nur nicht-personalisierte Anzeigen aus.</p></div>';
}
add_action( 'admin_notices', 'koehlbrand_cmp_notice' );


/* -------------------------------------------------------------------------
 * ads.txt
 * ---------------------------------------------------------------------- */

/**
 * `/ads.txt` ausliefern, solange keine echte Datei im Web-Root liegt.
 *
 * Ohne ads.txt kaufen nicht-autorisierte Bidder die Inventare nicht mehr –
 * das kostet direkt Umsatz. Weil die Datei ins Domain-Root gehört und nicht
 * ins Theme, hängt sie sonst am Deployment; diese Variante macht das Theme
 * unabhängig davon.
 *
 * Greift nur, wenn WordPress im Root installiert ist – liegt WP in einem
 * Unterverzeichnis, erreicht der Request `/ads.txt` gar nicht erst PHP und die
 * Datei muss vom Webserver kommen.
 */
function koehlbrand_serve_ads_txt() {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$pfad = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wird nur verglichen.

	if ( '/ads.txt' !== $pfad || file_exists( ABSPATH . 'ads.txt' ) ) {
		return;
	}

	$client = koehlbrand_adsense_client();

	if ( '' === $client ) {
		return;
	}

	// "ca-pub-1234" → "pub-1234"; ads.txt kennt das ca-Präfix nicht.
	$publisher = preg_replace( '/^ca-/', '', $client );

	$zeilen = array( sprintf( 'google.com, %s, DIRECT, f08c47fec0942fa0', $publisher ) );

	/**
	 * Weitere Zeilen (andere Vermarkter, Reseller) ergänzen.
	 */
	$zeilen = (array) apply_filters( 'koehlbrand_ads_txt_lines', $zeilen );

	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	echo implode( "\n", array_map( 'sanitize_text_field', $zeilen ) ) . "\n";
	exit;
}
add_action( 'init', 'koehlbrand_serve_ads_txt' );
