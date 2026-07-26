<?php
/**
 * Title: CTA-Kasten
 * Slug: koehlbrand/cta-kasten
 * Categories: call-to-action
 * Keywords: cta, hinweis, button, uebersicht
 * Description: Amber-Signal-Kasten mit Überschrift, Text und Button, z. B. Verweis auf eine Rubrik-Übersicht.
 */
?>
<!-- wp:group {"backgroundColor":"primary","textColor":"white","style":{"spacing":{"padding":{"top":"var:preset|spacing|md","right":"var:preset|spacing|md","bottom":"var:preset|spacing|md","left":"var:preset|spacing|md"}}},"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
<div class="wp-block-group has-white-color has-primary-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--md);padding-right:var(--wp--preset--spacing--md);padding-bottom:var(--wp--preset--spacing--md);padding-left:var(--wp--preset--spacing--md)">

	<!-- wp:group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"level":3,"fontFamily":"headline","textColor":"white"} -->
		<h3 class="wp-block-heading has-white-color has-text-color has-headline-font-family">Alle Beiträge zu diesem Thema</h3>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"textColor":"fog"} -->
		<p class="has-fog-color has-text-color">Kurzer Hinweistext, was die Leser:innen bei einem Klick erwartet.</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"backgroundColor":"accent","textColor":"ink"} -->
		<div class="wp-block-button"><a class="wp-block-button__link has-ink-color has-accent-background-color has-text-color has-background wp-element-button" href="#">Zur Übersicht</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->

</div>
<!-- /wp:group -->
