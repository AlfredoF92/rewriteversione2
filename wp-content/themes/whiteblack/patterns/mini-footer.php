<?php
/**
 * Title: mini-footer
 * Slug: whiteblack/mini-footer
 * Inserter: no
 */
?>
<!-- wp:group {"style":{"elements":{"link":{"color":{"text":"var:preset|color|white"}}},"spacing":{"padding":{"bottom":"15px","top":"15px"}}},"backgroundColor":"black","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-white-color has-black-background-color has-text-color has-background has-link-color" style="padding-top:15px;padding-bottom:15px"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"15px","padding":{"top":"0px","bottom":"0px"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide" style="padding-top:0px;padding-bottom:0px"><!-- wp:site-title {"level":0} /-->

<!-- wp:loginout /--></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><?php /* Translators: 1. is the start of a 'a' HTML element, 2. is the end of a 'a' HTML element */ 
echo sprintf( esc_html__( 'Fantasmagorically photographed, developed and printed in black and white with %1$sWordPress%2$s', 'whiteblack' ), '<a href="' . esc_url( 'http://wordpress.org' ) . '" data-type="link" data-id="wordpress.org" target="_blank" rel="noreferrer noopener nofollow">', '</a>' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->