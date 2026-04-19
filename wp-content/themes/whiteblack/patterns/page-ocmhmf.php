<?php
/**
 * Title: One column page wiht mini header and mini footer
 * Slug: whiteblack/page-ocmhmf
 * Categories: posts
 * Template Types: page
 * Inserter: no
 */
?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"}},"position":{"type":""}},"backgroundColor":"custom-color-3","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-custom-color-3-background-color has-background" style="padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px"><!-- wp:template-part {"slug":"mini-header","area":"uncategorized","align":"wide"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"},"blockGap":"15px"}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"15px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide"><!-- wp:post-title {"textAlign":"left","align":"wide"} /-->

<!-- wp:post-content {"align":"wide"} /--></div>
<!-- /wp:group --></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"mini-footer","area":"uncategorized"} /-->