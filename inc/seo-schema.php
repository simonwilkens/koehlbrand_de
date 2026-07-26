<?php
/**
 * SEO – strukturierte Daten (JSON-LD)
 *
 * Ein einziger @graph statt mehrerer Script-Blöcke: so lassen sich Artikel,
 * Website und Herausgeber über @id sauber verknüpfen, und Google muss die
 * Beziehungen nicht raten.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pfad der aktuellen Ansicht als flache Liste.
 *
 * Wird doppelt genutzt: für die sichtbare Brotkrumen-Navigation
 * (inc/breadcrumbs.php) und für das BreadcrumbList-Schema. Der letzte
 * Eintrag ist immer die aktuelle Seite.
 *
 * @return array Liste aus [ 'name' => string, 'url' => string ].
 */
function koehlbrand_breadcrumb_items() {
	$items = array(
		array(
			'name' => 'Start',
			'url'  => home_url( '/' ),
		),
	);

	if ( is_singular( 'post' ) ) {
		$cats = get_the_category();

		if ( ! empty( $cats ) ) {
			$link = get_category_link( $cats[0] );
			if ( ! is_wp_error( $link ) ) {
				$items[] = array(
					'name' => $cats[0]->name,
					'url'  => $link,
				);
			}
		}

		$items[] = array(
			'name' => get_the_title(),
			'url'  => get_permalink(),
		);
	} elseif ( is_page() ) {
		foreach ( array_reverse( get_post_ancestors( get_the_ID() ) ) as $ancestor_id ) {
			$items[] = array(
				'name' => get_the_title( $ancestor_id ),
				'url'  => get_permalink( $ancestor_id ),
			);
		}

		$items[] = array(
			'name' => get_the_title(),
			'url'  => get_permalink(),
		);
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		$link = $term instanceof WP_Term ? get_term_link( $term ) : '';

		if ( $term instanceof WP_Term && ! is_wp_error( $link ) ) {
			$items[] = array(
				'name' => $term->name,
				'url'  => $link,
			);
		}
	} elseif ( is_search() ) {
		$items[] = array(
			'name' => sprintf( 'Suche nach „%s“', get_search_query() ),
			'url'  => koehlbrand_current_url(),
		);
	} elseif ( is_404() ) {
		$items[] = array(
			'name' => 'Seite nicht gefunden',
			'url'  => '',
		);
	}

	return $items;
}

/**
 * Organization-Knoten (Herausgeber).
 */
function koehlbrand_schema_organization( $org_id ) {
	$org = array(
		'@type' => 'Organization',
		'@id'   => $org_id,
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);

	// Nur ein echtes Rasterbild als Logo angeben – Google akzeptiert für
	// das Organization-Logo kein SVG, ein ungültiger Wert kostet das
	// gesamte Rich Result.
	if ( has_site_icon() ) {
		$icon_id = (int) get_option( 'site_icon' );
		$src     = $icon_id ? wp_get_attachment_image_src( $icon_id, 'full' ) : false;

		if ( $src && ! str_ends_with( strtolower( $src[0] ), '.svg' ) ) {
			$org['logo'] = array(
				'@type'  => 'ImageObject',
				'url'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
			);
		}
	}

	return $org;
}

/**
 * Autor-Knoten für Article.
 *
 * Fällt auf den Herausgeber zurück, wenn kein Autor gesetzt ist. Das ist kein
 * theoretischer Fall: über die REST API angelegte Beiträge bekommen ohne
 * ausdrücklich gesetztes post_author die ID 0 – ein leeres author-Feld macht
 * das Article-Rich-Result bei Google ungültig.
 */
function koehlbrand_schema_author( $org_id ) {
	$author_id   = (int) get_post_field( 'post_author' );
	$author_name = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';

	if ( '' !== trim( (string) $author_name ) ) {
		return array(
			'@type' => 'Person',
			'name'  => $author_name,
		);
	}

	return array( '@id' => $org_id );
}

/**
 * Kompletten JSON-LD-Graph ausgeben.
 */
function koehlbrand_json_ld() {
	if ( is_404() ) {
		return;
	}

	$home      = home_url( '/' );
	$org_id    = $home . '#organization';
	$site_id   = $home . '#website';
	$graph     = array();

	$graph[] = koehlbrand_schema_organization( $org_id );

	$graph[] = array(
		'@type'           => 'WebSite',
		'@id'             => $site_id,
		'url'             => $home,
		'name'            => get_bloginfo( 'name' ),
		'description'     => get_bloginfo( 'description' ),
		'publisher'       => array( '@id' => $org_id ),
		'inLanguage'      => get_bloginfo( 'language' ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => $home . '?s={search_term_string}',
			),
			'query-input' => 'required name=search_term_string',
		),
	);

	if ( is_singular( 'post' ) ) {
		$permalink = get_permalink();

		$article = array(
			'@type'            => 'Article',
			'@id'              => $permalink . '#article',
			'isPartOf'         => array( '@id' => $site_id ),
			'mainEntityOfPage' => array( '@id' => $permalink ),
			// Google wertet headline über 110 Zeichen als ungültig.
			'headline'         => mb_substr( wp_strip_all_tags( get_the_title() ), 0, 110 ),
			'datePublished'    => get_the_date( 'c' ),
			'dateModified'     => get_the_modified_date( 'c' ),
			'author'           => koehlbrand_schema_author( $org_id ),
			'publisher'        => array( '@id' => $org_id ),
			'inLanguage'       => get_bloginfo( 'language' ),
		);

		$desc = koehlbrand_meta_description();
		if ( '' !== $desc ) {
			$article['description'] = $desc;
		}

		$image = koehlbrand_share_image();
		if ( $image ) {
			$article['image'] = array(
				'@type'  => 'ImageObject',
				'url'    => $image['url'],
				'width'  => $image['width'],
				'height' => $image['height'],
			);
		}

		$cats = get_the_category();
		if ( ! empty( $cats ) ) {
			$article['articleSection'] = $cats[0]->name;
		}

		// Wortzahl und Lesedauer stammen aus derselben Rechnung wie die Angabe
		// in der Autorenzeile (inc/reading-time.php) – die Suchmaschine sieht
		// damit genau das, was auch auf der Seite steht.
		$woerter = koehlbrand_word_count();
		if ( $woerter > 0 ) {
			$article['wordCount']    = $woerter;
			$article['timeRequired'] = 'PT' . koehlbrand_reading_time() . 'M';
		}

		$graph[] = $article;
	}

	$crumbs = koehlbrand_breadcrumb_items();

	if ( count( $crumbs ) > 1 ) {
		$elements = array();

		foreach ( $crumbs as $i => $crumb ) {
			$element = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $crumb['name'],
			);

			// Der letzte Eintrag ist die aktuelle Seite und bekommt laut
			// Google-Vorgabe kein "item".
			if ( '' !== $crumb['url'] && $i < count( $crumbs ) - 1 ) {
				$element['item'] = $crumb['url'];
			}

			$elements[] = $element;
		}

		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'@id'             => koehlbrand_current_url() . '#breadcrumb',
			'itemListElement' => $elements,
		);
	}

	printf(
		"<script type=\"application/ld+json\">%s</script>\n",
		wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		)
	);
}
add_action( 'wp_head', 'koehlbrand_json_ld', 3 );
