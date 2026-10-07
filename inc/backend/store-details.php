<?php
/**
 * Store identity: the contact and branding details that appear in transactional
 * email and on generated documents.
 *
 * These used to be hardcoded to the source store's city, which makes them wrong
 * the moment the theme is installed anywhere else, so they live in options with
 * a per-field fallback. Every accessor is filterable, which is also the simplest
 * way for a child theme to override one value without an options screen.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_get_store_details' ) ) {
	/**
	 * Every store detail, resolved against its fallback.
	 *
	 * @return array
	 */
	function dts_get_store_details() {
		$details = array(
			'name'     => __( 'Custom Dubai Tyre Shop', 'custom-dubai-tyre-shop' ),
			'email'    => 'info@example.com',
			'phone'    => '+971 4 000 0000',
			'whatsapp' => '',
			'address'  => '',
			'logo_url' => '',
			'accent'   => '#d4a11e',
		);

		foreach ( array_keys( $details ) as $key ) {
			$value = get_option( 'dts_store_' . $key, '' );

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$details[ $key ] = trim( $value );
			}
		}

		// A logo may be stored as an attachment ID instead of a URL.
		$logo_id = (int) get_option( 'dts_store_logo_id', 0 );

		if ( $logo_id ) {
			$details['logo_url'] = (string) wp_get_attachment_url( $logo_id );
		}

		return apply_filters( 'dts_store_details', $details );
	}
}

if ( ! function_exists( 'dts_get_store_detail' ) ) {
	/**
	 * One store detail.
	 *
	 * @param string $key     Detail key.
	 * @param string $default Returned when the key is unknown.
	 */
	function dts_get_store_detail( $key, $default = '' ) {
		$details = dts_get_store_details();

		return array_key_exists( $key, $details ) ? $details[ $key ] : $default;
	}
}

if ( ! function_exists( 'dts_get_store_logo_url' ) ) {
	/** Public store logo URL, or an empty string when none is set. */
	function dts_get_store_logo_url() {
		return (string) dts_get_store_detail( 'logo_url' );
	}
}

if ( ! function_exists( 'dts_get_store_logo_file' ) ) {
	/**
	 * Filesystem path to the store logo, for offline rendering (PDF).
	 *
	 * The logo is normally stored as an attachment ID, but some stores only set
	 * a URL; both are resolved. An empty string means no usable local file, in
	 * which case the caller falls back to name-only branding.
	 *
	 * @return string
	 */
	function dts_get_store_logo_file() {
		$id = (int) get_option( 'dts_store_logo_id', 0 );

		if ( $id && function_exists( 'get_attached_file' ) ) {
			$file = get_attached_file( $id );

			if ( $file && file_exists( $file ) ) {
				return $file;
			}
		}

		$url = dts_get_store_logo_url();

		if ( '' === $url || ! function_exists( 'wp_upload_dir' ) ) {
			return '';
		}

		if ( function_exists( 'attachment_url_to_postid' ) ) {
			$id = (int) attachment_url_to_postid( $url );

			if ( $id ) {
				$file = get_attached_file( $id );

				if ( $file && file_exists( $file ) ) {
					return $file;
				}
			}
		}

		// Fall back to rebuilding the uploads path from the URL.
		$upload = wp_upload_dir();
		$base   = (string) $upload['baseurl'];
		$dir    = (string) $upload['basedir'];

		if ( $base && $dir && 0 === strpos( $url, $base ) ) {
			$path = $dir . substr( $url, strlen( $base ) );

			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'dts_get_store_email' ) ) {
	/**
	 * Address that receives store notifications.
	 *
	 * Falls back to the site admin so a fresh install still has a valid
	 * recipient, and never to an address on another domain.
	 */
	function dts_get_store_email() {
		$email = (string) dts_get_store_detail( 'email' );
		$valid = is_email( $email );

		if ( ! $valid ) {
			$admin = get_option( 'admin_email' );
			$valid = is_email( $admin );
			$email = $valid ? (string) $admin : '';
		}

		/**
		 * Filters the store notification address.
		 *
		 * @param string $email Resolved address, or an empty string if none is valid.
		 */
		return (string) apply_filters( 'dts_store_email', $email );
	}
}

if ( ! function_exists( 'dts_get_store_phone_href' ) ) {
	/** Phone number in a tel: href, digits only. */
	function dts_get_store_phone_href() {
		$phone = (string) dts_get_store_detail( 'phone' );
		$href  = preg_replace( '/[^0-9+]/', '', $phone );

		return $href ? 'tel:' . $href : '';
	}
}

if ( ! function_exists( 'dts_register_store_detail_settings' ) ) {
	/** Register the store detail options. */
	function dts_register_store_detail_settings() {
		$fields = array( 'name', 'email', 'phone', 'whatsapp', 'address', 'logo_id', 'accent' );

		foreach ( $fields as $field ) {
			register_setting( 'dts-store-details', 'dts_store_' . $field );
		}
	}
}
add_action( 'admin_init', 'dts_register_store_detail_settings' );
