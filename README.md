# Köhlbrand WordPress-Theme

Block-Theme (Full Site Editing) für koehlbrand.de, gebaut nach den Marken-Guidelines
in `branding/koehlbrand-brand-guidelines.md` und `branding/design-tokens.css`.

## Installation und Updates (WordPress-Multisite)

koehlbrand.de ist eine Site in einem Multisite-Netzwerk mit rund neun
Auftritten. Themes liegen dort netzwerkweit in `wp-content/themes/`; eine
einzelne Site kann ein Theme aktivieren, aber weder installieren noch
aktualisieren. Beides macht ausschließlich der Netzwerk-Admin.

**Installieren (als Super Admin):**

1. **Netzwerkverwaltung → Themes → Theme hinzufügen → Theme hochladen**,
   ZIP auswählen, installieren. Das ZIP muss den Ordner `koehlbrand-theme`
   als Wurzel enthalten.
2. Das Theme für koehlbrand.de freigeben – entweder netzwerkweit aktivieren
   oder in den Einstellungen dieser einen Site.
3. Im Backend von koehlbrand.de unter **Design → Themes** aktivieren.

**Aktualisieren:** derselbe Weg. Neues ZIP über die Netzwerkverwaltung
hochladen, WordPress fragt beim Überschreiben nach und zeigt beide Versionen
an. Vorher lokal im Docker prüfen (siehe `technik/architektur-plan.md`) – ein
Theme-Update trifft in einer Multisite jede Site, die das Theme nutzt.

### Warum kein automatischer Update-Mechanismus

Bewusste Entscheidung vom 27.07.2026. Der Code liegt in
**`simonwilkens/koehlbrand_de`** (öffentlich, Theme im Repo-Root), und das
Repo ist so aufgebaut, dass Git Updater damit arbeiten könnte. Eingerichtet
ist es trotzdem nicht:

- Git Updater ist ein **Network-Only-Plugin** (`Network: true` im Header) und
  lässt sich nur netzwerkweit aktivieren. Es hängt sich dann in
  `site_transient_update_themes`, `site_transient_update_plugins` und
  `upgrader_source_selection` ein – also in die Hooks, über die jedes Update
  aller neun Sites läuft. Auf welche Themes es zugreift, steuert allein der
  Header `GitHub Theme URI` / `Update URI`; eine Beschränkung auf ein
  bestimmtes Theme gibt es nicht.
- Der Server läuft auf **PHP 7.4**. Git Updater verlangt seit 12.10.0
  (29.01.2025) PHP 8.0; nutzbar wäre nur die eingefrorene **12.9.0** vom
  08.01.2025 – ohne Sicherheitsupdates, mit Schreibrechten aufs Dateisystem.

Das Risiko für acht fremde Websites wiegt schwerer als der gesparte
ZIP-Upload. Aus demselben Grund trägt der `style.css`-Header **keinen**
`Update URI` und **kein** `GitHub Theme URI`: Git Updater ist im Netzwerk
installiert, nur nicht aktiviert – mit den Headern würde es dieses Theme
automatisch erfassen, sobald es jemand für ein anderes Projekt aktiviert.

**Wenn das später doch automatisch laufen soll**, ist
[Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
(MIT, PHP ≥ 5.6.20, läuft also auf 7.4) der passendere Weg: als mu-plugin
eingebunden und namentlich auf `koehlbrand-theme` konfiguriert, wirkt er
ausschließlich auf dieses eine Theme.

### Versionen und Tags

Auch ohne Update-Mechanismus gilt: **Version im `style.css`-Header hochzählen
und einen Git-Tag mit derselben Nummer setzen** (`v1.2.1` → Version `1.2.1`).
Nur so lässt sich später nachvollziehen, welcher Stand auf dem Server liegt –
und ein automatischer Mechanismus ließe sich jederzeit ohne Aufräumarbeit
nachrüsten.

> **Keine Zugangsdaten ins Repo.** `branding/`, `technik/` und
> `docker-compose.yml` aus dem Projektordner gehören bewusst **nicht** hierher.
> Das Repo ist öffentlich, und ein künftiger Update-Mechanismus würde seinen
> Inhalt nach `wp-content/themes/` auf den Server entpacken – über HTTP
> erreichbar.

## Läuft automatisch bei der Aktivierung

`koehlbrand_run_setup()` in `functions.php` erledigt einmalig:

- **Rubriken anlegen** – `neubau`, `hafenwirtschaft`, `ausflugstipps`,
  `fototipps` (bestehende Kategorien werden nicht verändert)
- **Sprechende URLs** – Permalink-Struktur `/%postname%/`, Kategorie-Basis
  `kategorie`, Schlagwort-Basis `thema`. Die Kategorie-Basis ist zwingend, weil
  Header und Footer fest auf `/kategorie/<slug>/` verlinken. Eine bereits
  gesetzte Permalink-Struktur wird respektiert.
- **Pflichtseiten als Entwurf** – Impressum, Datenschutz, Kontakt. Inhalte
  müssen redaktionell/juristisch gefüllt und veröffentlicht werden; solange
  das nicht passiert ist, zeigt das Backend einen Hinweis.
- **Kommentare schließen** – Standardstatus für neue Beiträge auf „closed“,
  bestehende Beiträge und Seiten werden mitgeschlossen.

## Keine Leserkommentare

Das Portal führt bewusst keine Kommentare. Abgeschaltet ist das auf mehreren
Ebenen (`functions.php`), damit weder ein Template noch ein Plugin noch ein
direkter POST auf `wp-comments-post.php` ein Schlupfloch lässt: `comments_open`
und `pings_open` liefern immer `false`, vorhandene Kommentare werden nicht
geladen, Kommentar-Feeds antworten mit 404, die Kommentar-Unterstützung ist von
allen Inhaltstypen entfernt, und Menüpunkt, Dashboard-Widget sowie die
Diskussions-Einstellungen sind im Backend nicht erreichbar.

Soll das je zurückgenommen werden, reicht es **nicht**, die Filter zu
entfernen – dann fehlt immer noch das Markup in `templates/single.html`, und
es müssten Moderation, Spamschutz und die DSGVO-Seite (IP-Speicherung,
Einwilligung) geklärt sein.

> **Server-Voraussetzung:** Für sprechende URLs muss Apache die `.htaccess`
> auswerten (`AllowOverride All`). Das offizielle `wordpress:latest`-Image
> setzt `AllowOverride None` – die lokale Testumgebung mountet deshalb
> `technik/apache-permalinks.conf`. Auf dem Live-Server vorher prüfen.

## Danach noch manuell

1. **Logo setzen:** Design → Editor → Design (Styles) → Logo hochladen. Für
   das horizontale Logo (`branding/koehlbrand-logo-horizontal.svg`) muss der
   SVG-Upload aktiviert sein – WordPress blockiert SVGs standardmäßig aus
   Sicherheitsgründen. Empfehlung: Plugin „Safe SVG“ installieren, oder das
   Logo einmalig als PNG exportieren.
2. **Doppelte Datenschutzseite:** WordPress legt selbst einen Entwurf
   „Datenschutzerklärung“ (`datenschutzerklaerung`) an. Der Footer verlinkt auf
   `/datenschutz/` – den WP-Standardentwurf also entweder löschen oder unter
   Einstellungen → Datenschutz auf die richtige Seite umstellen.
3. **Favicon:** Solange unter Design → Website-Icon kein eigenes Icon gesetzt
   ist, verwendet das Theme automatisch `koehlbrand-logo-icon.svg` als
   Favicon (siehe `functions.php`). Für ein "offizielles" Website-Icon in den
   WP-Einstellungen wird ein PNG (mind. 512×512 px) benötigt.
4. **Startseite:** Einstellungen → Lesen → „Eine statische Seite anzeigen“ ist
   nicht nötig – `templates/front-page.html` greift automatisch, solange
   unter Einstellungen → Lesen „Deine letzten Beiträge“ eingestellt ist.

## Werbeplätze (`inc/ads.php`)

### Site-Verifizierung (unabhängig von der Auslieferung)

Das Theme setzt auf jeder Seite den Meta-Tag, mit dem Google die Website dem
AdSense-Konto zuordnet:

```html
<meta name="google-adsense-account" content="ca-pub-9359612609141957">
```

Die ID steht als Standardwert in `KOEHLBRAND_ADSENSE_PUBLISHER_ID` – anders als
die übrige Ad-Konfiguration, die über Optionen läuft. Grund: Der Tag muss stehen,
damit Google überhaupt freischaltet; hinge er an einem Options-Eintrag, den
jemand beim Aufsetzen vergisst, scheiterte die Freischaltung stillschweigend.
Ein Geheimnis ist die ID nicht, sie steht im Quelltext jeder Seite. Überschreiben
geht per Option `koehlbrand_adsense_publisher_id` oder gleichnamigem Filter; ein
leerer Wert schaltet den Tag ab (z. B. auf einer Staging-Instanz, die sich nicht
als Live-Site ausgeben soll).

**Der Tag liefert keine Anzeigen aus.** Er zieht kein Skript nach. Ob Anzeigen
laufen, hängt allein an `koehlbrand_adsense_client` – siehe unten.

An derselben Publisher-ID hängt auch **`/ads.txt`**: Das Theme liefert sie
selbst aus, solange keine echte Datei im Web-Root liegt.

```
google.com, pub-9359612609141957, DIRECT, f08c47fec0942fa0
```

Auch das ist Kontozuordnung, keine Auslieferung – Google prüft ads.txt bereits
während der Freischaltung. In einer Multisite ist der Weg über das Theme der
physischen Datei überlegen: Eine `ads.txt` im gemeinsamen Web-Root würde für
alle Domains des Netzwerks gelten, so liefert jede Site ihre eigene. Weitere
Zeilen (andere Vermarkter, Reseller) ergänzt der Filter
`koehlbrand_ads_txt_lines`.

Voraussetzung ist, dass der Request PHP erreicht. Prüfen lässt sich das durch
den Aufruf von `/ads.txt`: Kommt eine WordPress-404-Seite, ist PHP erreicht und
der Mechanismus greift; kommt die 404 des Webservers, liegt WordPress in einem
Unterverzeichnis und die Datei muss vom Webserver kommen.

### Reihenfolge bis zur ersten echten Anzeige

1. Meta-Tag ausliefern ✔ (erledigt)
2. Google schaltet die Website frei
3. Sechs Anzeigenblöcke im AdSense-Konto anlegen, IDs in
   `koehlbrand_adsense_slot_ids` eintragen. Ohne diese IDs rendert ein Slot nur
   die Höhenreservierung – ein `<ins>` ohne `data-ad-slot` erzeugt lediglich
   einen Konsolenfehler.
4. Zertifizierte CMP (TCF 2.2) am Hook `koehlbrand_cmp` einhängen. Ohne sie
   liefert Google für EWR-Traffic keine oder nur nicht-personalisierte Anzeigen –
   das ist eine Auslieferungssperre bei Google, keine DSGVO-Abwägung.
5. `koehlbrand_adsense_client` setzen und `koehlbrand_ads_preview` auf 0.

Schritt 5 vor Schritt 3 und 4 bringt nichts: Der Loader lädt auf jeder Seite,
Anzeigen erscheinen keine.

### Auslieferung

Alle Plätze reservieren ihre Höhe, bevor irgendein Skript lädt – sonst springt
das Layout beim Nachladen (schlechter CLS-Wert, weniger sichtbare Anzeigen).
Die Kennzeichnung „Anzeige“ steckt fest im Markup und ist nicht abschaltbar.

Drei Zustände:

| Zustand | Bedingung | Ausgabe |
|---|---|---|
| aus | keine Publisher-ID, kein Vorschaumodus | gar nichts |
| Vorschau | `koehlbrand_ads_preview` = 1 | gestrichelter Platzhalter in der reservierten Höhe |
| aktiv | `koehlbrand_adsense_client` gesetzt | `<ins class="adsbygoogle">` + Loader + Preconnects |

Optionen (per WP-CLI oder `update_option()`):

```
koehlbrand_adsense_client    "ca-pub-…"
koehlbrand_adsense_slot_ids  [ "header" => "123…", "sidebar" => …, "in-content" => …,
                               "end-of-article" => …, "in-feed" => …, "anchor" => … ]
koehlbrand_ads_preview       true/false
```

| Platz | Format | mobil | Desktop |
|---|---|---|---|
| Banner unter dem Header | Leaderboard, volle Breite | 100px hoch | 90px hoch |
| Im Artikel (automatisch) | Rectangle, zentriert | 300×250 | 336×280 |
| Artikelende | Rectangle, zentriert | 300×250 | 336×280 |
| Zwischen den Archiv-Karten | Rectangle, zentriert | 300×250 | 300×250 |
| Sidebar (sticky) | Half Page, feste Größe | – | 300×600 |
| Anchor | Leaderboard, fixiert | 60px hoch | – |

Die Rechtecke sind bewusst **in der Breite gedeckelt** (`max_width` in der
Slot-Registry). Ein responsiver Block ohne Deckel füllt die 720px-Spalte und
wird zum breiten Banner; mit 336px bzw. 300px liefert AdSense stattdessen
Large bzw. Medium Rectangle – die Formate mit der besten Klickrate im
Fließtext. Deshalb tragen diese Slots auch kein
`data-full-width-responsive`, das würde sie mobil wieder auf volle
Displaybreite ziehen.

Auto-Injection im Fließtext: ab 600 Wörtern, nach Absatz 2 und dann alle 4,
maximal 3 Anzeigen. Feinjustierung über die Filter `koehlbrand_ad_slots`,
`koehlbrand_ad_min_words`, `koehlbrand_ad_paragraph_interval`,
`koehlbrand_ad_max_in_content` und `koehlbrand_ad_slot_enabled`.

Beispiel – Sidebar auf Medium Rectangle umstellen:

```php
add_filter( 'koehlbrand_ad_slots', function ( $slots ) {
	$slots['sidebar']['height']             = 250;
	$slots['sidebar']['reserve']['desktop'] = 250;
	return $slots;
} );
```

### Automatische Anzeigen (Auto Ads)

Vorerst **aus**. Auto Ads platzieren zusätzlich zu den hier definierten Slots
und reservieren keinen Platz – genau der Layout-Sprung, gegen den die
Reservierungen oben gebaut sind. Außerdem wählt Google die Formate selbst und
kann die Rechteck-Strategie überschreiben.

Wenn später doch: in der AdSense-Oberfläche nur **Anchor** und **Vignette**
aktivieren und „In-Page-Anzeigen“ ausgeschaltet lassen. Der eigene
Anchor-Slot wird dann überflüssig:

```php
add_filter( 'koehlbrand_ad_slot_enabled', function ( $an, $slot ) {
	return 'anchor' === $slot ? false : $an;
}, 10, 2 );
```

`/ads.txt` liefert das Theme selbst aus, sobald eine Publisher-ID hinterlegt
ist – vorausgesetzt, WordPress liegt im Domain-Root. Eine echte Datei im
Web-Root hat Vorrang.

> **Vor der ersten echten Anzeige:** Google verlangt für EWR-Traffic eine
> zertifizierte CMP nach TCF 2.2, sonst werden Anzeigen gar nicht oder nur
> nicht-personalisiert ausgeliefert. Das Consent-Skript gehört an den Hook
> `koehlbrand_cmp` (läuft im `<head>` vor dem AdSense-Loader). Solange
> AdSense aktiv ist und dort nichts hängt, warnt das Backend.

## Reichweitenmessung (`inc/analytics.php`)

Google Analytics 4. Das gtag.js-Snippet steht auf jeder Frontend-Seite im
`<head>`, dazu Preconnects auf `googletagmanager.com` und
`google-analytics.com`:

```html
<script async src="https://www.googletagmanager.com/gtag/js?id=G-0ZZ7G738S9"></script>
```

Die Mess-ID steht – wie die AdSense-Publisher-ID und aus demselben Grund – als
Standardwert im Code (`KOEHLBRAND_GA4_MEASUREMENT_ID`) statt nur in einer
Option: Die Messung soll laufen, sobald das Theme aktiv ist, und nicht
stillschweigend ausfallen, weil beim Aufsetzen ein Options-Eintrag fehlt.
Überschreiben geht per Option `koehlbrand_ga4_id` oder gleichnamigem Filter;
ein leerer Wert schaltet die Messung ab – der Weg für die lokale
Docker-Instanz, deren Aufrufe sonst in der Statistik landen:

```php
add_filter( 'koehlbrand_ga4_id', fn() => '' );
```

Angemeldete Redakteure werden **nicht** ausgenommen. In der Testphase sind die
eigenen Aufrufe die einzigen, die es gibt – eine Ausnahme hieße, dass in GA4
nichts ankommt und die Einbindung fälschlich als kaputt gilt. Sobald echter
Traffic läuft, schaltet dieser Filter die eigenen Besuche aus:

```php
add_filter( 'koehlbrand_ga4_enabled', function ( $an ) {
	return is_user_logged_in() ? false : $an;
} );
```

> **Kein Consent-Banner** – Entscheidung vom 26.07.2026, bewusste
> Übergangslösung für die Entwicklungs- und Testphase. Vor dem Produktivbetrieb
> steht ein Wechsel der Tracking-Lösung samt sauberer Consent-Umsetzung an.
> Bis dahin gilt: Die **Datenschutzerklärung muss GA4 benennen** (Verarbeitung,
> Empfänger, US-Transfer, Widerspruch). Ihr Abschnitt 5 sagt derzeit das
> Gegenteil – „bindet derzeit keine Analyse-Werkzeuge ein" –, und solange dort
> das Wort „Analytics" fehlt, warnt das Backend. Wer doch früher eine CMP
> einhängt: Der Hook `koehlbrand_cmp` läuft im `<head>` vor gtag.js.

## Lesezeit, Inhaltsverzeichnis, Artikel-Navigation, Empfehlungen

Vier Bausteine gegen den Absprung nach dem ersten Absatz. Alle vier rechnen zur
Laufzeit aus dem Beitrag – es gibt keine Metafelder, die die REST-API-Pipeline
befüllen müsste, und nichts veraltet, wenn ein Artikel später umgeschrieben
wird. JavaScript braucht keiner davon.

| Baustein | Block | Steht in |
|---|---|---|
| Lesezeit | `koehlbrand/reading-time` | Autorenzeile im Aufmacher, im Artikelkopf und auf den Empfehlungskarten |
| Inhaltsverzeichnis | `koehlbrand/toc` | `single.html`, direkt über dem Artikeltext |
| Artikel-Navigation | `koehlbrand/post-nav` | `single.html`, unter der Schlagwortzeile |
| Empfehlungen | Query-Loop mit Klasse `koehlbrand-related-posts` | `single.html`, Abschnitt „Passend zum Thema“ |

**Lesezeit** (`inc/reading-time.php`): Wortzahl geteilt durch 200 Wörter pro
Minute, aufgerundet, mindestens eine Minute. Dieselbe Rechnung liefert
`wordCount` und `timeRequired` ans Article-Schema, damit Seite und
Suchergebnis nicht auseinanderlaufen.

```php
add_filter( 'koehlbrand_words_per_minute', fn() => 180 );
```

**Inhaltsverzeichnis** (`inc/toc.php`): sammelt H2 und H3, vergibt
Sprungmarken aus den Überschriftentexten und setzt dieselben Marken per
`the_content`-Filter in den Artikel. Eine im Editor gesetzte eigene Sprungmarke
bleibt unangetastet. Unter drei Überschriften rendert der Block nichts – dann
ist der Kasten größer als sein Nutzen. Auf- und zuklappen erledigt `<details>`.

```php
add_filter( 'koehlbrand_toc_min_headings', fn() => 4 );
```

**Artikel-Navigation** (`inc/post-nav.php`): vorheriger und nächster Beitrag,
bevorzugt aus derselben Rubrik. Am Anfang oder Ende einer Rubrik greift der
chronologische Fallback; gibt es gar keinen Nachbarn, entfällt das Feld.

**Empfehlungen** (`inc/related-posts.php`): Rangfolge statt „neueste der
Rubrik“. Gemeinsame Schlagwörter zählen dreifach, gemeinsame Rubriken doppelt,
dazu ein Aktualitätsbonus unter 1, der nur bei gleichem Themenbezug entscheidet.
Reicht das nicht für drei Karten, wird mit aktuellen Beiträgen aufgefüllt.

```php
add_filter( 'koehlbrand_related_weights', function ( $w ) {
	$w['tag'] = 5.0; // Schlagwörter noch stärker gewichten
	return $w;
} );
```

> **Für die Content-Pipeline:** Beides lebt von den Daten im Beitrag. Ohne
> **Schlagwörter** fällt die Rangfolge auf Rubrik und Aktualität zurück, ohne
> **H2-Zwischenüberschriften** erscheint kein Inhaltsverzeichnis.

## Sperrungen (`inc/closures.php`)

Die Termine der Vollsperrungen stehen als Datenliste in der Option
`koehlbrand_sperrungen`, **nicht** im Seitentext. Zwei Blöcke lesen dieselbe
Quelle, damit sie nicht auseinanderlaufen können:

| Block | Zeigt | Steht in |
|---|---|---|
| `koehlbrand/next-closure` | den nächsten oder laufenden Termin | `parts/sidebar.html` und, als breiter Streifen, in `templates/front-page.html` unter dem Aufmacher |
| `koehlbrand/closure-table` | alle künftigen Termine als Tabelle | `/sperrungen/` |

**Der wöchentliche Pflegeschritt beschränkt sich auf die Option.** Vergangene
Termine fallen von selbst heraus – maßgeblich ist das Ende, nicht der Beginn,
damit eine laufende Sperrung sichtbar bleibt. **Ohne künftigen Termin rendert
der Kasten nichts**: kein leerer Rahmen, kein „Stand unbekannt“.

Fällt eine Sperrung der Köhlbrandbrücke mit einer des Elbtunnels zusammen,
ergänzt der Kasten von selbst den Hinweis, dass für den Schwerlastverkehr keine
Ausweichroute bleibt. Das ist der Daseinsgrund des Bausteins: Die Termine
stammen von der Hamburg Port Authority und der Autobahn GmbH, und keine der
beiden Quellen kennt die andere.

**Zwei Darstellungen, eine Auszeichnung.** In der Seitenleiste steht der Kasten
gestapelt, auf der Startseite über die volle Breite (`className`
`koehlbrand-closure--breit`, ab 900px einzeilig; darunter fällt er in die
gestapelte Form zurück). Innerhalb des Vorlaufs – Vorgabe sieben Tage – wird er
kräftiger hinterlegt, weil er auf der Startseite dauerhaft steht und ein Termin
in acht Wochen sonst so aussieht wie der am Freitag:

```php
add_filter( 'koehlbrand_sperrung_vorlauf_tage', fn() => 14 );
```

Die Termine lassen sich vor der Ausgabe auch programmatisch ergänzen oder
ersetzen, etwa aus einer künftigen Datenquelle:

```php
add_filter( 'koehlbrand_sperrungen', function ( $termine ) {
	$termine[] = array(
		'bauwerk' => 'Köhlbrandbrücke',
		'beginn'  => '2026-10-16 21:00',
		'ende'    => '2026-10-19 05:00',
	);
	return $termine;
} );
```

## Struktur

- `theme.json` – Farben, Schriften, Abstände (1:1 aus den Design-Tokens)
- `inc/` – SEO (Meta-Tags, Open Graph, JSON-LD), Brotkrumen, Werbeplätze,
  Reichweitenmessung sowie Lesezeit, Inhaltsverzeichnis, Artikel-Navigation
  und Empfehlungen. Bewusst
  theme-nativ statt Yoast/RankMath, weil die Artikel automatisiert über die
  REST API entstehen und die Pipeline sonst plugin-spezifische Metafelder
  befüllen müsste.
- `blocks/breadcrumbs/` – dynamischer Block `koehlbrand/breadcrumbs`, im
  Editor unter „Design“ zu finden
- `blocks/ad-slot/` – dynamischer Block `koehlbrand/ad-slot` (Werbeplatz),
  ebenfalls unter „Design“
- `blocks/reading-time/`, `blocks/toc/`, `blocks/post-nav/` – Lesezeit,
  Inhaltsverzeichnis und Artikel-Navigation, ebenfalls unter „Design“
- `blocks/next-closure/`, `blocks/closure-table/` – nächste Vollsperrung und
  Terminübersicht, beide aus der Option `koehlbrand_sperrungen`
- `templates/` – Startseite, Artikel, Seite, Rubrik-Archiv, Suche, 404
- `parts/` – Header, Footer und Sidebar. Die Sidebar erscheint auf Artikel-
  und Rubrikseiten neben dem Text (ab 1100px Fensterbreite) und enthält den
  Kasten „Nächste Vollsperrung“ über dem Werbeplatz; weitere redaktionelle
  Module können dort ergänzt werden und stapeln sich mobil unter dem Artikel.
- `patterns/` – Fakten-Box, Bild mit Fotocredit, CTA-Kasten (im Editor unter
  Kategorie „Köhlbrand“ zu finden)
- `assets/fonts/` – Barlow Semi Condensed & IBM Plex Sans, selbst gehostet (kein
  Google-Fonts-CDN, aus DSGVO-Gründen)
- `assets/images/` – die drei Logo-Varianten aus dem Branding-Ordner

## Bekannte offene Punkte

- `content/` und `technik/` im Projektordner waren noch leer – es sind daher
  noch keine echten Artikeltexte oder z. B. ein Hero-Bild der Brücke im
  Theme enthalten. Sobald Fotos vorliegen, empfiehlt sich ein Blende/Sonnenuntergang-
  Bild als Featured Image des ersten Artikels für den Hero-Bereich.
- Menüpunkte in Header/Footer sind aktuell fest verlinkt (`/kategorie/...`,
  `/impressum/`, `/datenschutz/`) – ggf. anpassen, sobald die echten Slugs/
  Seiten stehen.
