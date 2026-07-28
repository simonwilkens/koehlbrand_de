<?php
/**
 * Einwilligung für die Reichweitenmessung.
 *
 * Ersetzt die Übergangslösung vom 26.07.2026, mit der Google Analytics ohne
 * Einwilligung lief. Seit die Website mit echten Beiträgen öffentlich ist, ist
 * die Entwicklungsphase vorbei, auf die sich diese Entscheidung stützte.
 *
 * Was dieses Modul leistet – und was nicht:
 *
 * - Es holt eine Einwilligung für **Google Analytics 4** ein, § 25 Abs. 1
 *   TDDDG in Verbindung mit Art. 6 Abs. 1 lit. a DSGVO. Ohne Einwilligung wird
 *   gtag.js nicht geladen; es geht kein Aufruf an Google raus.
 * - Es ist **keine zertifizierte CMP nach TCF 2.2**. Für AdSense im EWR
 *   verlangt Google genau die, und zwar unabhängig davon, was hier steht. Der
 *   Hook `koehlbrand_cmp` (inc/ads.php) bleibt dafür der vorgesehene Platz;
 *   eine dort eingehängte CMP kann über den Filter `koehlbrand_consent_state`
 *   die Hoheit über das Signal übernehmen.
 *
 * Zwei Gestaltungsentscheidungen, die aus der Rechtslage folgen und nicht aus
 * dem Geschmack:
 *
 * 1. **Ablehnen ist genauso leicht wie Annehmen.** Beide Schaltflächen sind
 *    gleich groß, gleich prominent und auf derselben Ebene. Ein „Ablehnen“, das
 *    kleiner, grauer oder eine Klickebene tiefer liegt, gilt der
 *    Datenschutzkonferenz als unwirksame Einwilligung.
 * 2. **Kein Wegklicken ohne Entscheidung.** Es gibt kein X und kein
 *    Schließen per Escape – aber auch keinen Fokus-Käfig: Wer das Banner
 *    ignoriert, kann die Seite normal lesen. Ohne Entscheidung wird nicht
 *    gemessen, das Ignorieren ist also die datensparsame Variante.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Name des Einwilligungs-Cookies.
 *
 * Das Cookie selbst ist nach § 25 Abs. 2 Nr. 2 TDDDG einwilligungsfrei: Es
 * speichert ausschließlich die Entscheidung, ist unbedingt erforderlich, um sie
 * umzusetzen, und enthält keine Kennung.
 */
const KOEHLBRAND_CONSENT_COOKIE = 'koehlbrand_consent';

/**
 * Gültigkeit der gespeicherten Entscheidung in Tagen.
 *
 * Sechs Monate: lange genug, um nicht bei jedem Besuch zu fragen, kurz genug,
 * dass eine Einwilligung nicht unbegrenzt weiterwirkt.
 */
const KOEHLBRAND_CONSENT_DAYS = 182;

/**
 * Aktueller Einwilligungsstand: `granted`, `denied` oder `unknown`.
 *
 * Über den gleichnamigen Filter übernimmt eine später eingehängte CMP das
 * Signal, ohne dass hier etwas geändert werden muss.
 */
function koehlbrand_consent_state() {
	$roh = isset( $_COOKIE[ KOEHLBRAND_CONSENT_COOKIE ] )
		? sanitize_key( wp_unslash( $_COOKIE[ KOEHLBRAND_CONSENT_COOKIE ] ) )
		: '';

	$stand = in_array( $roh, array( 'granted', 'denied' ), true ) ? $roh : 'unknown';

	$stand = (string) apply_filters( 'koehlbrand_consent_state', $stand );

	return in_array( $stand, array( 'granted', 'denied' ), true ) ? $stand : 'unknown';
}

/**
 * Darf auf diesem Aufruf gemessen werden?
 */
function koehlbrand_consent_granted() {
	return 'granted' === koehlbrand_consent_state();
}

/**
 * Soll das Banner überhaupt ausgeliefert werden?
 *
 * Ohne Mess-ID gibt es nichts, wozu eingewilligt werden könnte – dann ist ein
 * Banner nicht nur überflüssig, sondern irreführend. Genau das ist der Fall auf
 * der lokalen Docker-Instanz, deren Mess-ID leer gesetzt ist.
 */
function koehlbrand_consent_aktiv() {
	if ( is_admin() || is_feed() || is_embed() ) {
		return false;
	}

	return (bool) apply_filters( 'koehlbrand_consent_aktiv', '' !== koehlbrand_ga4_id() );
}

/**
 * Banner und Steuer-Skript im Fußbereich.
 *
 * Das Markup wird **immer** ausgegeben, auch nach getroffener Entscheidung:
 * Der Link „Cookie-Einstellungen“ im Fußbereich öffnet denselben Dialog erneut,
 * damit sich eine Einwilligung genauso leicht widerrufen wie erteilen lässt
 * (Art. 7 Abs. 3 DSGVO). Sichtbar ist es nur, wenn es gebraucht wird.
 */
function koehlbrand_consent_banner() {
	if ( ! koehlbrand_consent_aktiv() ) {
		return;
	}

	$offen        = 'unknown' === koehlbrand_consent_state();
	$datenschutz  = get_page_by_path( 'datenschutz' );
	$datenschutz  = $datenschutz ? get_permalink( $datenschutz ) : '';
	?>
	<div class="koehlbrand-consent" id="koehlbrand-consent" role="dialog"
		aria-modal="false" aria-labelledby="koehlbrand-consent-titel"
		data-ga-id="<?php echo esc_attr( koehlbrand_ga4_id() ); ?>"
		data-cookie="<?php echo esc_attr( KOEHLBRAND_CONSENT_COOKIE ); ?>"
		data-tage="<?php echo esc_attr( (string) KOEHLBRAND_CONSENT_DAYS ); ?>"
		<?php echo $offen ? '' : 'hidden'; ?>>
		<div class="koehlbrand-consent__box">
			<h2 class="koehlbrand-consent__titel" id="koehlbrand-consent-titel">Dürfen wir messen, wie diese Seite genutzt wird?</h2>
			<p class="koehlbrand-consent__text">
				Wir würden gern Google Analytics einsetzen, um zu sehen, welche Beiträge
				gelesen werden. Dafür werden Cookies gesetzt und Daten an Google
				übertragen, auch in die USA. Das ist für die Nutzung dieser Website
				nicht nötig – wenn Sie ablehnen, funktioniert alles unverändert, und es
				wird nichts geladen.
				<?php if ( '' !== $datenschutz ) : ?>
					Einzelheiten in der <a href="<?php echo esc_url( $datenschutz ); ?>">Datenschutzerklärung</a>.
				<?php endif; ?>
			</p>
			<div class="koehlbrand-consent__knoepfe">
				<button type="button" class="koehlbrand-consent__knopf" data-consent="denied">Ablehnen</button>
				<button type="button" class="koehlbrand-consent__knopf" data-consent="granted">Einverstanden</button>
			</div>
			<p class="koehlbrand-consent__fuss">Die Entscheidung gilt sechs Monate und lässt sich jederzeit über „Cookie-Einstellungen“ im Fußbereich ändern.</p>
		</div>
	</div>
	<?php
	echo wp_get_inline_script_tag( koehlbrand_consent_script() );
}
add_action( 'wp_footer', 'koehlbrand_consent_banner' );

/**
 * Steuer-Skript.
 *
 * Warum gtag.js hier und nicht mehr serverseitig im `<head>` geladen wird: Es
 * gibt genau einen Ort, an dem über das Laden entschieden wird. Würde der
 * Server das Snippet abhängig vom Cookie ausgeben, entschiede bei einer
 * zwischengespeicherten Seite der Zustand des ersten Besuchers über alle
 * folgenden – ein Fehler, den man nicht sieht und der in beide Richtungen
 * falsch läuft.
 */
function koehlbrand_consent_script() {
	return <<<'JS'
( function () {
	'use strict';

	var dialog = document.getElementById( 'koehlbrand-consent' );
	if ( ! dialog ) { return; }

	var cookieName = dialog.dataset.cookie;
	var gaId       = dialog.dataset.gaId;
	var tage       = parseInt( dialog.dataset.tage, 10 ) || 182;

	function lesen() {
		var treffer = document.cookie.match(
			new RegExp( '(?:^|; )' + cookieName + '=([^;]*)' )
		);
		return treffer ? decodeURIComponent( treffer[ 1 ] ) : '';
	}

	function schreiben( wert ) {
		var ablauf = new Date( Date.now() + tage * 864e5 ).toUTCString();
		document.cookie = cookieName + '=' + wert + ';expires=' + ablauf +
			';path=/;SameSite=Lax' +
			( 'https:' === location.protocol ? ';Secure' : '' );
	}

	/**
	 * Von Google Analytics gesetzte Cookies entfernen.
	 *
	 * Zwei Fälle, in denen das gebraucht wird: der Widerruf einer erteilten
	 * Einwilligung – und Besucher, die vor Einführung dieses Banners auf der
	 * Website waren, als noch ohne Einwilligung gemessen wurde. Deren `_ga`-
	 * Cookies liegen sonst bis zu zwei Jahre weiter im Browser, obwohl gerade
	 * „Ablehnen“ geklickt wurde.
	 *
	 * Gelöscht wird für den aktuellen Host und für die um eine Ebene gekürzte
	 * Domain, weil GA das Cookie auf der registrierbaren Domain ablegt.
	 */
	function analyticsCookiesLoeschen() {
		var teile   = location.hostname.split( '.' );
		var domains = [ location.hostname, '.' + location.hostname ];

		if ( teile.length > 2 ) {
			domains.push( '.' + teile.slice( -2 ).join( '.' ) );
		}

		document.cookie.split( '; ' ).forEach( function ( eintrag ) {
			var name = eintrag.split( '=' )[ 0 ];
			if ( 0 !== name.indexOf( '_ga' ) && 0 !== name.indexOf( '_gid' ) ) { return; }

			domains.forEach( function ( domain ) {
				document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT' +
					';path=/;domain=' + domain;
			} );
			document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
		} );
	}

	var geladen = false;

	function analyticsLaden() {
		if ( geladen || ! gaId ) { return; }
		geladen = true;

		var s = document.createElement( 'script' );
		s.async = true;
		s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent( gaId );
		document.head.appendChild( s );

		window.dataLayer = window.dataLayer || [];
		window.gtag = function () { window.dataLayer.push( arguments ); };
		window.gtag( 'js', new Date() );
		window.gtag( 'config', gaId );
	}

	function zeigen() {
		dialog.hidden = false;
		var knopf = dialog.querySelector( '.koehlbrand-consent__knopf' );
		if ( knopf ) { knopf.focus(); }
	}

	dialog.addEventListener( 'click', function ( ereignis ) {
		var knopf = ereignis.target.closest( '[data-consent]' );
		if ( ! knopf ) { return; }

		var wahl = knopf.dataset.consent;
		schreiben( wahl );
		dialog.hidden = true;

		if ( 'granted' === wahl ) {
			analyticsLaden();
			return;
		}

		analyticsCookiesLoeschen();

		if ( geladen ) {
			// Bereits geladenes gtag.js lässt sich nicht zurücknehmen. Ein
			// Neuaufbau der Seite ist der einzige verlässliche Weg, den
			// Widerruf sofort wirksam zu machen.
			location.reload();
		}
	} );

	// Widerruf und nachträgliche Zustimmung über den Fußbereich.
	document.addEventListener( 'click', function ( ereignis ) {
		var ausloeser = ereignis.target.closest( 'a[href$="#cookie-einstellungen"]' );
		if ( ! ausloeser ) { return; }
		ereignis.preventDefault();
		zeigen();
	} );

	if ( 'granted' === lesen() ) {
		analyticsLaden();
	}
}() );
JS;
}
