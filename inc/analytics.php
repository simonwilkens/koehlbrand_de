<?php
/**
 * Google Analytics 4 (gtag.js)
 *
 * Reichweitenmessung für koehlbrand.de. Der Tracking-Code steht im Theme und
 * nicht in einem Plugin – aus demselben Grund wie SEO und Werbung: Die Website
 * wird über die REST API automatisiert bespielt, jede zusätzliche Plugin-Ebene
 * wäre ein weiterer Ort, an dem die Pipeline Einstellungen pflegen müsste.
 *
 * **Kein Consent-Banner.** Entscheidung vom 26.07.2026: GA4 läuft in der
 * Entwicklungs- und Testphase bewusst ohne Cookie-Consent. Das ist eine
 * Übergangslösung – vor dem Produktivbetrieb steht ein Wechsel der
 * Tracking-Lösung samt sauberer Consent-Umsetzung an (siehe
 * technik/architektur-plan.md). Wer bis dahin doch eine CMP einhängt: Der
 * Hook `koehlbrand_cmp` (inc/ads.php, wp_head-Priorität 1) läuft vor diesem
 * Snippet, das Consent-Signal ist also vor gtag.js da.
 *
 * Zwei Dinge, die vor dem Livegang trotzdem passen müssen:
 *
 * 1. Die **Datenschutzerklärung** muss GA4 benennen (Verarbeitung, Empfänger,
 *    US-Transfer, Widerspruch). Ihr Abschnitt 5 sagt bislang das Gegenteil –
 *    „derzeit keine Analyse-Werkzeuge" –, deshalb der Backend-Hinweis unten.
 * 2. In der GA4-Property gehört die **IP-Anonymisierung** geprüft; GA4 kürzt
 *    IP-Adressen zwar von sich aus, das ist aber Property-Einstellung und
 *    nichts, was dieses Snippet steuern kann.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mess-ID der GA4-Property ("G-…").
 *
 * Wie die AdSense-Publisher-ID bewusst mit hinterlegtem Standardwert statt nur
 * über eine Option: Die Messung soll laufen, sobald das Theme aktiv ist, und
 * nicht stillschweigend ausfallen, weil beim Aufsetzen ein Options-Eintrag
 * fehlt. Ein Geheimnis ist die ID nicht – sie steht im Quelltext jeder Seite.
 */
define( 'KOEHLBRAND_GA4_MEASUREMENT_ID', 'G-0ZZ7G738S9' );


/**
 * Mess-ID, über Option `koehlbrand_ga4_id` bzw. gleichnamigen Filter
 * überschreibbar. Leerer String = Messung aus (z. B. auf der lokalen
 * Docker-Instanz, deren Aufrufe die Statistik verfälschen würden).
 */
function koehlbrand_ga4_id() {
	$id = trim( (string) get_option( 'koehlbrand_ga4_id', KOEHLBRAND_GA4_MEASUREMENT_ID ) );
	$id = trim( (string) apply_filters( 'koehlbrand_ga4_id', $id ) );

	return preg_match( '/^G-[A-Z0-9]{4,20}$/', $id ) ? $id : '';
}

/**
 * Wird auf diesem Aufruf gemessen?
 *
 * Angemeldete Redakteure werden **nicht** ausgenommen. Solange die Website in
 * der Testphase ist, sind die eigenen Aufrufe die einzigen, die es gibt – eine
 * Ausnahme für Angemeldete hieße, dass in GA4 gar nichts ankommt und die
 * Einbindung fälschlich als kaputt gilt. Sobald echter Traffic läuft, schaltet
 * dieser Filter die eigenen Besuche aus:
 *
 *     add_filter( 'koehlbrand_ga4_enabled', function ( $an ) {
 *         return is_user_logged_in() ? false : $an;
 *     } );
 */
function koehlbrand_ga4_enabled() {
	if ( is_admin() || is_feed() || is_embed() || is_preview() ) {
		return false;
	}

	return (bool) apply_filters( 'koehlbrand_ga4_enabled', '' !== koehlbrand_ga4_id() );
}

/**
 * Das gtag.js-Snippet in den `<head>`.
 *
 * Bewusst direkt ausgegeben statt über `wp_enqueue_script()`: Der Loader ist
 * asynchron, das Konfigurations-Snippet muss aber unmittelbar dahinter stehen.
 * WordPress stuft ein Skript mit angehängtem `after`-Inline-Code auf
 * "blocking" zurück – das `async` fiele also still weg und gtag.js stünde
 * render-blockierend im Kopf.
 *
 * Priorität 3: nach dem CMP-Slot (1) und dem AdSense-Verifizierungs-Tag (2),
 * aber vor `wp_print_head_scripts()` (9).
 */
function koehlbrand_ga4_snippet() {
	if ( ! koehlbrand_ga4_enabled() ) {
		return;
	}

	$id = koehlbrand_ga4_id();

	wp_print_script_tag(
		array(
			'async' => true,
			'src'   => 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $id ),
		)
	);

	echo wp_get_inline_script_tag(
		sprintf(
			"window.dataLayer = window.dataLayer || [];\n" .
			"function gtag(){dataLayer.push(arguments);}\n" .
			"gtag('js', new Date());\n" .
			"gtag('config', '%s');\n",
			esc_js( $id )
		)
	);
}
add_action( 'wp_head', 'koehlbrand_ga4_snippet', 3 );

/**
 * Verbindungsaufbau zu den Tag-Hosts vorziehen – spart pro Host DNS-Lookup und
 * TLS-Handshake, bevor der erste Messtreffer rausgeht. `googletagmanager.com`
 * liefert das Skript, `google-analytics.com` nimmt die Treffer entgegen.
 */
function koehlbrand_ga4_resource_hints( $hints, $relation ) {
	if ( 'preconnect' !== $relation || ! koehlbrand_ga4_enabled() ) {
		return $hints;
	}

	$hints[] = 'https://www.googletagmanager.com';
	$hints[] = 'https://www.google-analytics.com';

	return $hints;
}
add_filter( 'wp_resource_hints', 'koehlbrand_ga4_resource_hints', 10, 2 );

/**
 * Hinweis im Backend, solange die Datenschutzerklärung GA4 nicht benennt.
 *
 * Der Anlass ist konkret: Abschnitt 5 der veröffentlichten Datenschutzerklärung
 * sagte am 27.07.2026 wörtlich, die Website binde „derzeit keine
 * Analyse-Werkzeuge" ein und werde vor deren Aktivierung ergänzt. Mit diesem
 * Snippet ist dieser Satz falsch – und zwar auf einer Seite, die im Frontend
 * völlig normal aussieht, sodass es sonst niemandem auffällt.
 *
 * Geprüft wird auf das Wort „Analytics" im Seiteninhalt: sobald der Abschnitt
 * neu geschrieben ist, verschwindet der Hinweis von selbst. Grob, aber besser
 * als ein Options-Häkchen, das man einmal wegklickt und danach vergisst.
 */
function koehlbrand_ga4_privacy_notice() {
	if ( ! current_user_can( 'manage_options' ) || '' === koehlbrand_ga4_id() ) {
		return;
	}

	$seite = get_page_by_path( 'datenschutz' );

	if ( $seite && 'publish' === $seite->post_status && false !== stripos( $seite->post_content, 'Analytics' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p><strong>Köhlbrand-Theme:</strong> Google Analytics misst, aber die <a href="%s">Datenschutzerklärung</a> benennt GA4 nicht. Sie muss Verarbeitung, Empfänger, US-Transfer und Widerspruch beschreiben – Abschnitt 5 behauptet sonst weiterhin, es seien keine Analyse-Werkzeuge im Einsatz.</p></div>',
		esc_url( $seite ? get_edit_post_link( $seite->ID ) : admin_url( 'edit.php?post_type=page' ) )
	);
}
add_action( 'admin_notices', 'koehlbrand_ga4_privacy_notice' );
