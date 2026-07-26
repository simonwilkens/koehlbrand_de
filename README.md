# Köhlbrand WordPress-Theme

Block-Theme (Full Site Editing) für koehlbrand.de, gebaut nach den Marken-Guidelines
in `branding/koehlbrand-brand-guidelines.md` und `branding/design-tokens.css`.

## Installation

1. Ordner `koehlbrand-theme` als ZIP packen (bereits als `koehlbrand-theme.zip`
   im Projektordner vorhanden) und unter **Design → Themes → Neu hinzufügen →
   Theme hochladen** installieren, oder per FTP nach `wp-content/themes/` kopieren.
2. Theme aktivieren.

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

## Struktur

- `theme.json` – Farben, Schriften, Abstände (1:1 aus den Design-Tokens)
- `inc/` – SEO (Meta-Tags, Open Graph, JSON-LD), Brotkrumen, Werbeplätze sowie
  Lesezeit, Inhaltsverzeichnis, Artikel-Navigation und Empfehlungen. Bewusst
  theme-nativ statt Yoast/RankMath, weil die Artikel automatisiert über die
  REST API entstehen und die Pipeline sonst plugin-spezifische Metafelder
  befüllen müsste.
- `blocks/breadcrumbs/` – dynamischer Block `koehlbrand/breadcrumbs`, im
  Editor unter „Design“ zu finden
- `blocks/ad-slot/` – dynamischer Block `koehlbrand/ad-slot` (Werbeplatz),
  ebenfalls unter „Design“
- `blocks/reading-time/`, `blocks/toc/`, `blocks/post-nav/` – Lesezeit,
  Inhaltsverzeichnis und Artikel-Navigation, ebenfalls unter „Design“
- `templates/` – Startseite, Artikel, Seite, Rubrik-Archiv, Suche, 404
- `parts/` – Header, Footer und Sidebar. Die Sidebar erscheint auf Artikel-
  und Rubrikseiten neben dem Text (ab 1100px Fensterbreite) und enthält
  aktuell nur den Werbeplatz; redaktionelle Module können dort ergänzt werden
  und stapeln sich mobil unter dem Artikel.
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
