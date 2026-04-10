<?php
/**
 * Title: Page with mini header and mini footer
 * Slug: whiteblack/page-mhmf
 * Categories: posts
 * Template Types: page
 * Inserter: no
 */
?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"}},"position":{"type":""}},"backgroundColor":"custom-color-3","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-custom-color-3-background-color has-background" style="padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px"><!-- wp:template-part {"slug":"mini-header","align":"wide"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"},"blockGap":"15px"}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"15px","left":"15px"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"40%","style":{"spacing":{"blockGap":"15px"}}} -->
<div class="wp-block-column" style="flex-basis:40%"><!-- wp:post-title {"textAlign":"right"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"blockGap":"15px"}}} -->
<div class="wp-block-column"><!-- wp:post-content /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"mini-footer"} /-->