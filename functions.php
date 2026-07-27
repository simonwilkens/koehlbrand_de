<?php
/**
 * Köhlbrand Theme – functions.php
 *
 * Block-Theme für koehlbrand.de. Layout/Farben/Typografie kommen aus theme.json;
 * diese Datei kümmert sich um Theme-Supports, Editor-Styles, die einmalige
 * Grundeinrichtung (Kategorien/Permalinks) und ein paar kleine
 * Komfortfunktionen (Copyright-Jahr, Rubrik- und Related-Queries).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Kein direkter Zugriff.
}

/**
 * Wird hochgezählt, wenn die Grundeinrichtung erneut laufen soll (z. B. weil
 * eine neue Rubrik dazugekommen ist).
 */
define( 'KOEHLBRAND_SETUP_VERSION', '2' );

/**
 * Ausgelagerte Bausteine. SEO liegt bewusst im Theme statt in einem Plugin
 * (Yoast/RankMath), weil die Artikel automatisiert über die WP REST API
 * entstehen – die Pipeline müsste sonst plugin-spezifische Metafelder
 * befüllen. Siehe technik/architektur-plan.md.
 */
require_once get_theme_file_path( 'inc/seo-meta.php' );
require_once get_theme_file_path( 'inc/seo-schema.php' );
require_once get_theme_file_path( 'inc/breadcrumbs.php' );
require_once get_theme_file_path( 'inc/ads.php' );
require_once get_theme_file_path( 'inc/analytics.php' );

/**
 * Bausteine für die Verweildauer: Lesezeit, Inhaltsverzeichnis,
 * Artikel-Navigation und die Rangfolge der Empfehlungen am Artikelende.
 */
require_once get_theme_file_path( 'inc/reading-time.php' );
require_once get_theme_file_path( 'inc/toc.php' );
require_once get_theme_file_path( 'inc/post-nav.php' );
require_once get_theme_file_path( 'inc/related-posts.php' );

/**
 * Die vier Rubriken des Portals. Slug => [ Name, Beschreibung ].
 * Die Slugs müssen zu den Links in parts/header.html und parts/footer.html
 * sowie zu den CSS-Klassen "koehlbrand-cat-<slug>" in front-page.html passen.
 */
function koehlbrand_rubriken() {
	return array(
		'neubau'          => array(
			'Neubau der Brücke',
			'Planung, Bau und Zeitplan der neuen Köhlbrandquerung – von der Trassenwahl bis zur Fertigstellung.',
		),
		'hafenwirtschaft' => array(
			'Hafenwirtschaft',
			'Umschlagzahlen, Logistik und wirtschaftliche Bedeutung des Hamburger Hafens rund um den Köhlbrand.',
		),
		'ausflugstipps'   => array(
			'Ausflugstipps',
			'Aussichtspunkte, Touren und Anfahrt – wie man die Köhlbrandbrücke und den Hafen selbst erlebt.',
		),
		'fototipps'       => array(
			'Fototipps',
			'Standorte, Tageszeiten und Kameraeinstellungen für die besten Aufnahmen der Brücke.',
		),
	);
}

/**
 * Grundlegende Theme-Unterstützung.
 */
function koehlbrand_setup() {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	// Ohne 'comment-form'/'comment-list': das Portal führt keine Kommentare,
	// siehe koehlbrand_disable_comments().
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );

	// Seiten bekommen ein Auszugsfeld. Beiträge haben es von Haus aus, Seiten
	// nicht – ohne das leitet koehlbrand_meta_description() die Beschreibung
	// jeder Seite aus dem Fließtext ab. Betrifft die Pillar-Seiten der
	// Content-Strategie und die Startseite, deren Auszug ihre Description ist.
	add_post_type_support( 'page', 'excerpt' );

	// Editor lädt dieselben Schriften/Farben wie das Frontend.
	add_editor_style( 'style.css' );

	// Übersetzungen (falls /languages später ergänzt wird).
	load_theme_textdomain( 'koehlbrand', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'koehlbrand_setup' );

/**
 * style.css im Frontend laden.
 *
 * Block-Themes bekommen das NICHT geschenkt: WordPress bindet die style.css
 * eines Block-Themes nicht automatisch ein, add_editor_style() wirkt nur im
 * Editor. Ohne diesen Enqueue bleiben Fokus-Rahmen, Skip-Link, Rubrik-Badge
 * und Brotkrumen-Styles im Frontend wirkungslos.
 */
function koehlbrand_enqueue_styles() {
	wp_enqueue_style(
		'koehlbrand-style',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'koehlbrand_enqueue_styles' );

/**
 * Eigene Block-Pattern-Kategorie "Köhlbrand", damit die Redaktion die
 * mitgelieferten Muster (Fakten-Box, Bild mit Credit, CTA-Kasten) im
 * Editor gebündelt findet.
 */
function koehlbrand_register_pattern_categories() {
	register_block_pattern_category(
		'koehlbrand',
		array( 'label' => __( 'Köhlbrand', 'koehlbrand' ) )
	);
}
add_action( 'init', 'koehlbrand_register_pattern_categories' );


/* -------------------------------------------------------------------------
 * Einmalige Grundeinrichtung
 *
 * Ohne diese Schritte funktioniert das Theme nicht: die Rubrik-Sektionen der
 * Startseite und sämtliche Menüpunkte hängen an den vier Kategorie-Slugs und
 * an der Permalink-Struktur. Läuft bei Theme-Aktivierung und – als Netz für
 * Deployments über Git Updater – noch einmal beim nächsten Admin-Aufruf.
 * ---------------------------------------------------------------------- */

/**
 * Setup nachholen, falls es für die aktuelle Version noch nicht lief.
 */
function koehlbrand_maybe_run_setup() {
	if ( KOEHLBRAND_SETUP_VERSION === get_option( 'koehlbrand_setup_version' ) ) {
		return;
	}
	koehlbrand_run_setup();
}
add_action( 'admin_init', 'koehlbrand_maybe_run_setup' );
add_action( 'after_switch_theme', 'koehlbrand_run_setup' );

/**
 * Legt Rubriken und Pflichtseiten an und richtet sprechende URLs ein.
 */
function koehlbrand_run_setup() {
	koehlbrand_setup_categories();
	koehlbrand_setup_permalinks();
	koehlbrand_setup_required_pages();
	koehlbrand_setup_close_comments();

	update_option( 'koehlbrand_setup_version', KOEHLBRAND_SETUP_VERSION );
}

/**
 * Die vier Rubriken anlegen, sofern noch nicht vorhanden. Bestehende
 * Kategorien werden nicht angefasst – auch Beschreibungen nicht, damit
 * redaktionelle Texte bei einem Update nicht überschrieben werden.
 */
function koehlbrand_setup_categories() {
	foreach ( koehlbrand_rubriken() as $slug => $data ) {
		if ( term_exists( $slug, 'category' ) ) {
			continue;
		}

		wp_insert_term(
			$data[0],
			'category',
			array(
				'slug'        => $slug,
				'description' => $data[1],
			)
		);
	}
}

/**
 * Sprechende URLs. Ohne diesen Schritt läuft die Seite auf "?p=123" – für ein
 * Portal, das seinen Traffic aus Suchmaschinen zieht, ein Ausschlusskriterium.
 *
 * Die Kategorie-Basis muss "kategorie" sein, weil Header und Footer fest auf
 * /kategorie/<slug>/ verlinken; WordPress würde sonst /category/<slug>/
 * ausliefern und jeder Menüpunkt liefe ins Leere.
 *
 * Eine bereits gesetzte Permalink-Struktur wird respektiert und nicht
 * überschrieben – nur die leere Standardeinstellung wird ersetzt.
 */
function koehlbrand_setup_permalinks() {
	global $wp_rewrite;

	$changed = false;

	if ( '' === get_option( 'permalink_structure' ) ) {
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$changed = true;
	}

	if ( '' === get_option( 'category_base' ) ) {
		update_option( 'category_base', 'kategorie' );
		$changed = true;
	}

	if ( '' === get_option( 'tag_base' ) ) {
		update_option( 'tag_base', 'thema' );
		$changed = true;
	}

	if ( $changed ) {
		$wp_rewrite->init();
		$wp_rewrite->flush_rules();
	}
}

/**
 * Impressum, Datenschutz und Kontakt sind im Footer verlinkt, in Deutschland
 * gesetzlich vorgeschrieben und Voraussetzung für die AdSense-Freischaltung.
 * Sie werden hier nur als **Entwurf** mit Platzhalter angelegt – die Inhalte
 * müssen redaktionell/juristisch gefüllt und dann veröffentlicht werden.
 */
function koehlbrand_setup_required_pages() {
	$pages = array(
		'impressum'   => 'Impressum',
		'datenschutz' => 'Datenschutz',
		'kontakt'     => 'Kontakt',
	);

	foreach ( $pages as $slug => $title ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}

		wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'draft',
				'post_name'      => $slug,
				'post_title'     => $title,
				'post_content'   => '<!-- wp:paragraph --><p>Platzhalter – dieser Text muss noch gefüllt werden, bevor die Seite veröffentlicht wird.</p><!-- /wp:paragraph -->',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);
	}
}

/**
 * Kommentare in den Grundeinstellungen abschalten und bei bestehenden
 * Inhalten schließen.
 *
 * Die Laufzeit-Filter weiter unten würden allein genügen, aber ohne diesen
 * Schritt stünde im Backend bei jedem Beitrag weiterhin „Kommentare erlauben“
 * – ein Widerspruch, der irgendwann jemanden dazu bringt, die Filter wieder
 * herauszunehmen.
 */
function koehlbrand_setup_close_comments() {
	update_option( 'default_comment_status', 'closed' );
	update_option( 'default_ping_status', 'closed' );

	$offen = get_posts(
		array(
			'post_type'   => 'any',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);

	foreach ( $offen as $id ) {
		if ( 'closed' === get_post_field( 'comment_status', $id ) && 'closed' === get_post_field( 'ping_status', $id ) ) {
			continue;
		}

		wp_update_post(
			array(
				'ID'             => $id,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);
	}
}

/**
 * Hinweis im Backend, solange eine der Pflichtseiten noch Entwurf ist.
 */
function koehlbrand_required_pages_notice() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}

	$offen = array();
	foreach ( array( 'impressum' => 'Impressum', 'datenschutz' => 'Datenschutz', 'kontakt' => 'Kontakt' ) as $slug => $title ) {
		$page = get_page_by_path( $slug );
		if ( ! $page || 'publish' !== $page->post_status ) {
			$offen[] = $title;
		}
	}

	if ( empty( $offen ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p><strong>Köhlbrand-Theme:</strong> %s noch nicht veröffentlicht. Diese Seiten sind im Footer verlinkt, rechtlich vorgeschrieben und Voraussetzung für die AdSense-Freischaltung.</p></div>',
		esc_html( implode( ', ', $offen ) )
	);
}
add_action( 'admin_notices', 'koehlbrand_required_pages_notice' );


/**
 * Warnung, wenn WordPress Suchmaschinen aussperrt.
 *
 * Einstellungen → Lesen → "Suchmaschinen davon abhalten, diese Website zu
 * indexieren" setzt alles auf noindex. Für ein Portal, dessen Geschäftsmodell
 * auf Suchmaschinen-Traffic beruht, ist das der teuerste Ein-Klick-Fehler
 * überhaupt – und er fällt monatelang niemandem auf.
 */
function koehlbrand_blog_public_notice() {
	if ( ! current_user_can( 'manage_options' ) || get_option( 'blog_public' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p><strong>Köhlbrand-Theme:</strong> Suchmaschinen sind derzeit ausgesperrt – die gesamte Website liefert <code>noindex</code>. Für die Testphase in Ordnung, vor dem Livegang unbedingt unter <a href="%s">Einstellungen → Lesen</a> abschalten.</p></div>',
		esc_url( admin_url( 'options-reading.php' ) )
	);
}
add_action( 'admin_notices', 'koehlbrand_blog_public_notice' );


/* -------------------------------------------------------------------------
 * Kommentare abgeschaltet
 *
 * Entscheidung vom 26.07.2026: Das Portal führt keine Leserkommentare. Ein
 * offenes Kommentarfeld auf einer vollautomatisch bespielten Seite müsste
 * moderiert werden – Spam, Haftung für fremde Inhalte, DSGVO (IP-Adressen,
 * Gravatar) und die AdSense-Regeln für nutzergenerierte Inhalte kämen alle
 * zusammen, ohne dass jemand täglich hinschaut.
 *
 * Abgeschaltet wird auf mehreren Ebenen, damit weder ein Template, noch ein
 * Plugin, noch ein direkter POST auf wp-comments-post.php ein Schlupfloch
 * lässt.
 * ---------------------------------------------------------------------- */

/**
 * Kommentare und Pingbacks sind überall geschlossen – unabhängig davon, was
 * am einzelnen Beitrag steht.
 */
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );

/**
 * Vorhandene Kommentare (falls je welche existieren) gar nicht erst laden.
 */
add_filter( 'comments_array', '__return_empty_array', 20 );

/**
 * Kommentar-Feeds aus dem <head> nehmen. `automatic-feed-links` würde sonst
 * pro Beitrag einen Feed verlinken, der immer leer bleibt.
 */
add_filter( 'feed_links_show_comments_feed', '__return_false' );

/**
 * Kommentar-Feeds, die trotzdem direkt aufgerufen werden, auf 404 setzen –
 * ein leerer Feed mit HTTP 200 ist für Suchmaschinen nur Ballast.
 */
function koehlbrand_block_comment_feeds() {
	if ( is_comment_feed() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
add_action( 'template_redirect', 'koehlbrand_block_comment_feeds', 1 );

/**
 * Kommentar-Menü und -Widgets im Backend ausblenden, damit die Abschaltung
 * nicht versehentlich rückgängig gemacht wird.
 */
function koehlbrand_hide_comment_ui() {
	remove_menu_page( 'edit-comments.php' );
	remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
}
add_action( 'admin_menu', 'koehlbrand_hide_comment_ui' );

/**
 * Der direkte Aufruf von /wp-admin/edit-comments.php bliebe sonst erreichbar,
 * auch wenn der Menüpunkt weg ist.
 */
function koehlbrand_block_comment_admin() {
	global $pagenow;

	if ( in_array( $pagenow, array( 'edit-comments.php', 'options-discussion.php' ), true ) ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
}
add_action( 'admin_init', 'koehlbrand_block_comment_admin' );

/**
 * Kommentar-Eintrag aus der Adminleiste im Frontend entfernen.
 */
function koehlbrand_remove_admin_bar_comments( $wp_admin_bar ) {
	$wp_admin_bar->remove_node( 'comments' );
}
add_action( 'admin_bar_menu', 'koehlbrand_remove_admin_bar_comments', 999 );

/**
 * Kommentar-Unterstützung von den Inhaltstypen nehmen. Damit verschwindet
 * auch die Spalte in der Beitragsliste und das Feld im Block-Editor.
 */
function koehlbrand_remove_comment_support() {
	foreach ( array( 'post', 'page', 'attachment' ) as $type ) {
		remove_post_type_support( $type, 'comments' );
		remove_post_type_support( $type, 'trackbacks' );
	}
}
add_action( 'init', 'koehlbrand_remove_comment_support', 20 );


/* -------------------------------------------------------------------------
 * Query-Anpassungen
 * ---------------------------------------------------------------------- */

/**
 * Query-Loop-Blöcke anhand ihrer CSS-Klasse anpassen.
 *
 * - "koehlbrand-related-posts": Empfehlungen am Artikelende (single.html). Die
 *   Rangfolge nach Schlagwörtern, Rubrik und Aktualität steckt in
 *   inc/related-posts.php.
 * - "koehlbrand-cat-<slug>": auf eine oder mehrere Rubriken einschränken
 *   (Startseite). Das Block-Attribut taxQuery erwartet numerische Term-IDs,
 *   die sich zwischen lokaler Testinstanz und Live-Server unterscheiden –
 *   deshalb wird der Slug hier zur Laufzeit aufgelöst. Mehrere Klassen
 *   ergeben eine ODER-Verknüpfung.
 *
 * WICHTIG: Der Filter bekommt von WordPress den **post-template**-Block
 * übergeben (siehe wp-includes/blocks/post-template.php), nicht den
 * umschließenden query-Block. Die className muss deshalb am
 * <!-- wp:post-template --> stehen, sonst läuft die Erkennung ins Leere.
 */
function koehlbrand_query_loop_vars( $query, $block ) {
	$class_list = $block->parsed_block['attrs']['className'] ?? '';

	if ( ! is_string( $class_list ) || '' === $class_list ) {
		return $query;
	}

	if ( false !== strpos( $class_list, 'koehlbrand-related-posts' ) && is_singular( 'post' ) ) {
		return koehlbrand_related_query_vars( $query );
	}

	if ( preg_match_all( '/koehlbrand-cat-([a-z0-9_-]+)/', $class_list, $treffer ) ) {
		$query['category_name'] = implode( ',', $treffer[1] );
	}

	return $query;
}
add_filter( 'query_loop_block_query_vars', 'koehlbrand_query_loop_vars', 10, 2 );


/* -------------------------------------------------------------------------
 * Darstellung
 * ---------------------------------------------------------------------- */

/**
 * Favicon-Fallback: solange in Einstellungen → Website-Icon kein eigenes
 * Icon hinterlegt ist, das Emblem-SVG aus dem Branding-Ordner verwenden.
 */
function koehlbrand_fallback_favicon() {
	if ( ! has_site_icon() ) {
		printf(
			'<link rel="icon" type="image/svg+xml" href="%s">',
			esc_url( get_theme_file_uri( 'assets/images/koehlbrand-logo-icon.svg' ) )
		);
	}
}
add_action( 'wp_head', 'koehlbrand_fallback_favicon' );

/**
 * Aktive Rubrik in der Navigation markieren.
 *
 * Der Kern vergibt `current-menu-item` nur für Menüpunkte, die als
 * Beitrags-/Term-Referenz angelegt sind (Attribut `id` plus `kind`). Header und
 * Footer verlinken die Rubriken aber als schlichte URLs – absichtlich, denn
 * Term-IDs unterscheiden sich zwischen lokaler Instanz und Live-Server, und
 * genau daran ist schon die Rubrik-Query gescheitert (siehe
 * koehlbrand_query_loop_vars). Ohne id bleibt jeder Menüpunkt inaktiv, und die
 * Amber-Unterkante der aktiven Rubrik käme nie zum Vorschein.
 *
 * Deshalb hier der Abgleich über den Pfad: die aktuell angezeigte Kategorie
 * (bzw. deren Elternkategorie) gegen das href des Menüpunkts. Das funktioniert
 * in jeder Umgebung, weil es an den Slugs hängt und nicht an IDs.
 */
function koehlbrand_mark_current_nav_item( $block_content, $block ) {
	if ( 'core/navigation-link' !== $block['blockName'] || '' === trim( (string) $block_content ) ) {
		return $block_content;
	}

	$url = $block['attrs']['url'] ?? '';
	if ( '' === $url ) {
		return $block_content;
	}

	$aktiv = false;

	if ( is_category() ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			// Aktuelle Kategorie und ihre Vorfahren – so bleibt die Oberrubrik
			// markiert, wenn später Unterkategorien dazukommen.
			$ids = array_merge( array( $term->term_id ), get_ancestors( $term->term_id, 'category' ) );

			foreach ( $ids as $id ) {
				$link = get_category_link( $id );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				if ( untrailingslashit( wp_parse_url( $link, PHP_URL_PATH ) ) === untrailingslashit( wp_parse_url( $url, PHP_URL_PATH ) ) ) {
					$aktiv = true;
					break;
				}
			}
		}
	}

	if ( ! $aktiv ) {
		return $block_content;
	}

	// Die Klasse gehört ans <li>, weil die Stile daran hängen; der Kern setzt
	// sie im Normalfall an derselben Stelle.
	return preg_replace(
		'/class="([^"]*wp-block-navigation-item[^"]*)"/',
		'class="$1 current-menu-item"',
		$block_content,
		1
	);
}
add_filter( 'render_block', 'koehlbrand_mark_current_nav_item', 10, 2 );

/**
 * Copyright-Jahr im Footer: ersetzt den Platzhalter "{jahr}" (siehe
 * parts/footer.html) automatisch durch das aktuelle Jahr.
 */
function koehlbrand_replace_year_placeholder( $block_content ) {
	if ( is_string( $block_content ) && false !== strpos( $block_content, '{jahr}' ) ) {
		$block_content = str_replace( '{jahr}', gmdate( 'Y' ), $block_content );
	}
	return $block_content;
}
add_filter( 'render_block', 'koehlbrand_replace_year_placeholder' );

/**
 * Etwas großzügigere Standard-Auszugslänge (in Wörtern) für post-excerpt,
 * passend zu den Karten-Layouts auf Start- und Archivseiten.
 */
function koehlbrand_excerpt_length( $length ) {
	return 24;
}
add_filter( 'excerpt_length', 'koehlbrand_excerpt_length' );
