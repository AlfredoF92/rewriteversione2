<?php
/**
 * Title: Post with mini header and mini footer - no featured image
 * Slug: whiteblack/post-mhmfnfi
 * Categories: posts
 * Template Types: single
 * Inserter: no
 */
?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"}},"position":{"type":""}},"backgroundColor":"custom-color-3","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-custom-color-3-background-color has-background" style="padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px"><!-- wp:template-part {"slug":"mini-header","align":"wide"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"},"blockGap":"15px"}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"15px","left":"15px"},"padding":{"top":"15px","bottom":"15px","left":"0px","right":"0px"},"margin":{"top":"0px","bottom":"0px"}}}} -->
<div class="wp-block-columns alignwide" style="margin-top:0px;margin-bottom:0px;padding-top:15px;padding-right:0px;padding-bottom:15px;padding-left:0px"><!-- wp:column {"width":"40%","style":{"spacing":{"blockGap":"15px"}}} -->
<div class="wp-block-column" style="flex-basis:40%"><!-- wp:post-title {"textAlign":"right"} /-->

<!-- wp:post-date {"textAlign":"right"} /-->

<!-- wp:post-author-name {"textAlign":"right"} /-->

<!-- wp:post-terms {"term":"category","textAlign":"right"} /-->

<!-- wp:post-terms {"term":"post_tag","textAlign":"right"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"blockGap":"15px"}}} -->
<div class="wp-block-column"><!-- wp:post-content /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:comments {"align":"wide","style":{"spacing":{"padding":{"right":"0","left":"0"}}}} -->
<div class="wp-block-comments alignwide" style="padding-right:0;padding-left:0"><!-- wp:comments-title {"level":3} /-->

<!-- wp:comment-template {"style":{"spacing":{"padding":{"left":"0px","right":"0px"}}}} -->
<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"0"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"40px","style":{"spacing":{"padding":{"right":"10px"}}}} -->
<div class="wp-block-column" style="padding-right:10px;flex-basis:40px"><!-- wp:avatar {"size":40,"style":{"border":{"radius":"20px"}}} /--></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"blockGap":"0px"}}} -->
<div class="wp-block-column"><!-- wp:comment-author-name {"fontSize":"small"} /-->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"5px","bottom":"5px"},"blockGap":"15px"}},"layout":{"type":"flex"}} -->
<div class="wp-block-group" style="margin-top:5px;margin-bottom:5px"><!-- wp:comment-date {"fontSize":"small"} /-->

<!-- wp:comment-edit-link {"fontSize":"small"} /--></div>
<!-- /wp:group -->

<!-- wp:comment-content /-->

<!-- wp:comment-reply-link {"fontSize":"small"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:comment-template -->

<!-- wp:comments-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:comments-pagination-previous /-->

<!-- wp:comments-pagination-numbers /-->

<!-- wp:comments-pagination-next /-->
<!-- /wp:comments-pagination -->

<!-- wp:post-comments-form /--></div>
<!-- /wp:comments --></div>
<!-- /wp:group --></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"mini-footer"} /-->