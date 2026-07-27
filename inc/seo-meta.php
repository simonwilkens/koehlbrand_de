<?php
/**
 * SEO – Meta-Tags im <head>
 *
 * Bewusst theme-nativ statt per Yoast/RankMath: die Artikel entstehen
 * automatisiert über die WP REST API, und die müsste sonst plugin-spezifische
 * Metafelder befüllen. So wird die Description deterministisch aus Excerpt
 * bzw. Inhalt abgeleitet und die Pipeline muss nichts davon wissen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kanonische URL der aktuell angezeigten Ansicht – inklusive Seitenzahl bei
 * paginierten Archiven (selbstreferenzierend, NICHT auf Seite 1 zeigend:
 * Google will paginierte Seiten indexieren, ein Canonical auf Seite 1
 * verschluckt die Artikel auf Seite 2+).
 */
function koehlbrand_current_url() {
	$url = '';

	if ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_home() ) {
		$blog_id = (int) get_option( 'page_for_posts' );
		$url     = $blog_id ? get_permalink( $blog_id ) : home_url( '/' );
	} elseif ( is_singular() ) {
		$url = get_permalink();
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		$link = $term ? get_term_link( $term ) : '';
		$url  = is_wp_error( $link ) ? '' : $link;
	} elseif ( is_author() ) {
		$url = get_author_posts_url( get_queried_object_id() );
	} elseif ( is_year() ) {
		$url = get_year_link( get_query_var( 'year' ) );
	} elseif ( is_month() ) {
		$url = get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) );
	} elseif ( is_day() ) {
		$url = get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) );
	}

	if ( '' === $url ) {
		$url = home_url( '/' );
	}

	// Bei Einzelansichten regelt WordPress die Paginierung selbst (rel_canonical).
	if ( ! is_singular() ) {
		$paged = (int) get_query_var( 'paged' );
		if ( $paged > 1 ) {
			$url = trailingslashit( $url ) . 'page/' . $paged . '/';
		}
	}

	return $url;
}

/**
 * Meta-Description der aktuellen Ansicht. Leerer String = kein Tag ausgeben.
 */
function koehlbrand_meta_description() {
	$desc = '';

	if ( is_front_page() ) {
		// Ist eine statische Seite als Startseite eingestellt, gilt ihr Auszug.
		// Der Untertitel muss sonst zwei Felder mit gegensätzlichen Längen
		// bedienen: Er steckt auch im <title> ("Name – Untertitel", ab rund 60
		// Zeichen abgeschnitten), während die Description bis 155 Zeichen trägt.
		// Der Auszug trennt beides, ohne eine eigene Option zu erfinden.
		//
		// Der Fließtext-Rückfall aus dem is_singular()-Zweig taugt hier nicht:
		// front-page.html gibt den Seiteninhalt gar nicht aus, er kann also
		// beliebig weit von dem abweichen, was Besucher sehen.
		$front = is_singular() ? get_queried_object() : null;

		$desc = ( $front instanceof WP_Post && '' !== trim( (string) $front->post_excerpt ) )
			? $front->post_excerpt
			: get_bloginfo( 'description' );
	} elseif ( is_singular() ) {
		$post = get_queried_object();

		if ( $post instanceof WP_Post ) {
			if ( '' !== trim( (string) $post->post_excerpt ) ) {
				$desc = $post->post_excerpt;
			} else {
				// Block-Kommentare und Shortcodes raus, sonst landet
				// "wp:paragraph" in der Suchergebnis-Vorschau.
				$desc = wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $post->post_content ) ) );
			}
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			$desc = '' !== trim( (string) $term->description )
				? $term->description
				: sprintf( 'Alle Beiträge in der Rubrik „%s“ auf %s.', $term->name, get_bloginfo( 'name' ) );
		}
	}

	$desc = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $desc ) ) );

	// Auf ~160 Zeichen kürzen, an der letzten Wortgrenze.
	if ( mb_strlen( $desc ) > 160 ) {
		$desc  = mb_substr( $desc, 0, 157 );
		$space = mb_strrpos( $desc, ' ' );
		if ( false !== $space ) {
			$desc = mb_substr( $desc, 0, $space );
		}
		$desc .= '…';
	}

	return $desc;
}

/**
 * Bild für Social-Vorschauen: Beitragsbild, sonst Website-Icon.
 *
 * @return array|null [ url, width, height, alt ] oder null.
 */
function koehlbrand_share_image() {
	$id = 0;

	if ( is_singular() && has_post_thumbnail() ) {
		$id = get_post_thumbnail_id();
	} elseif ( has_site_icon() ) {
		$id = (int) get_option( 'site_icon' );
	}

	if ( ! $id ) {
		return null;
	}

	$src = wp_get_attachment_image_src( $id, 'full' );

	if ( ! $src ) {
		return null;
	}

	return array(
		'url'    => $src[0],
		'width'  => (int) $src[1],
		'height' => (int) $src[2],
		'alt'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
	);
}

/**
 * Description, Canonical, Open Graph und Twitter Cards ausgeben.
 *
 * Priorität 2, damit die Tags weit oben im <head> stehen – manche Crawler
 * lesen nur die ersten Kilobytes.
 */
function koehlbrand_head_meta() {
	if ( is_search() || is_404() ) {
		return; // Kein Canonical/OG auf Ansichten, die ohnehin noindex sind.
	}

	$desc = koehlbrand_meta_description();
	$url  = koehlbrand_current_url();

	if ( '' !== $desc ) {
		printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( $desc ) );
	}

	// Auf Einzelansichten kümmert sich WordPress' rel_canonical() darum.
	if ( ! is_singular() ) {
		printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $url ) );
	}

	printf( "<meta property=\"og:type\" content=\"%s\">\n", is_singular( 'post' ) ? 'article' : 'website' );
	printf( "<meta property=\"og:site_name\" content=\"%s\">\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( "<meta property=\"og:locale\" content=\"%s\">\n", esc_attr( get_locale() ) );
	printf( "<meta property=\"og:url\" content=\"%s\">\n", esc_url( $url ) );
	// Ohne Seitennamen-Suffix: og:site_name liefert den separat, sonst steht
	// der Name in Vorschaukarten doppelt.
	$og_title = is_singular() ? wp_strip_all_tags( get_the_title() ) : wp_get_document_title();
	printf( "<meta property=\"og:title\" content=\"%s\">\n", esc_attr( $og_title ) );

	if ( '' !== $desc ) {
		printf( "<meta property=\"og:description\" content=\"%s\">\n", esc_attr( $desc ) );
	}

	$image = koehlbrand_share_image();

	if ( $image ) {
		printf( "<meta property=\"og:image\" content=\"%s\">\n", esc_url( $image['url'] ) );
		printf( "<meta property=\"og:image:width\" content=\"%d\">\n", $image['width'] );
		printf( "<meta property=\"og:image:height\" content=\"%d\">\n", $image['height'] );
		if ( '' !== $image['alt'] ) {
			printf( "<meta property=\"og:image:alt\" content=\"%s\">\n", esc_attr( $image['alt'] ) );
		}
	}

	if ( is_singular( 'post' ) ) {
		printf( "<meta property=\"article:published_time\" content=\"%s\">\n", esc_attr( get_the_date( 'c' ) ) );
		printf( "<meta property=\"article:modified_time\" content=\"%s\">\n", esc_attr( get_the_modified_date( 'c' ) ) );

		$cats = get_the_category();
		if ( ! empty( $cats ) ) {
			printf( "<meta property=\"article:section\" content=\"%s\">\n", esc_attr( $cats[0]->name ) );
		}
	}

	printf( "<meta name=\"twitter:card\" content=\"%s\">\n", $image ? 'summary_large_image' : 'summary' );
}
add_action( 'wp_head', 'koehlbrand_head_meta', 2 );

/**
 * Robots-Direktiven.
 *
 * Autoren- und Datumsarchive erzeugen bei einem Portal mit einer Handvoll
 * Autoren nur Duplicate Content – auf noindex, Links aber weiterverfolgen.
 *
 * Leere Term-Archive ebenso: WordPress liefert für sie HTTP 200 mit einer
 * Seite ohne Inhalt. Betrifft vor allem die Standardkategorie „Allgemein“,
 * die sich nicht löschen lässt, aber im Portal keine Rolle spielt.
 *
 * Paginierte Archive bleiben bewusst indexierbar: sie auf noindex zu setzen
 * ist verbreitet, aber veraltet – Google verliert dadurch den Zugang zu
 * älteren Artikeln. Stattdessen sorgt koehlbrand_current_url() für ein
 * selbstreferenzierendes Canonical pro Seite.
 */
function koehlbrand_robots( $robots ) {
	if ( is_author() || is_date() || koehlbrand_is_empty_term_archive() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['index'], $robots['nofollow'] );
	}

	// Volle Snippet-Länge und große Bildvorschauen in den Suchergebnissen.
	// Die Werte müssen Strings sein: wp_robots() hängt nur bei String-Werten
	// ein ":wert" an, ein Integer -1 landet als nacktes "max-snippet" im HTML.
	$robots['max-snippet']       = '-1';
	$robots['max-image-preview'] = 'large';
	$robots['max-video-preview'] = '-1';

	return $robots;
}
add_filter( 'wp_robots', 'koehlbrand_robots' );

/**
 * Term-Archiv ohne einen einzigen Beitrag?
 *
 * `count` zählt nur veröffentlichte Beiträge, Entwürfe bleiben außen vor –
 * genau das, was hier gebraucht wird.
 */
function koehlbrand_is_empty_term_archive() {
	if ( ! is_category() && ! is_tag() && ! is_tax() ) {
		return false;
	}

	$term = get_queried_object();

	return $term instanceof WP_Term && 0 === (int) $term->count;
}

/**
 * Autoren-Sitemap entfernen – die Archive sind noindex, also gehören sie
 * auch nicht in die Sitemap.
 */
function koehlbrand_remove_user_sitemap( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'koehlbrand_remove_user_sitemap', 10, 2 );

/**
 * Sitemap-URLs ohne Provider sauber auf 404 setzen.
 *
 * Wird ein Provider entfernt, bricht WP_Sitemaps::render_sitemaps() nur ab,
 * ohne den Status zu setzen – WordPress liefert dann die Startseite mit
 * HTTP 200 aus. Das ist ein Soft-404, den Google als Duplikat der Startseite
 * wertet. Priorität 0, damit es vor dem Sitemap-Renderer greift.
 */
function koehlbrand_404_for_removed_sitemaps() {
	$sitemap = get_query_var( 'sitemap' );

	// "index" ist der Sitemap-Index selbst und hat keinen eigenen Provider.
	if ( empty( $sitemap ) || 'index' === $sitemap ) {
		return;
	}

	$server = wp_sitemaps_get_server();

	if ( $server && $server->registry->get_provider( $sitemap ) ) {
		return;
	}

	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'koehlbrand_404_for_removed_sitemaps', 0 );
