<?php
/**
 * Title: footer
 * Slug: whiteblack/footer
 * Inserter: no
 */
?>
<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"15px","left":"15px"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"40%","style":{"spacing":{"blockGap":"15px"}}} -->
<div class="wp-block-column" style="flex-basis:40%"><!-- wp:navigation {"style":{"spacing":{"blockGap":"15px"}}} /--></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"blockGap":"15px"}}} -->
<div class="wp-block-column"><!-- wp:search {"label":"Search","showLabel":false,"buttonText":"Search","buttonPosition":"button-inside","buttonUseIcon":true} /-->

<!-- wp:tag-cloud {"taxonomy":"category"} /-->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:tag-cloud /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"15px","padding":{"top":"0px","bottom":"0px"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide" style="padding-top:0px;padding-bottom:0px"><!-- wp:site-title /-->

<!-- wp:loginout {"fontSize":"medium"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"15px","bottom":"15px"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="padding-top:15px;padding-bottom:15px"><!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><?php /* Translators: 1. is the start of a 'a' HTML element, 2. is the end of a 'a' HTML element */ 
echo sprintf( esc_html__( 'Fantasmagorically photographed, developed and printed in black and white with %1$sWordPress%2$s', 'whiteblack' ), '<a href="' . esc_url( 'http://wordpress.org' ) . '" data-type="link" data-id="wordpress.org" rel="nofollow">', '</a>' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->