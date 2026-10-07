<?php
/**
 * Globe section shortcode.
 *
 * The copy is rendered here rather than in React so it is present in the HTML
 * for search engines and works without JavaScript. Only the globe itself is a
 * React mount point.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL of the lazily loaded globe engine.
 *
 * inc/enqueue.php registers the chunk through the WordPress script API, so the
 * version query string and path stay owned by WordPress.
 *
 * @return string
 */
function custom_dubai_globe_engine_src() {
	$engine = wp_scripts()->registered['custom-dubai-globe-engine'] ?? null;

	if ( $engine && $engine->src ) {
		return $engine->ver ? add_query_arg( 'ver', $engine->ver, $engine->src ) : $engine->src;
	}

	return get_theme_file_uri( '/shortcodes/globe-section/chunks/globe-engine.js' );
}

/**
 * Render the globe section.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function custom_dubai_render_globe_section_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'eyebrow'        => __( 'Supplying all seven emirates', 'custom-dubai-tyre-shop' ),
			'heading'        => __( 'Your biggest supplier of tyres in the UAE', 'custom-dubai-tyre-shop' ),
			'text'           => __( 'Premium passenger, SUV and truck tyres delivered to workshops, garages and fleets across every emirate, with honest pricing, expert fitting and same-day delivery.', 'custom-dubai-tyre-shop' ),
			'points'         => __( 'Seven emirates served|Same-day delivery across the UAE|Passenger, SUV and truck sizes in stock|Mobile fitting, balancing and alignment', 'custom-dubai-tyre-shop' ),
			'button_text'    => __( 'Shop tyres', 'custom-dubai-tyre-shop' ),
			'button_url'     => home_url( '/tyres/' ),
			'secondary_text' => __( 'Talk to a tyre expert', 'custom-dubai-tyre-shop' ),
			'secondary_url'  => home_url( '/contact/' ),
			'label'          => __( 'Rotating globe highlighting the United Arab Emirates, with all seven emirates pinned', 'custom-dubai-tyre-shop' ),
		),
		$atts,
		'custom_dubai_globe_section'
	);

	// Bullets are pipe separated so each point can contain commas.
	$points = array_values(
		array_filter(
			array_map( 'trim', explode( '|', $atts['points'] ) ),
			'strlen'
		)
	);

	ob_start();
	?>
	<section
		class="custom-dubai-globe-section"
		data-custom-dubai-globe
		data-engine-src="<?php echo esc_url( custom_dubai_globe_engine_src() ); ?>"
		data-globe-label="<?php echo esc_attr( $atts['label'] ); ?>"
	>
		<div class="custom-dubai-globe-section__inner container">
			<div class="custom-dubai-globe-section__copy">
				<?php if ( $atts['eyebrow'] ) : ?>
					<p class="custom-dubai-globe-section__eyebrow"><?php echo esc_html( $atts['eyebrow'] ); ?></p>
				<?php endif; ?>

				<?php if ( $atts['heading'] ) : ?>
					<h2 class="custom-dubai-globe-section__heading"><?php echo wp_kses_post( $atts['heading'] ); ?></h2>
				<?php endif; ?>

				<?php if ( $atts['text'] ) : ?>
					<p class="custom-dubai-globe-section__text"><?php echo wp_kses_post( $atts['text'] ); ?></p>
				<?php endif; ?>

				<?php if ( $points ) : ?>
					<ul class="custom-dubai-globe-section__points">
						<?php foreach ( $points as $point ) : ?>
							<li class="custom-dubai-globe-section__point"><?php echo esc_html( $point ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<div class="custom-dubai-globe-section__actions">
					<?php if ( $atts['button_text'] && $atts['button_url'] ) : ?>
						<a class="custom-dubai-globe-section__button" href="<?php echo esc_url( $atts['button_url'] ); ?>">
							<?php echo esc_html( $atts['button_text'] ); ?>
						</a>
					<?php endif; ?>

					<?php if ( $atts['secondary_text'] && $atts['secondary_url'] ) : ?>
						<a class="custom-dubai-globe-section__link" href="<?php echo esc_url( $atts['secondary_url'] ); ?>">
							<?php echo esc_html( $atts['secondary_text'] ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="custom-dubai-globe-section__globe" data-custom-dubai-globe-mount></div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'custom_dubai_globe_section', 'custom_dubai_render_globe_section_shortcode' );
