<?php
/**
 * Google Analytics 4 (gtag.js)
 *
 * Reichweitenmessung für koehlbrand.de. Der Tracking-Code steht im Theme und
 * nicht in einem Plugin – aus demselben Grund wie SEO und Werbung: Die Website
 * wird über die REST API automatisiert bespielt, jede zusätzliche Plugin-Ebene
 * wäre ein weiterer Ort, an dem die Pipeline Einstellungen pflegen müsste.
 *
 * **Das Laden übernimmt seit v1.6.0 `inc/consent.php`.** Diese Datei entscheidet
 * nur noch, *ob* eine Messung überhaupt in Frage kommt (gültige Mess-ID,
 * passender Seitentyp); geladen wird gtag.js ausschließlich nach erteilter
 * Einwilligung, und zwar clientseitig. Der frühere Head-Ausdruck ist ersatzlos
 * entfallen – er hätte bei einer zwischengespeicherten Seite den Zustand des
 * ersten Besuchers an alle folgenden weitergegeben.
 *
 * Offen bleibt: In der GA4-Property gehört die **IP-Anonymisierung** geprüft;
 * GA4 kürzt IP-Adressen zwar von sich aus, das ist aber Property-Einstellung
 * und nichts, was das Theme steuern kann.
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
 * Kein Preconnect zu den Google-Hosts mehr.
 *
 * Ein `preconnect` ist kein bloßer Hinweis: Der Browser baut die Verbindung
 * tatsächlich auf, samt DNS-Auflösung und TLS-Handshake – und überträgt dabei
 * die IP-Adresse an Google. Vor einer Einwilligung ist das genau das, was nicht
 * passieren darf. Nach der Einwilligung wäre es zulässig, brächte aber wenig:
 * Das Skript wird ohnehin erst durch das Steuer-Skript im Fußbereich angefragt,
 * der Vorlauf zum Verbindungsaufbau ist dann längst verstrichen.
 *
 * Deshalb ersatzlos gestrichen. Die Funktion bleibt als Notiz stehen, damit die
 * Überlegung nicht in sechs Monaten als „vergessen“ nachgeholt wird.
 */

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
