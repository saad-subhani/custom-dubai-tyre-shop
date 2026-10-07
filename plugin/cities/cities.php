<?php
/**
 * Plugin Name: Cities and Areas
 * Description: Create linked city and area landing pages at /city/ and /city/area/.
 * Version: 1.0.0
 * Author: Custom Dubai Tyre Shop
 * Text Domain: cities-areas
 */

defined( 'ABSPATH' ) || exit;

/** Read the configured city catalogue. */
function ca_get_cities() {
	$cities = get_option( 'ca_cities', array() );
	return is_array( $cities ) ? $cities : array();
}

/** Add exact rewrite rules for all configured city and area paths. */
function ca_add_rewrite_rules() {
	foreach ( ca_get_cities() as $city_slug => $city ) {
		$city_slug = sanitize_title( $city_slug );
		if ( '' === $city_slug ) {
			continue;
		}
		if ( ! empty( $city['areas'] ) && is_array( $city['areas'] ) ) {
			foreach ( $city['areas'] as $area_slug => $area ) {
				$area_slug = sanitize_title( $area_slug );
				if ( '' !== $area_slug ) {
					add_rewrite_rule(
						'^' . preg_quote( $city_slug, '#' ) . '/' . preg_quote( $area_slug, '#' ) . '/?$',
						'index.php?ca_city=' . rawurlencode( $city_slug ) . '&ca_area=' . rawurlencode( $area_slug ),
						'top'
					);
				}
			}
		}
		add_rewrite_rule(
			'^' . preg_quote( $city_slug, '#' ) . '/?$',
			'index.php?ca_city=' . rawurlencode( $city_slug ),
			'top'
		);
	}
}
add_action( 'init', 'ca_add_rewrite_rules' );

/** Register route variables with WordPress. */
function ca_register_query_vars( $vars ) {
	$vars[] = 'ca_city';
	$vars[] = 'ca_area';
	return $vars;
}
add_filter( 'query_vars', 'ca_register_query_vars' );

/** Admin menu entry. */
function ca_add_admin_menu() {
	add_menu_page( __( 'Cities and Areas', 'cities-areas' ), __( 'Cities and Areas', 'cities-areas' ), 'manage_options', 'cities-areas', 'ca_render_admin_page', 'dashicons-location-alt', 58 );
}
add_action( 'admin_menu', 'ca_add_admin_menu' );

/** Load the WordPress editor and admin controls on this plugin screen. */
function ca_admin_assets( $hook ) {
	if ( 'toplevel_page_cities-areas' !== $hook ) {
		return;
	}
	wp_enqueue_editor();
	wp_add_inline_script( 'editor', "(function(){function initEditor(id){if(window.wp&&wp.editor&&document.getElementById(id)){wp.editor.initialize(id,{tinymce:{wpautop:true,toolbar1:'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo'},quicktags:true,mediaButtons:false})}}function removeEditors(root){if(!window.wp||!wp.editor)return;root.querySelectorAll('textarea.ca-rich-editor').forEach(function(el){wp.editor.remove(el.id)})}var list=document.getElementById('ca-city-list'),nextCity=0;list.querySelectorAll('.ca-city').forEach(function(city){nextCity=Math.max(nextCity,Number(city.dataset.cityIndex)+1)});document.getElementById('ca-add-city').addEventListener('click',function(){var html=document.getElementById('ca-city-template').innerHTML.replaceAll('__CITY__',nextCity);list.insertAdjacentHTML('beforeend',html);var panel=list.lastElementChild;panel.querySelectorAll('textarea.ca-rich-editor').forEach(function(el){initEditor(el.id)});nextCity++});document.addEventListener('click',function(e){if(e.target.classList.contains('ca-remove-city')){var panel=e.target.closest('.ca-city');removeEditors(panel);panel.remove()}if(e.target.classList.contains('ca-remove-area')){var row=e.target.closest('.ca-area');removeEditors(row);row.remove()}if(e.target.classList.contains('ca-add-area')){var panel=e.target.closest('.ca-city'),index=panel.dataset.cityIndex,wrap=panel.querySelector('.ca-area-list'),areaIndex=Number(panel.dataset.nextAreaIndex||0),html=document.getElementById('ca-area-template').innerHTML.replaceAll('__CITY__',index).replaceAll('__AREA__',areaIndex);wrap.insertAdjacentHTML('beforeend',html);panel.dataset.nextAreaIndex=areaIndex+1;var editor=wrap.lastElementChild.querySelector('textarea.ca-rich-editor');if(editor)initEditor(editor.id)}})})();" );
}
add_action( 'admin_enqueue_scripts', 'ca_admin_assets' );

/** Save the catalogue and refresh the custom routes. */
function ca_save_cities() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage cities.', 'cities-areas' ) );
	}
	check_admin_referer( 'ca_save_cities' );
	$input = isset( $_POST['ca_cities'] ) ? wp_unslash( $_POST['ca_cities'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$cities = array();
	foreach ( (array) $input as $city ) {
		$city_name = sanitize_text_field( isset( $city['name'] ) ? $city['name'] : '' );
		$city_slug = sanitize_title( isset( $city['slug'] ) ? $city['slug'] : $city_name );
		if ( '' === $city_name || '' === $city_slug || isset( $cities[ $city_slug ] ) ) {
			continue;
		}
		$areas = array();
		foreach ( (array) ( isset( $city['areas'] ) ? $city['areas'] : array() ) as $area ) {
			$area_name = sanitize_text_field( isset( $area['name'] ) ? $area['name'] : '' );
			$area_slug = sanitize_title( isset( $area['slug'] ) ? $area['slug'] : $area_name );
			if ( '' === $area_name || '' === $area_slug || isset( $areas[ $area_slug ] ) ) {
				continue;
			}
			$areas[ $area_slug ] = array(
				'name'        => $area_name,
				'slug'        => $area_slug,
				'description' => wp_kses_post( isset( $area['description'] ) ? $area['description'] : '' ),
			);
		}
		$cities[ $city_slug ] = array(
			'name'        => $city_name,
			'slug'        => $city_slug,
			'description' => wp_kses_post( isset( $city['description'] ) ? $city['description'] : '' ),
			'areas'       => $areas,
		);
	}
	update_option( 'ca_cities', $cities );
	// Re-register the just-saved slugs before flushing so the new URLs are included.
	ca_add_rewrite_rules();
	flush_rewrite_rules( false );
	wp_safe_redirect( add_query_arg( array( 'page' => 'cities-areas', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_ca_save_cities', 'ca_save_cities' );

/** Output an area editor row. */
function ca_render_area_row( $city_index, $area_index, $area = array(), $template = false ) {
	$base = 'ca_cities[' . $city_index . '][areas][' . $area_index . ']';
	?>
	<div class="ca-area" style="border-left:3px solid #c3c4c7;margin:12px 0;padding:8px 12px">
		<p><label><?php esc_html_e( 'Area name', 'cities-areas' ); ?> <input class="regular-text" name="<?php echo esc_attr( $base ); ?>[name]" value="<?php echo esc_attr( isset( $area['name'] ) ? $area['name'] : '' ); ?>" placeholder="Al Quoz"></label>
		<label><?php esc_html_e( 'URL slug', 'cities-areas' ); ?> <input class="regular-text" name="<?php echo esc_attr( $base ); ?>[slug]" value="<?php echo esc_attr( isset( $area['slug'] ) ? $area['slug'] : '' ); ?>" placeholder="al-quoz"></label>
		<button type="button" class="button-link-delete ca-remove-area"><?php esc_html_e( 'Remove area', 'cities-areas' ); ?></button></p>
		<p><strong><?php esc_html_e( 'Area description', 'cities-areas' ); ?></strong><?php if ( $template ) : ?>
		<textarea class="large-text ca-rich-editor" id="ca-area-description-<?php echo esc_attr( $city_index . '-' . $area_index ); ?>" rows="5" name="<?php echo esc_attr( $base ); ?>[description]"></textarea>
		<?php else :
		wp_editor( isset( $area['description'] ) ? $area['description'] : '', 'ca_area_description_' . sanitize_key( (string) $city_index ) . '_' . sanitize_key( (string) $area_index ), array(
			'textarea_name' => $base . '[description]',
			'textarea_rows' => 5,
			'editor_class'  => 'ca-rich-editor',
			'media_buttons' => false,
			'teeny'         => true,
			'quicktags'     => true,
			'tinymce'       => array( 'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo' ),
		) );
		endif; ?></p>
	</div>
	<?php
}

/** Render the city and area management screen. */
function ca_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$cities = ca_get_cities();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Cities and Areas', 'cities-areas' ); ?></h1>
		<p><?php esc_html_e( 'Each city gets a page at /city/. Areas belong to their city and use /city/area/.', 'cities-areas' ); ?></p>
		<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cities and areas saved. Their URLs are ready.', 'cities-areas' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ca_save_cities">
			<?php wp_nonce_field( 'ca_save_cities' ); ?>
			<div id="ca-city-list">
			<?php $city_index = 0; foreach ( $cities as $city ) : ?>
				<?php ca_render_city_panel( $city_index, $city ); $city_index++; ?>
			<?php endforeach; ?>
			</div>
			<p class="ca-add-city-action"><button type="button" class="button" id="ca-add-city"><?php esc_html_e( 'Add city', 'cities-areas' ); ?></button></p>
			<?php submit_button( __( 'Save Cities and Areas', 'cities-areas' ) ); ?>
		</form>
		<template id="ca-city-template"><?php ca_render_city_panel( '__CITY__', array( 'name' => '', 'slug' => '', 'description' => '', 'areas' => array() ), true ); ?></template>
		<template id="ca-area-template"><?php ca_render_area_row( '__CITY__', '__AREA__', array(), true ); ?></template>
	</div>
	<style>
		.ca-add-city-action, .ca-city .ca-add-area { opacity: 0; visibility: hidden; transition: opacity .15s ease; }
		#ca-city-list:hover + .ca-add-city-action, .ca-add-city-action:hover, .ca-add-city-action:focus-within, #ca-city-list:empty + .ca-add-city-action,
		.ca-city:hover .ca-add-area, .ca-city:focus-within .ca-add-area { opacity: 1; visibility: visible; }
		@media (hover: none) { .ca-add-city-action, .ca-city .ca-add-area { opacity: 1; visibility: visible; } }
	</style>
	<?php
}

/** Output one city panel and its linked areas. */
function ca_render_city_panel( $city_index, $city, $template = false ) {
	?>
	<section class="postbox ca-city" data-city-index="<?php echo esc_attr( $city_index ); ?>" data-next-area-index="<?php echo esc_attr( count( (array) ( isset( $city['areas'] ) ? $city['areas'] : array() ) ) ); ?>" style="padding:16px;margin:16px 0">
		<p><label><strong><?php esc_html_e( 'City name', 'cities-areas' ); ?></strong> <input class="regular-text" name="ca_cities[<?php echo esc_attr( $city_index ); ?>][name]" value="<?php echo esc_attr( isset( $city['name'] ) ? $city['name'] : '' ); ?>" placeholder="Dubai" required></label>
		<label><strong><?php esc_html_e( 'URL slug', 'cities-areas' ); ?></strong> <input class="regular-text" name="ca_cities[<?php echo esc_attr( $city_index ); ?>][slug]" value="<?php echo esc_attr( isset( $city['slug'] ) ? $city['slug'] : '' ); ?>" placeholder="dubai" required></label>
		<button type="button" class="button-link-delete ca-remove-city"><?php esc_html_e( 'Remove city', 'cities-areas' ); ?></button></p>
		<p><strong><?php esc_html_e( 'City description', 'cities-areas' ); ?></strong><?php if ( $template ) : ?>
		<textarea class="large-text ca-rich-editor" id="ca-city-description-<?php echo esc_attr( $city_index ); ?>" rows="5" name="ca_cities[<?php echo esc_attr( $city_index ); ?>][description]"></textarea>
		<?php else :
		wp_editor( isset( $city['description'] ) ? $city['description'] : '', 'ca_city_description_' . sanitize_key( (string) $city_index ), array(
			'textarea_name' => 'ca_cities[' . $city_index . '][description]',
			'textarea_rows' => 5,
			'editor_class'  => 'ca-rich-editor',
			'media_buttons' => false,
			'teeny'         => true,
			'quicktags'     => true,
			'tinymce'       => array( 'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo' ),
		) );
		endif; ?></p>
		<h3><?php esc_html_e( 'Areas in this city', 'cities-areas' ); ?></h3>
		<div class="ca-area-list">
		<?php $area_index = 0; foreach ( (array) ( isset( $city['areas'] ) ? $city['areas'] : array() ) as $area ) : ?>
		<?php ca_render_area_row( $city_index, $area_index, $area, $template ); $area_index++; ?>
		<?php endforeach; ?>
		</div>
		<button type="button" class="button ca-add-area"><?php esc_html_e( 'Add area', 'cities-areas' ); ?></button>
	</section>
	<?php
}

/** Resolve configured route values and render the matching landing page. */
function ca_render_location_page() {
	$city_slug = sanitize_title( get_query_var( 'ca_city' ) );
	if ( '' === $city_slug ) {
		return;
	}
	$cities = ca_get_cities();
	if ( ! isset( $cities[ $city_slug ] ) ) {
		return;
	}
	$city = $cities[ $city_slug ];
	$area_slug = sanitize_title( get_query_var( 'ca_area' ) );
	$area = '' !== $area_slug && isset( $city['areas'][ $area_slug ] ) ? $city['areas'][ $area_slug ] : null;
	if ( '' !== $area_slug && ! $area ) {
		return;
	}
	$status = get_query_var( 'ca_city' ) ? 200 : 404;
	status_header( $status );
	get_header();
	$title = $area ? $area['name'] . ', ' . $city['name'] : $city['name'];
	$description = $area ? $area['description'] : $city['description'];
	?>
	<main class="ca-location-page container" style="max-width:1200px;margin:0 auto;padding:64px 24px">
		<nav aria-label="<?php esc_attr_e( 'Breadcrumb', 'cities-areas' ); ?>" style="margin-bottom:24px">
			<a href="<?php echo esc_url( home_url( '/' . trailingslashit( $city_slug ) ) ); ?>"><?php echo esc_html( $city['name'] ); ?></a>
			<?php if ( $area ) : ?> <span aria-hidden="true">/</span> <span aria-current="page"><?php echo esc_html( $area['name'] ); ?></span><?php endif; ?>
		</nav>
		<h1><?php echo esc_html( $title ); ?></h1>
		<?php if ( $description ) : ?><div class="ca-location-page__description"><?php echo wp_kses_post( wpautop( $description ) ); ?></div><?php endif; ?>
		<?php if ( $area ) : ?>
			<p><a href="<?php echo esc_url( home_url( '/' . trailingslashit( $city_slug ) ) ); ?>">&larr; <?php printf( esc_html__( 'All areas in %s', 'cities-areas' ), esc_html( $city['name'] ) ); ?></a></p>
		<?php elseif ( ! empty( $city['areas'] ) ) : ?>
			<section class="ca-location-page__areas" aria-labelledby="ca-areas-heading">
				<h2 id="ca-areas-heading"><?php printf( esc_html__( 'Areas in %s', 'cities-areas' ), esc_html( $city['name'] ) ); ?></h2>
				<ul>
				<?php foreach ( $city['areas'] as $area_item ) : ?>
					<li><a href="<?php echo esc_url( home_url( '/' . trailingslashit( $city_slug . '/' . $area_item['slug'] ) ) ); ?>"><?php echo esc_html( $area_item['name'] ); ?></a></li>
				<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	</main>
	<?php
	get_footer();
	exit;
}
add_action( 'template_redirect', 'ca_render_location_page' );

/** Set a useful browser title for generated city and area pages. */
function ca_document_title( $title ) {
	$city_slug = sanitize_title( get_query_var( 'ca_city' ) );
	$cities = ca_get_cities();
	if ( ! isset( $cities[ $city_slug ] ) ) {
		return $title;
	}
	$area_slug = sanitize_title( get_query_var( 'ca_area' ) );
	if ( isset( $cities[ $city_slug ]['areas'][ $area_slug ] ) ) {
		return $cities[ $city_slug ]['areas'][ $area_slug ]['name'] . ', ' . $cities[ $city_slug ]['name'];
	}
	return $cities[ $city_slug ]['name'];
}
add_filter( 'pre_get_document_title', 'ca_document_title' );
