<?php
/**
 * Title: loop
 * Slug: whiteblack/loop
 * Inserter: no
 */
?>
<!-- wp:query {"queryId":24,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true,"taxQuery":null,"parents":[],"format":[]},"tagName":"main","enhancedPagination":true,"align":"wide"} -->
<main class="wp-block-query alignwide"><!-- wp:post-template -->
<!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"15px","left":"15px"},"margin":{"top":"0px","bottom":"0px"}}}} -->
<div class="wp-block-columns" style="margin-top:0px;margin-bottom:0px"><!-- wp:column {"verticalAlignment":"bottom","width":"40%","style":{"spacing":{"blockGap":"15px","padding":{"top":"0px","bottom":"0px"}}}} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="padding-top:0px;padding-bottom:0px;flex-basis:40%"><!-- wp:post-title {"textAlign":"right","isLink":true} /-->

<!-- wp:post-date {"textAlign":"right"} /-->

<!-- wp:post-featured-image {"isLink":true,"width":"150px","align":"right","className":"is-style-whiteblack-fromgray"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","style":{"spacing":{"blockGap":"15px"}}} -->
<div class="wp-block-column is-vertically-aligned-bottom"><!-- wp:post-excerpt {"moreText":"read more","showMoreOnNewLine":false} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:spacer {"height":"45px"} -->
<div style="height:45px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph {"align":"center","placeholder":"Add text or blocks that will display when a query returns no results."} -->
<p class="has-text-align-center"><?php esc_html_e('No result content.', 'whiteblack');?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></main>
<!-- /wp:query -->