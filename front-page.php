<?php
/**
 * Template Name: Custom Dubai Tyre Shop Homepage
 * Template Post Type: page
 *
 * @package Custom_Dubai_Tyre_Shop
 */
get_header();
?>
<main class="site-main">
	<?php
	$front_page = get_post();
	if ( ! $front_page || ! has_shortcode( $front_page->post_content, 'custom_dubai_hero' ) ) {
		get_template_part( 'template-parts/components/hero' );
	}
	if ( function_exists( 'hos_render_homepage' ) ) {
		hos_render_homepage();
	}
	// Skipped when the page content already places the section itself, so it is
	// never rendered twice on the same page.
	if ( ! $front_page || ! has_shortcode( $front_page->post_content, 'custom_dubai_shop_by_category' ) ) {
		get_template_part( 'template-parts/components/shop-by-category' );
	}
	get_template_part( 'template-parts/components/services-section' );

	// Skipped when the page content already places the section itself, so the
	// globe is never rendered twice on the same page.
	if ( ! $front_page || ! has_shortcode( $front_page->post_content, 'custom_dubai_globe_section' ) ) {
		get_template_part( 'template-parts/components/globe-section' );
	}
	?>
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<?php if ( trim( get_the_content() ) !== '' ) : ?>
				<article <?php post_class(); ?>>
					<div class="entry-content"><?php the_content(); ?></div>
				</article>
			<?php endif; ?>
		<?php endwhile; ?>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
