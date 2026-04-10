<?php
/**
 * Title: 404
 * Slug: whiteblack/404
 * Inserter: no
 */
?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"}},"position":{"type":""}},"backgroundColor":"custom-color-3","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-custom-color-3-background-color has-background" style="padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px"><!-- wp:template-part {"slug":"header","area":"header","align":"wide"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"},"margin":{"top":"15px","bottom":"15px"}},"border":{"width":"1px"}},"borderColor":"custom-color-1","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-border-color has-custom-color-1-border-color" style="border-width:1px;margin-top:15px;margin-bottom:15px;padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px"><!-- wp:image {"sizeSlug":"large","align":"center"} -->
<figure class="wp-block-image aligncenter size-large"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/404wordpress.jpg" alt=""/></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"},"blockGap":"15px"}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"15px","left":"15px"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"40%","style":{"spacing":{"blockGap":"15px"}}} -->
<div class="wp-block-column" style="flex-basis:40%"><!-- wp:heading {"textAlign":"right"} -->
<h2 class="wp-block-heading has-text-align-right"><?php esc_html_e('404 Error - The page you requested doesn’t exist.', 'whiteblack');?></h2>
<!-- /wp:heading --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"blockGap":"15px"}}} -->
<div class="wp-block-column"><!-- wp:paragraph -->
<p><?php esc_html_e('The cosmic object you were looking for has disappeared beyond the event horizon. If you arrived at this page using a bookmark or favorites link, please update it accordingly.', 'whiteblack');?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></main>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"},"blockGap":"15px"},"elements":{"link":{"color":{"text":"var:preset|color|white"}}}},"backgroundColor":"black","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-white-color has-black-background-color has-text-color has-background has-link-color" style="padding-top:15px;padding-right:15px;padding-bottom:15px;padding-left:15px"><!-- wp:template-part {"slug":"footer","area":"footer","align":"wide"} /--></div>
<!-- /wp:group -->