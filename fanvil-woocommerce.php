<?php
/**
 * Shop, category, sub-category, tag, product search, promotions and single product pages.
 * Used by functions.php when Elementor Pro is not active (replaces its Theme Builder templates).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>
<main id="content" class="fvt-main">
	<div class="fvt-container">
		<?php
		if ( is_product() ) {
			while ( have_posts() ) {
				the_post();
				echo do_shortcode( '[fanvil_product]' );
			}
		} else {
			echo do_shortcode( '[fanvil_shop]' );
		}
		?>
	</div>
</main>
<style id="fvt-main-css">
.fvt-main{width:100%}
.fvt-container{max-width:1250px;margin:0 auto;padding:10px}
</style>
<?php
get_footer();
