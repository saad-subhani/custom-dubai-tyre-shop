<?php
/**
 * Plugin Name: Fitting Center Manager
 * Description: Manage fitting centers, opening hours, and center images for the Custom Dubai Tyre Shop theme.
 * Version: 1.0.0
 * Author: Custom Dubai Tyre Shop
 * Text Domain: fitting-center-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default centers mirror the theme's existing catalogue. Options saved in the
 * admin replace this catalogue and are returned through the theme's filter.
 */
function fcp_default_centers() {
	$open = array(
		'Saturday'  => array( array( '09:00', '20:00' ) ),
		'Sunday'    => array( array( '09:00', '20:00' ) ),
		'Monday'    => array( array( '09:00', '20:00' ) ),
		'Tuesday'   => array( array( '09:00', '20:00' ) ),
		'Wednesday' => array( array( '09:00', '20:00' ) ),
		'Thursday'  => array( array( '09:00', '20:00' ) ),
		'Friday'    => array( array( '09:00', '12:00' ), array( '14:30', '20:00' ) ),
	);
	$centers = array(
		'abu_dhabi' => array(
			'label'   => 'Abu Dhabi',
			'centers' => array(
				array( 'id' => 'mussafah', 'name' => 'Mussafah Industrial Fitting Center', 'hours' => array( 'Saturday to Thursday: 09:00 - 20:00', 'Sunday: 09:00 - 20:00', 'Friday: 09:00 - 12:00 & 14:30 - 20:00' ), 'schedule' => $open, 'image_id' => 0, 'image_url' => '' ),
			),
		),
		'dubai' => array(
			'label'   => 'Dubai',
			'centers' => array(
				array( 'id' => 'alqouz', 'name' => 'Alqouz Fitting Center', 'hours' => array( 'Saturday to Thursday: 09:00 - 20:00', 'Sunday: 09:00 - 13:00', 'Friday: 09:00 - 12:00 & 14:30 - 20:00' ), 'schedule' => array_merge( $open, array( 'Sunday' => array( array( '09:00', '13:00' ) ) ) ), 'image_id' => 0, 'image_url' => '' ),
				array( 'id' => 'tecom', 'name' => 'Tecom Fitting Center', 'hours' => array( 'Saturday to Thursday: 09:00 - 20:00', 'Sunday: 09:00 - 20:00', 'Friday: 09:00 - 12:00 & 14:30 - 20:00' ), 'schedule' => $open, 'image_id' => 0, 'image_url' => '' ),
				array( 'id' => 'ras_al_khor', 'name' => 'Ras Al Khor Fitting Center', 'hours' => array( 'Saturday to Thursday: 09:00 - 20:00', 'Sunday: 09:00 - 20:00', 'Friday: 09:00 - 12:00 & 14:30 - 20:00' ), 'schedule' => $open, 'image_id' => 0, 'image_url' => '' ),
				array( 'id' => 'umm_ramool', 'name' => 'Umm Ramool Fitting Center', 'hours' => array( 'Saturday to Thursday: 09:00 - 20:00', 'Sunday: Closed', 'Friday: 09:00 - 12:00 & 14:30 - 20:00' ), 'schedule' => array_merge( $open, array( 'Sunday' => array() ) ), 'image_id' => 0, 'image_url' => '' ),
			),
		),
	);

	return $centers;
}

/** Supply the managed catalogue to the theme's existing integration point. */
function fcp_filter_centers( $theme_centers ) {
	$saved = get_option( 'fcp_centers', false );
	return is_array( $saved ) ? $saved : fcp_default_centers();
}
add_filter( 'dts_fitting_centers', 'fcp_filter_centers', 20 );

/** Capability needed to manage the catalogue. */
function fcp_manage_capability() {
	return class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
}

/**
 * Mirror the page into WooCommerce's own navigation.
 *
 * WooCommerce ships a navigation layer that supersedes the classic WordPress
 * sidebar, and it ignores anything registered only through add_submenu_page().
 * Registering through the Menu API keeps the entry visible in both the legacy
 * sidebar and the WooCommerce navigation.
 */
function fcp_register_wc_navigation_item() {
	if ( ! class_exists( '\Automattic\WooCommerce\Admin\Features\Navigation\Menu' ) ) {
		return;
	}

	if ( ! method_exists( '\Automattic\WooCommerce\Admin\Features\Navigation\Menu', 'add_plugin_item' ) ) {
		return;
	}

	\Automattic\WooCommerce\Admin\Features\Navigation\Menu::add_plugin_item(
		array(
			'id'         => 'fcp-fitting-centers',
			'title'      => 'Fitting Centers',
			'capability' => 'manage_woocommerce',
			'url'        => 'fitting-center-plugin',
		)
	);
}

/**
 * Add a settings screen under WooCommerce when available, otherwise Settings.
 *
 * Runs late so WooCommerce has already registered its own top-level menu. Core
 * drops any submenu whose parent slug is missing from $menu, so registering
 * before WooCommerce exists leaves the page unreachable with no error.
 */
function fcp_add_admin_menu() {
	if ( class_exists( 'WooCommerce' ) ) {
		add_submenu_page( 'woocommerce', 'Fitting Centers', 'Fitting Centers', 'manage_woocommerce', 'fitting-center-plugin', 'fcp_render_admin_page' );
		fcp_register_wc_navigation_item();
	} else {
		add_options_page( 'Fitting Centers', 'Fitting Centers', 'manage_options', 'fitting-center-plugin', 'fcp_render_admin_page' );
	}
}
add_action( 'admin_menu', 'fcp_add_admin_menu', 99 );

/** Load WordPress's media picker only on this plugin's settings screen. */
function fcp_admin_assets( $hook ) {
	if ( false === strpos( (string) $hook, 'fitting-center-plugin' ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'jquery' );
	wp_add_inline_script( 'jquery', fcp_admin_script() );
}
add_action( 'admin_enqueue_scripts', 'fcp_admin_assets' );

/** Save and validate the center catalogue. */
function fcp_save_centers() {
	if ( ! current_user_can( fcp_manage_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to manage fitting centers.', 'fitting-center-plugin' ) );
	}
	check_admin_referer( 'fcp_save_centers' );
	$input = isset( $_POST['fcp_cities'] ) ? wp_unslash( $_POST['fcp_cities'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$output = array();
	$used_ids = array();

	foreach ( (array) $input as $city ) {
		$key = sanitize_title( isset( $city['key'] ) ? $city['key'] : '' );
		$label = sanitize_text_field( isset( $city['label'] ) ? $city['label'] : '' );
		if ( '' === $key || '' === $label ) {
			continue;
		}
		$group = array( 'label' => $label, 'centers' => array() );
		foreach ( (array) ( isset( $city['centers'] ) ? $city['centers'] : array() ) as $center ) {
			$id = sanitize_title( isset( $center['id'] ) ? $center['id'] : '' );
			$name = sanitize_text_field( isset( $center['name'] ) ? $center['name'] : '' );
			if ( '' === $id || '' === $name || isset( $used_ids[ $id ] ) ) {
				continue;
			}
			$used_ids[ $id ] = true;
			$image_id = absint( isset( $center['image_id'] ) ? $center['image_id'] : 0 );
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
			$hours_text = sanitize_textarea_field( isset( $center['hours'] ) ? $center['hours'] : '' );
			$hours = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $hours_text ) ) ) );
			$defaults = fcp_default_centers();
			$schedule = array();
			foreach ( $defaults as $default_city ) {
				foreach ( $default_city['centers'] as $default_center ) {
					if ( $default_center['id'] === $id ) {
						$schedule = $default_center['schedule'];
						break 2;
					}
				}
			}
			$group['centers'][] = array(
				'id'        => $id,
				'name'      => $name,
				'image_id'  => $image_id,
				'image_url' => $image_url ? esc_url_raw( $image_url ) : esc_url_raw( isset( $center['image_url'] ) ? $center['image_url'] : '' ),
				'hours'     => $hours,
				'schedule'  => $schedule,
			);
		}
		if ( ! empty( $group['centers'] ) ) {
			$output[ $key ] = $group;
		}
	}
	update_option( 'fcp_centers', $output );
	wp_safe_redirect( add_query_arg( array( 'page' => 'fitting-center-plugin', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_fcp_save_centers', 'fcp_save_centers' );

/** Render the center editor. */
function fcp_render_admin_page() {
	if ( ! current_user_can( fcp_manage_capability() ) ) {
		return;
	}
	$centers = fcp_filter_centers( array() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Fitting Centers', 'fitting-center-plugin' ); ?></h1>
		<p><?php esc_html_e( 'Manage the locations shown by the shop. Images are added to the WordPress Media Library.', 'fitting-center-plugin' ); ?></p>
		<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Fitting centers saved.', 'fitting-center-plugin' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fcp-form">
			<input type="hidden" name="action" value="fcp_save_centers">
			<?php wp_nonce_field( 'fcp_save_centers' ); ?>
			<div id="fcp-cities">
			<?php $city_index = 0; foreach ( $centers as $city_key => $city ) : ?>
				<section class="postbox fcp-city" style="padding:16px;margin:16px 0" data-city-index="<?php echo esc_attr( $city_index ); ?>">
					<p><label><?php esc_html_e( 'City key', 'fitting-center-plugin' ); ?> <input class="regular-text" name="fcp_cities[<?php echo esc_attr( $city_index ); ?>][key]" value="<?php echo esc_attr( $city_key ); ?>" required></label>
					<label><?php esc_html_e( 'City name', 'fitting-center-plugin' ); ?> <input class="regular-text" name="fcp_cities[<?php echo esc_attr( $city_index ); ?>][label]" value="<?php echo esc_attr( $city['label'] ); ?>" required></label>
					<button type="button" class="button-link-delete fcp-remove-city"><?php esc_html_e( 'Remove city', 'fitting-center-plugin' ); ?></button></p>
					<div class="fcp-city-centers">
					<?php $center_index = 0; foreach ( $city['centers'] as $center ) : ?>
						<?php fcp_render_center_row( $city_index, $center_index, $center ); $center_index++; ?>
					<?php endforeach; ?>
					</div>
					<button type="button" class="button fcp-add-center"><?php esc_html_e( 'Add center', 'fitting-center-plugin' ); ?></button>
				</section>
			<?php $city_index++; endforeach; ?>
			</div>
			<p><button type="button" class="button" id="fcp-add-city"><?php esc_html_e( 'Add city', 'fitting-center-plugin' ); ?></button></p>
			<?php submit_button( __( 'Save Fitting Centers', 'fitting-center-plugin' ) ); ?>
		</form>
		<template id="fcp-center-template">
			<?php fcp_render_center_row( '__CITY__', '__CENTER__', array( 'id' => '', 'name' => '', 'hours' => array(), 'image_id' => 0, 'image_url' => '' ) ); ?>
		</template>
		<template id="fcp-city-template">
			<section class="postbox fcp-city" style="padding:16px;margin:16px 0" data-city-index="__CITY__">
				<p><label><?php esc_html_e( 'City key', 'fitting-center-plugin' ); ?> <input class="regular-text" name="fcp_cities[__CITY__][key]" value="" required></label>
				<label><?php esc_html_e( 'City name', 'fitting-center-plugin' ); ?> <input class="regular-text" name="fcp_cities[__CITY__][label]" value="" required></label>
				<button type="button" class="button-link-delete fcp-remove-city"><?php esc_html_e( 'Remove city', 'fitting-center-plugin' ); ?></button></p>
				<div class="fcp-city-centers"></div><button type="button" class="button fcp-add-center"><?php esc_html_e( 'Add center', 'fitting-center-plugin' ); ?></button>
			</section>
		</template>
	</div>
	<?php
}

/** Output one editable center row. */
function fcp_render_center_row( $city_index, $center_index, $center ) {
	$base = 'fcp_cities[' . $city_index . '][centers][' . $center_index . ']';
	$hours = implode( "\n", (array) $center['hours'] );
	?>
	<div class="fcp-center" style="border-top:1px solid #ddd;padding:12px 0" data-center-index="<?php echo esc_attr( $center_index ); ?>">
		<p><label><?php esc_html_e( 'Center ID', 'fitting-center-plugin' ); ?> <input class="regular-text" name="<?php echo esc_attr( $base ); ?>[id]" value="<?php echo esc_attr( $center['id'] ); ?>" required></label>
		<label><?php esc_html_e( 'Name', 'fitting-center-plugin' ); ?> <input class="regular-text" name="<?php echo esc_attr( $base ); ?>[name]" value="<?php echo esc_attr( $center['name'] ); ?>" required></label>
		<button type="button" class="button-link-delete fcp-remove-center"><?php esc_html_e( 'Remove center', 'fitting-center-plugin' ); ?></button></p>
		<input type="hidden" class="fcp-image-id" name="<?php echo esc_attr( $base ); ?>[image_id]" value="<?php echo esc_attr( $center['image_id'] ); ?>">
		<input type="hidden" class="fcp-image-url" name="<?php echo esc_attr( $base ); ?>[image_url]" value="<?php echo esc_url( $center['image_url'] ); ?>">
		<button type="button" class="button fcp-upload-image"><?php esc_html_e( 'Choose image', 'fitting-center-plugin' ); ?></button>
		<button type="button" class="button fcp-remove-image"><?php esc_html_e( 'Remove image', 'fitting-center-plugin' ); ?></button>
		<img class="fcp-preview" src="<?php echo esc_url( $center['image_url'] ); ?>" alt="" style="<?php echo $center['image_url'] ? '' : 'display:none;'; ?>max-width:180px;max-height:120px;vertical-align:middle;margin:8px">
		<p><label><?php esc_html_e( 'Opening hours (one line per day)', 'fitting-center-plugin' ); ?><br><textarea name="<?php echo esc_attr( $base ); ?>[hours]" rows="3" class="large-text"><?php echo esc_textarea( $hours ); ?></textarea></label></p>
	</div>
	<?php
}

/** Inline admin JavaScript for repeatable fields and the media picker. */
function fcp_admin_script() {
	return <<<'JS'
jQuery(function($){var cityCount=$('#fcp-cities .fcp-city').length;function addCenter(section){var ci=section.data('city-index'),wrap=section.find('.fcp-city-centers'),ni=wrap.find('.fcp-center').length,html=$('#fcp-center-template').html().replaceAll('__CITY__',ci).replaceAll('__CENTER__',ni);wrap.append(html)}$('#fcp-add-city').on('click',function(){var html=$('#fcp-city-template').html().replaceAll('__CITY__',cityCount);$('#fcp-cities').append(html);cityCount++});$(document).on('click','.fcp-add-center',function(){addCenter($(this).closest('.fcp-city'))}).on('click','.fcp-remove-city',function(){$(this).closest('.fcp-city').remove()}).on('click','.fcp-remove-center',function(){$(this).closest('.fcp-center').remove()}).on('click','.fcp-upload-image',function(e){e.preventDefault();var row=$(this).closest('.fcp-center'),frame=wp.media({title:'Choose fitting center image',button:{text:'Use image'},multiple:false,library:{type:'image'}});frame.on('select',function(){var image=frame.state().get('selection').first().toJSON();row.find('.fcp-image-id').val(image.id);row.find('.fcp-image-url').val(image.url);row.find('.fcp-preview').attr('src',image.url).show()});frame.open()}).on('click','.fcp-remove-image',function(){var row=$(this).closest('.fcp-center');row.find('.fcp-image-id,.fcp-image-url').val('');row.find('.fcp-preview').attr('src','').hide()})});
JS;
}
