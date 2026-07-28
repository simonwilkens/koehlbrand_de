<?php
/**
 * Sperrungen der Elbquerungen: Datenhaltung und Ausgabe.
 *
 * Warum eine Datenliste und kein gepflegter Text: Ein Kasten in der Seitenleiste,
 * der im November noch den September-Termin zeigt, beschädigt das Vertrauen in
 * die ganze Website mehr, als er nützt. Die Termine stehen deshalb in einer
 * Option, und beide Ausgaben – der Kasten in der Seitenleiste und die Tabelle
 * auf `/sperrungen/` – lesen dieselbe Quelle. Was vorbei ist, verschwindet von
 * selbst.
 *
 * Das ist zugleich der einzige Inhalt dieser Website, den es sonst nirgends
 * gibt: Die Termine der Köhlbrandbrücke stammen von der Hamburg Port Authority,
 * die des Elbtunnels von der Autobahn GmbH. Zusammengeführt nennt sie niemand.
 * Am 11.–14. September 2026 sind beide gleichzeitig gesperrt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Name der Option mit den Sperrterminen.
 */
const KOEHLBRAND_SPERRUNGEN_OPTION = 'koehlbrand_sperrungen';

/**
 * Bekannte Termine, Stand 27.07.2026.
 *
 * Dienen nur der Erstbefüllung. Sobald die Option existiert, wird sie nicht
 * mehr angefasst – ein Theme-Update darf redaktionell gepflegte Termine nicht
 * überschreiben.
 *
 * Zeiten in der Zeitzone der Website. Der Oktober-Termin der Köhlbrandbrücke
 * fehlt bewusst: Er war zu diesem Stand noch nicht veröffentlicht, und ein
 * geratener Termin wäre schlimmer als keiner.
 */
function koehlbrand_sperrungen_vorgabe() {
	return array(
		array(
			'bauwerk' => 'Köhlbrandbrücke',
			'beginn'  => '2026-09-11 21:00',
			'ende'    => '2026-09-14 05:00',
		),
		array(
			'bauwerk' => 'Elbtunnel (A 7)',
			'beginn'  => '2026-09-11 22:00',
			'ende'    => '2026-09-14 05:00',
		),
		array(
			'bauwerk' => 'Köhlbrandbrücke',
			'beginn'  => '2026-09-18 21:00',
			'ende'    => '2026-09-21 05:00',
		),
		array(
			'bauwerk' => 'Elbtunnel (A 7)',
			'beginn'  => '2026-09-25 22:00',
			'ende'    => '2026-09-28 05:00',
		),
	);
}

/**
 * Termine einmalig in die Option schreiben.
 *
 * Läuft aus `koehlbrand_run_setup()`. `false === get_option()` statt `empty()`:
 * Eine bewusst geleerte Liste – „derzeit sind keine Sperrungen bekannt“ – ist
 * eine gültige redaktionelle Aussage und darf nicht wieder mit den Vorgaben
 * überschrieben werden.
 */
function koehlbrand_setup_sperrungen() {
	if ( false === get_option( KOEHLBRAND_SPERRUNGEN_OPTION, false ) ) {
		add_option( KOEHLBRAND_SPERRUNGEN_OPTION, koehlbrand_sperrungen_vorgabe() );
	}
}

/**
 * Alle Termine, aufsteigend nach Beginn, mit Zeitstempeln angereichert.
 *
 * Einträge ohne verwertbares Datum fallen still heraus. Das ist Absicht: Eine
 * halb ausgefüllte Zeile in der Option soll die Ausgabe nicht zerlegen.
 */
function koehlbrand_sperrungen() {
	$roh = get_option( KOEHLBRAND_SPERRUNGEN_OPTION, array() );
	$roh = apply_filters( 'koehlbrand_sperrungen', is_array( $roh ) ? $roh : array() );

	$zone     = wp_timezone();
	$termine  = array();

	foreach ( $roh as $eintrag ) {
		if ( empty( $eintrag['bauwerk'] ) || empty( $eintrag['beginn'] ) || empty( $eintrag['ende'] ) ) {
			continue;
		}

		$beginn = date_create_immutable( (string) $eintrag['beginn'], $zone );
		$ende   = date_create_immutable( (string) $eintrag['ende'], $zone );

		if ( ! $beginn || ! $ende || $ende <= $beginn ) {
			continue;
		}

		$termine[] = array(
			'bauwerk' => (string) $eintrag['bauwerk'],
			'beginn'  => $beginn,
			'ende'    => $ende,
		);
	}

	usort(
		$termine,
		static function ( $a, $b ) {
			return $a['beginn'] <=> $b['beginn'];
		}
	);

	return $termine;
}

/**
 * Termine, die noch nicht vorbei sind.
 *
 * Maßgeblich ist das **Ende**, nicht der Beginn: Während einer laufenden
 * Sperrung ist die Angabe am wichtigsten, nicht am wenigsten.
 */
function koehlbrand_sperrungen_kuenftig() {
	$jetzt = current_datetime();

	return array_values(
		array_filter(
			koehlbrand_sperrungen(),
			static function ( $termin ) use ( $jetzt ) {
				return $termin['ende'] > $jetzt;
			}
		)
	);
}

/**
 * Der nächste anstehende oder laufende Termin, sonst null.
 */
function koehlbrand_naechste_sperrung() {
	$kuenftig = koehlbrand_sperrungen_kuenftig();

	return $kuenftig ? $kuenftig[0] : null;
}

/**
 * Überschneidet sich der Termin zeitlich mit einem anderen Bauwerk?
 *
 * Das ist der eigentliche Grund für dieses Widget. Fällt eine Sperrung der
 * Köhlbrandbrücke mit einer des Elbtunnels zusammen, gibt es für den
 * Schwerlastverkehr keine Ausweichroute mehr – und genau diese Konstellation
 * nennt keine der beiden Quellen, weil jede nur ihr eigenes Bauwerk kennt.
 *
 * Rückgabe: Name des anderen Bauwerks oder leerer String.
 */
function koehlbrand_sperrung_kollision( $termin ) {
	foreach ( koehlbrand_sperrungen() as $anderer ) {
		if ( $anderer['bauwerk'] === $termin['bauwerk'] ) {
			continue;
		}

		if ( $anderer['beginn'] < $termin['ende'] && $anderer['ende'] > $termin['beginn'] ) {
			return $anderer['bauwerk'];
		}
	}

	return '';
}

/**
 * Zeitraum als Text: „Fr 11.09., 21:00 – Mo 14.09., 05:00“.
 */
function koehlbrand_sperrung_zeitraum( $termin ) {
	return sprintf(
		'%s – %s',
		wp_date( 'D d.m., H:i', $termin['beginn']->getTimestamp() ),
		wp_date( 'D d.m., H:i', $termin['ende']->getTimestamp() )
	);
}

/**
 * URL der Sperrungsseite, für die Verlinkung aus dem Kasten.
 */
function koehlbrand_sperrungen_url() {
	$seite = get_page_by_path( 'sperrungen' );

	return $seite && 'publish' === $seite->post_status ? get_permalink( $seite ) : '';
}


/* -------------------------------------------------------------------------
 * Blöcke
 * ---------------------------------------------------------------------- */

/**
 * Beide Blöcke registrieren.
 */
function koehlbrand_register_closure_blocks() {
	foreach ( array( 'next-closure', 'closure-table' ) as $name ) {
		wp_register_script(
			'koehlbrand-' . $name . '-editor',
			get_theme_file_uri( 'blocks/' . $name . '/index.js' ),
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ),
			wp_get_theme()->get( 'Version' ),
			true
		);

		register_block_type(
			get_theme_file_path( 'blocks/' . $name ),
			array( 'render_callback' => 'koehlbrand_render_' . str_replace( '-', '_', $name ) . '_block' )
		);
	}
}
add_action( 'init', 'koehlbrand_register_closure_blocks' );

/**
 * Kasten „Nächste Vollsperrung“ für die Seitenleiste.
 *
 * **Ohne künftigen Termin rendert der Block nichts.** Kein leerer Kasten, kein
 * „Stand unbekannt“ – eine Überschrift ohne Inhalt sieht nach einem Fehler aus
 * und wirft die Frage auf, ob die Seite überhaupt gepflegt wird.
 */
function koehlbrand_render_next_closure_block( $attributes = array(), $content = '', $block = null ) {
	$termin = koehlbrand_naechste_sperrung();

	if ( ! $termin ) {
		return '';
	}

	$laeuft    = $termin['beginn'] <= current_datetime();
	$kollision = koehlbrand_sperrung_kollision( $termin );
	$url       = koehlbrand_sperrungen_url();
	$wrapper   = get_block_wrapper_attributes( array( 'class' => 'koehlbrand-closure' ) );

	$innen  = sprintf(
		'<p class="koehlbrand-closure__label">%s</p>',
		esc_html( $laeuft ? 'Aktuell gesperrt' : 'Nächste Vollsperrung' )
	);
	$innen .= sprintf(
		'<p class="koehlbrand-closure__bauwerk">%s</p>',
		esc_html( $termin['bauwerk'] )
	);
	$innen .= sprintf(
		'<p class="koehlbrand-closure__zeit"><time datetime="%s">%s</time></p>',
		esc_attr( $termin['beginn']->format( DATE_W3C ) ),
		esc_html( koehlbrand_sperrung_zeitraum( $termin ) )
	);

	if ( '' !== $kollision ) {
		$innen .= sprintf(
			'<p class="koehlbrand-closure__warnung">Gleichzeitig gesperrt: %s. Für den Schwerlastverkehr bleibt dann keine Ausweichroute.</p>',
			esc_html( $kollision )
		);
	}

	if ( '' !== $url ) {
		$innen .= sprintf(
			'<p class="koehlbrand-closure__mehr"><a href="%s">Alle Termine und Umleitungen</a></p>',
			esc_url( $url )
		);
	}

	return sprintf( '<aside %s>%s</aside>', $wrapper, $innen );
}

/**
 * Tabelle aller künftigen Termine – für `/sperrungen/`.
 *
 * Speist sich aus derselben Option wie der Kasten. Genau das war die Auflage:
 * eine Datenquelle statt zweier, die auseinanderlaufen können. Der wöchentliche
 * Pflegeschritt beschränkt sich damit auf die Option.
 */
function koehlbrand_render_closure_table_block( $attributes = array(), $content = '', $block = null ) {
	$termine = koehlbrand_sperrungen_kuenftig();
	$wrapper = get_block_wrapper_attributes( array( 'class' => 'koehlbrand-closure-table' ) );

	if ( ! $termine ) {
		return sprintf(
			'<p %s>Derzeit sind keine Vollsperrungen angekündigt. Diese Übersicht wird wöchentlich geprüft.</p>',
			$wrapper
		);
	}

	$zeilen = '';

	foreach ( $termine as $termin ) {
		$kollision = koehlbrand_sperrung_kollision( $termin );
		$hinweis   = '' !== $kollision
			? sprintf( '<br><span class="koehlbrand-closure-table__warnung">gleichzeitig: %s</span>', esc_html( $kollision ) )
			: '';

		$zeilen .= sprintf(
			'<tr><td>%s</td><td><time datetime="%s">%s</time>%s</td></tr>',
			esc_html( $termin['bauwerk'] ),
			esc_attr( $termin['beginn']->format( DATE_W3C ) ),
			esc_html( koehlbrand_sperrung_zeitraum( $termin ) ),
			$hinweis
		);
	}

	return sprintf(
		'<figure %s><table><caption>Angekündigte Vollsperrungen, wöchentlich geprüft</caption>' .
		'<thead><tr><th scope="col">Bauwerk</th><th scope="col">Zeitraum</th></tr></thead>' .
		'<tbody>%s</tbody></table></figure>',
		$wrapper,
		$zeilen
	);
}
