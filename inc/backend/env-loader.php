<?php
/**
 * Load the theme .env file and define its constants.
 *
 * The file is gitignored, so Git deploys never carry it. Admins get a notice
 * when the Wheel-Size key is missing.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_load_env' ) ) {
	/**
	 * Parse the theme root .env and define each KEY=VALUE pair as a constant.
	 *
	 * Existing constants always win so wp-config.php can override.
	 */
	function dts_load_env() {
		if ( defined( 'WHEEL_SIZE_API_KEY' ) ) {
			return;
		}

		$env_file = get_template_directory() . '/.env';

		if ( ! file_exists( $env_file ) || ! is_readable( $env_file ) ) {
			return;
		}

		$lines = file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		if ( empty( $lines ) ) {
			return;
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line || '#' === $line[0] || false === strpos( $line, '=' ) ) {
				continue;
			}

			list( $key, $value ) = explode( '=', $line, 2 );

			$key   = trim( $key );
			$value = trim( $value );

			// Strip a trailing inline comment that is not inside quotes.
			if ( '' !== $value && '"' !== $value[0] ) {
				$hash = strpos( $value, ' #' );
				if ( false !== $hash ) {
					$value = rtrim( substr( $value, 0, $hash ) );
				}
			}

			$value = trim( $value, "\"' " );

			if ( '' !== $key && ! defined( $key ) ) {
				define( $key, $value );
			}
		}
	}
}

if ( ! function_exists( 'dts_get_api_key' ) ) {
	/** Whether the Wheel-Size API key is available. */
	function dts_get_api_key() {
		if ( ! defined( 'WHEEL_SIZE_API_KEY' ) ) {
			return '';
		}

		return trim( (string) WHEEL_SIZE_API_KEY );
	}
}

// plugins_loaded runs before admin_menu; init is the safety net.
add_action( 'plugins_loaded', 'dts_load_env', 0 );
add_action( 'init', 'dts_load_env', 0 );
