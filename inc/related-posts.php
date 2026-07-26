<?php
/**
 * Empfehlungen am Artikelende.
 *
 * Vorher: „die drei neuesten Beiträge derselben Rubrik“. Das ist bei vier
 * Rubriken und wachsendem Archiv schnell beliebig – der aktuellste Beitrag der
 * Rubrik hat mit dem gerade gelesenen Artikel oft nichts zu tun, und in einer
 * dünn besetzten Rubrik blieb die Reihe halb leer.
 *
 * Jetzt: eine Rangfolge aus gemeinsamen Schlagwörtern (das genaueste Signal,
 * das die Redaktion setzt), gemeinsamen Rubriken und Aktualität. Reicht das
 * nicht für die volle Reihe, wird mit aktuellen Beiträgen aufgefüllt, damit
 * unter jedem Artikel drei Karten stehen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gewichtung der Signale.
 *
 * Ein gemeinsames Schlagwort wiegt schwerer als eine gemeinsame Rubrik: die
 * Rubrik teilen sich alle Beiträge des Ressorts, das Schlagwort nur die zum
 * selben Vorgang. Der Aktualitätsbonus liegt bewusst unter 1 – er entscheidet
 * nur bei gleichem Themenbezug, kippt aber nie einen Treffer mit mehr
 * gemeinsamen Schlagwörtern.
 */
function koehlbrand_related_weights() {
	return apply_filters(
		'koehlbrand_related_weights',
		array(
			'tag'      => 3.0,
			'category' => 2.0,
			'recency'  => 1.0,
		)
	);
}

/**
 * Wie viele Kandidaten überhaupt bewertet werden.
 *
 * Die Bewertung läuft in PHP, deshalb ein Deckel: 40 Beiträge sind genug, um
 * bei einem Archiv dieser Größe die passenden zu finden, und kosten eine
 * Query mit einem Bruchteil einer Millisekunde.
 */
function koehlbrand_related_pool_size() {
	return max( 10, (int) apply_filters( 'koehlbrand_related_pool_size', 40 ) );
}

/**
 * IDs der empfohlenen Beiträge, absteigend nach Relevanz.
 *
 * @param int $post_id Aktueller Beitrag.
 * @param int $anzahl  Gewünschte Anzahl.
 * @return int[]
 */
function koehlbrand_related_post_ids( $post_id, $anzahl = 3 ) {
	static $cache = array();

	$post_id = (int) $post_id;
	$anzahl  = max( 1, (int) $anzahl );
	$key     = $post_id . ':' . $anzahl;

	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$tags = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );
	$cats = wp_get_post_categories( $post_id );

	$gewicht  = koehlbrand_related_weights();
	$treffer  = array();
	$tax_args = array();

	if ( ! empty( $tags ) ) {
		$tax_args[] = array(
			'taxonomy' => 'post_tag',
			'field'    => 'term_id',
			'terms'    => $tags,
		);
	}

	if ( ! empty( $cats ) ) {
		$tax_args[] = array(
			'taxonomy' => 'category',
			'field'    => 'term_id',
			'terms'    => $cats,
		);
	}

	if ( ! empty( $tax_args ) ) {
		if ( count( $tax_args ) > 1 ) {
			$tax_args['relation'] = 'OR';
		}

		$kandidaten = get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => koehlbrand_related_pool_size(),
				'post__not_in'        => array( $post_id ),
				'tax_query'           => $tax_args, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Auf drei Karten am Artikelende beschränkt, mit Deckel.
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => false,
			)
		);

		$jetzt = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Nur für eine Altersdifferenz in Tagen.

		foreach ( $kandidaten as $kandidat ) {
			$gemeinsame_tags = count( array_intersect( $tags, wp_get_post_tags( $kandidat->ID, array( 'fields' => 'ids' ) ) ) );
			$gemeinsame_cats = count( array_intersect( $cats, wp_get_post_categories( $kandidat->ID ) ) );

			// Alter in Tagen, linear auf ein Jahr abgeschmolzen: ein Beitrag von
			// heute bekommt den vollen Bonus, einer von vor einem Jahr keinen.
			$alter_tage = max( 0, ( $jetzt - get_post_timestamp( $kandidat ) ) / DAY_IN_SECONDS );
			$aktualitaet = max( 0, 1 - ( $alter_tage / 365 ) );

			$punkte = ( $gemeinsame_tags * $gewicht['tag'] )
				+ ( $gemeinsame_cats * $gewicht['category'] )
				+ ( $aktualitaet * $gewicht['recency'] );

			if ( $punkte <= 0 ) {
				continue;
			}

			$treffer[] = array(
				'id'     => $kandidat->ID,
				'punkte' => $punkte,
				'datum'  => get_post_timestamp( $kandidat ),
			);
		}

		// Gleichstand nach Datum auflösen – usort ist erst ab PHP 8.0 stabil,
		// das Theme läuft laut style.css ab 7.4.
		usort(
			$treffer,
			function ( $a, $b ) {
				if ( $a['punkte'] === $b['punkte'] ) {
					return $b['datum'] <=> $a['datum'];
				}
				return $b['punkte'] <=> $a['punkte'];
			}
		);
	}

	$ids = array_slice( wp_list_pluck( $treffer, 'id' ), 0, $anzahl );

	// Auffüllen, falls Schlagwörter und Rubrik nicht genug hergeben – etwa bei
	// den ersten Artikeln einer neuen Rubrik.
	if ( count( $ids ) < $anzahl ) {
		$auffuellen = get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => $anzahl - count( $ids ),
				'post__not_in'        => array_merge( array( $post_id ), $ids ),
				'orderby'             => 'date',
				'order'               => 'DESC',
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		$ids = array_merge( $ids, $auffuellen );
	}

	$ids = array_map( 'intval', apply_filters( 'koehlbrand_related_post_ids', $ids, $post_id, $anzahl ) );

	$cache[ $key ] = $ids;

	return $ids;
}

/**
 * Query-Vars des Empfehlungs-Loops setzen.
 *
 * Wird aus koehlbrand_query_loop_vars() (functions.php) aufgerufen, sobald am
 * post-template die Klasse `koehlbrand-related-posts` steht.
 */
function koehlbrand_related_query_vars( $query ) {
	$post_id = get_the_ID();
	$anzahl  = isset( $query['posts_per_page'] ) ? (int) $query['posts_per_page'] : 3;

	$ids = koehlbrand_related_post_ids( $post_id, $anzahl );

	if ( empty( $ids ) ) {
		// Einziger Fall: Es gibt außer diesem Beitrag keinen weiteren. Dann
		// greift der query-no-results-Block im Template.
		$query['post__in'] = array( 0 );

		return $query;
	}

	$query['post__in']            = $ids;
	$query['orderby']             = 'post__in';
	$query['ignore_sticky_posts'] = true;

	return $query;
}
