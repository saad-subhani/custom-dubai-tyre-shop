<?php
/**
 * Single gateway for all Wheel-Size.com API access.
 *
 * Adds transient caching, self-tracked daily usage, serve-stale fallback and a
 * quota circuit breaker, so a vendor outage or an exhausted quota degrades to
 * cached data instead of breaking the store.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DTS_WS_API_BASE' ) ) {
	define( 'DTS_WS_API_BASE', 'https://api.wheel-size.com/v2/' );
}
if ( ! defined( 'DTS_WS_API_DAILY_LIMIT' ) ) {
	define( 'DTS_WS_API_DAILY_LIMIT', 5000 );
}

if ( ! function_exists( 'dts_ws_api_get' ) ) {
	/**
	 * Cached, counted, fail-safe GET against the Wheel-Size API.
	 *
	 * @param string $path  Path after /v2/, e.g. 'makes/'. Leading slash ignored.
	 * @param array  $args  Query args; the user_key is added automatically.
	 * @param int    $ttl   Cache lifetime in seconds.
	 * @param bool   $debug Return a diagnostics array instead of data.
	 * @return array|null Parsed response, or null when nothing can be served.
	 */
	function dts_ws_api_get( $path, $args = array(), $ttl = DAY_IN_SECONDS, $debug = false ) {
		$debug_info = array();

		$args = is_array( $args ) ? $args : array();

		// The cache key is built WITHOUT the key so it never reaches the database.
		$cache_url = DTS_WS_API_BASE . ltrim( $path, '/' ) . '?' . http_build_query( $args );
		$cache_key = 'dts_ws_' . md5( $cache_url );

		$key = dts_get_api_key();

		if ( '' !== $key ) {
			$args['user_key'] = $key;
		}

		$url = DTS_WS_API_BASE . ltrim( $path, '/' ) . '?' . http_build_query( $args );

		// 1. Fresh cache hit — no API cost.
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			$debug_info['step'] = 'cache_hit';

			return $debug ? $debug_info : $cached;
		}

		$debug_info['cache_hit'] = false;

		// 2. Circuit breaker — quota is known to be gone today.
		if ( get_transient( 'dts_ws_quota_exceeded' ) ) {
			$debug_info['step'] = 'circuit_breaker_blocked';

			return $debug ? $debug_info : dts_ws_stale_fallback( $cache_key );
		}

		if ( '' === $key ) {
			$debug_info['step'] = 'missing_api_key';

			return $debug ? $debug_info : dts_ws_stale_fallback( $cache_key );
		}

		// 3. Real outbound call (counted).
		dts_ws_count_hit();

		$debug_info['step']          = 'making_request';
		$debug_info['request_url']   = $url;

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$debug_info['step']  = 'wp_error';
			$debug_info['error'] = $response->get_error_message();

			return $debug ? $debug_info : dts_ws_stale_fallback( $cache_key );
		}

		$code                = (int) wp_remote_retrieve_response_code( $response );
		$debug_info['response_code'] = $code;

		if ( 403 === $code || 429 === $code ) {
			// Quota exhausted — trip the breaker until the vendor resets at 00:00 GMT.
			$debug_info['step'] = 'quota_exceeded';
			dts_ws_trip_breaker();

			return $debug ? $debug_info : dts_ws_stale_fallback( $cache_key );
		}

		if ( 200 !== $code ) {
			$debug_info['step'] = 'non_200';

			return $debug ? $debug_info : dts_ws_stale_fallback( $cache_key );
		}

		$body                   = wp_remote_retrieve_body( $response );
		$debug_info['body_length'] = strlen( $body );
		$data                   = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			$debug_info['step']         = 'json_decode_failed';
			$debug_info['body_preview'] = substr( $body, 0, 200 );

			return $debug ? $debug_info : dts_ws_stale_fallback( $cache_key );
		}

		// 4. Store a fresh copy and a no-TTL serve-stale copy.
		set_transient( $cache_key, $data, $ttl );
		update_option( $cache_key . '_backup', $data, false );

		$debug_info['step']       = 'success';
		$debug_info['data_count'] = isset( $data['data'] ) && is_array( $data['data'] ) ? count( $data['data'] ) : 0;

		return $debug ? $debug_info : $data;
	}
}

if ( ! function_exists( 'dts_ws_stale_fallback' ) ) {
	/** Last known good response for a cache key, or null. */
	function dts_ws_stale_fallback( $cache_key ) {
		$stale = get_option( $cache_key . '_backup', null );

		return is_array( $stale ) ? $stale : null;
	}
}

if ( ! function_exists( 'dts_ws_trip_breaker' ) ) {
	/** Flag the quota as exhausted until the vendor resets at 00:00 GMT. */
	function dts_ws_trip_breaker() {
		$gmt_midnight = strtotime( 'tomorrow 00:00 GMT' );

		set_transient( 'dts_ws_quota_exceeded', 1, max( 60, $gmt_midnight - time() ) );
	}
}

if ( ! function_exists( 'dts_ws_count_hit' ) ) {
	/** Self-tracked daily hit counter — the vendor exposes no usage endpoint. */
	function dts_ws_count_hit() {
		$today = gmdate( 'Ymd' );
		$key   = 'dts_ws_hits_' . $today;

		if ( false === get_transient( $key ) ) {
			set_transient( $key, 0, DAY_IN_SECONDS );
		}

		$count = (int) get_transient( $key ) + 1;

		set_transient( $key, $count, DAY_IN_SECONDS );

		$log   = get_option( 'dts_ws_hits_log', array() );
		$log   = is_array( $log ) ? $log : array();
		$month = gmdate( 'Ym' );

		$log[ $month ][ $today ] = $count;

		krsort( $log );
		update_option( 'dts_ws_hits_log', array_slice( $log, 0, 3, true ), false );
	}
}

if ( ! function_exists( 'dts_ws_rate_limited' ) ) {
	/**
	 * Sliding-window per-IP rate limiter for the public vehicle endpoints.
	 *
	 * @param string $bucket Logical bucket so endpoints get separate budgets.
	 * @param int    $max    Requests allowed per window.
	 * @param int    $window Window length in seconds.
	 */
	function dts_ws_rate_limited( $bucket = 'vehicle', $max = 30, $window = 60 ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'dts_ws_rl_' . md5( $bucket . '|' . $ip );

		$count = (int) get_transient( $key );

		if ( $count >= $max ) {
			return true;
		}

		set_transient( $key, $count + 1, $window );

		return false;
	}
}

if ( ! function_exists( 'dts_ws_usage_today' ) ) {
	/** Requests made to the vendor so far today. */
	function dts_ws_usage_today() {
		return (int) get_transient( 'dts_ws_hits_' . gmdate( 'Ymd' ) );
	}
}

if ( ! function_exists( 'dts_ws_breaker_active' ) ) {
	/** Whether the quota circuit breaker is currently tripped. */
	function dts_ws_breaker_active() {
		return (bool) get_transient( 'dts_ws_quota_exceeded' );
	}
}

/* ------------------------------------------------------------------------- */
/* Local brand map — removes /makes/ from the request path entirely          */
/* ------------------------------------------------------------------------- */

if ( ! function_exists( 'dts_ws_refresh_brand_map' ) ) {
	/** Fetch the makes list once and store it locally with its logos. */
	function dts_ws_refresh_brand_map() {
		$data = dts_ws_api_get( 'makes/', array( 'ordering' => 'slug', 'region' => 'medm' ), DAY_IN_SECONDS );

		if ( empty( $data['data'] ) || ! is_array( $data['data'] ) ) {
			// Keep yesterday's map on failure.
			return false;
		}

		$map = array();

		foreach ( $data['data'] as $make ) {
			if ( empty( $make['slug'] ) ) {
				continue;
			}

			$map[ $make['slug'] ] = array(
				'name' => isset( $make['name'] ) ? $make['name'] : $make['slug'],
				'logo' => isset( $make['logo'] ) ? $make['logo'] : '',
			);
		}

		if ( ! empty( $map ) ) {
			update_option( 'dts_ws_brand_map', $map, false );
		}

		return ! empty( $map );
	}
}

if ( ! function_exists( 'dts_get_brand_map' ) ) {
	/** Local make list, built once on cold start. Zero API cost at read time. */
	function dts_get_brand_map() {
		$map = get_option( 'dts_ws_brand_map', array() );

		if ( empty( $map ) ) {
			dts_ws_refresh_brand_map();
			$map = get_option( 'dts_ws_brand_map', array() );
		}

		return is_array( $map ) ? $map : array();
	}
}

if ( ! function_exists( 'dts_ws_cache_warm' ) ) {
	/** Pre-load the hottest read paths so first visitors hit cache, not the API. */
	function dts_ws_cache_warm() {
		dts_ws_api_get( 'makes/', array( 'ordering' => 'slug', 'region' => 'medm' ), DAY_IN_SECONDS );

		foreach ( dts_ws_top_makes( 20 ) as $make ) {
			dts_ws_api_get( 'models/', array( 'make' => $make ), DAY_IN_SECONDS );
		}
	}
}

if ( ! function_exists( 'dts_ws_top_makes' ) ) {
	/**
	 * Makes worth pre-caching.
	 *
	 * Replace with real analytics data when available.
	 */
	function dts_ws_top_makes( $limit = 20 ) {
		$common = array(
			'toyota',
			'nissan',
			'mitsubishi',
			'mazda',
			'honda',
			'ford',
			'hyundai',
			'kia',
			'chevrolet',
			'bmw',
			'mercedes-benz',
			'audi',
			'volkswagen',
			'land-rover',
			'jeep',
			'subaru',
			'lexus',
			'infiniti',
			'porsche',
			'tesla',
		);

		return array_slice( $common, 0, $limit );
	}
}

/* ------------------------------------------------------------------------- */
/* Scheduling                                                                 */
/* ------------------------------------------------------------------------- */

add_action(
	'after_setup_theme',
	function () {
		if ( ! wp_next_scheduled( 'dts_ws_refresh_brands_event' ) ) {
			wp_schedule_event( strtotime( '03:15' ), 'daily', 'dts_ws_refresh_brands_event' );
		}
		if ( ! wp_next_scheduled( 'dts_ws_warm_cache_event' ) ) {
			wp_schedule_event( strtotime( '00:20' ), 'daily', 'dts_ws_warm_cache_event' );
		}
	}
);

add_action( 'dts_ws_refresh_brands_event', 'dts_ws_refresh_brand_map' );
add_action( 'dts_ws_warm_cache_event', 'dts_ws_cache_warm' );

/* ------------------------------------------------------------------------- */
/* Admin surfacing                                                            */
/* ------------------------------------------------------------------------- */

add_action( 'admin_notices', 'dts_ws_api_key_notice' );
if ( ! function_exists( 'dts_ws_api_key_notice' ) ) {
	/** Warn admins when .env or the API key is missing. */
	function dts_ws_api_key_notice() {
		if ( ! current_user_can( 'manage_options' ) || '' !== dts_get_api_key() ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>Wheel-Size API:</strong> <code>.env</code> is not loaded, so <code>WHEEL_SIZE_API_KEY</code> is undefined and vehicle lookups return 503. Upload <code>.env</code> to the theme root (<code>%s</code>).</p></div>',
			esc_html( get_template_directory() . '/.env' )
		);
	}
}

add_action( 'wp_dashboard_setup', 'dts_ws_register_dashboard_widget' );
if ( ! function_exists( 'dts_ws_register_dashboard_widget' ) ) {
	/** Daily usage gauge on the WordPress dashboard. */
	function dts_ws_register_dashboard_widget() {
		wp_add_dashboard_widget(
			'dts_ws_usage_widget',
			'Wheel-Size API Usage (Today)',
			'dts_ws_render_dashboard_widget'
		);
	}
}

if ( ! function_exists( 'dts_ws_render_dashboard_widget' ) ) {
	/** Dashboard widget body. */
	function dts_ws_render_dashboard_widget() {
		$hits  = dts_ws_usage_today();
		$limit = DTS_WS_API_DAILY_LIMIT;
		$pct   = $limit > 0 ? min( 100, round( $hits / $limit * 100 ) ) : 0;
		$color = $pct > 90 ? '#d63638' : ( $pct > 70 ? '#dba617' : '#00a32a' );

		echo "<div style='font-size:14px'>";
		printf(
			'<strong style="color:%1$s;font-size:22px">%2$s</strong> / %3$s hits today (%4$s%%)',
			esc_attr( $color ),
			esc_html( number_format_i18n( $hits ) ),
			esc_html( number_format_i18n( $limit ) ),
			esc_html( $pct )
		);
		echo "<div style='background:#f0f0f1;height:10px;border-radius:5px;margin-top:8px'>";
		printf(
			'<div style="background:%1$s;height:10px;width:%2$s%%;border-radius:5px"></div>',
			esc_attr( $color ),
			esc_attr( $pct )
		);
		echo '</div>';

		if ( dts_ws_breaker_active() ) {
			echo '<p><strong style="color:#d63638">Circuit breaker active</strong> &mdash; serving cached data until 00:00 GMT.</p>';
		}

		// One alert per day once usage is critical.
		if ( $pct >= 90 && ! get_transient( 'dts_ws_alert_sent_' . gmdate( 'Ymd' ) ) ) {
			wp_mail(
				get_option( 'admin_email' ),
				'Wheel-Size API near daily limit',
				sprintf( 'Hits today: %1$s/%2$s', $hits, $limit )
			);
			set_transient( 'dts_ws_alert_sent_' . gmdate( 'Ymd' ), 1, DAY_IN_SECONDS );
		}

		echo '</div>';
	}
}

add_action( 'admin_bar_menu', 'dts_ws_admin_bar_usage', 999 );
if ( ! function_exists( 'dts_ws_admin_bar_usage' ) ) {
	/** Compact usage indicator in the admin bar. */
	function dts_ws_admin_bar_usage( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$hits  = dts_ws_usage_today();
		$limit = DTS_WS_API_DAILY_LIMIT;
		$pct   = $limit > 0 ? min( 100, round( $hits / $limit * 100 ) ) : 0;
		$color = $pct > 90 ? '#d63638' : ( $pct > 70 ? '#dba617' : '#00a32a' );

		$wp_admin_bar->add_node(
			array(
				'id'    => 'dts-ws-api-usage',
				'title' => sprintf(
					'<span style="color:%1$s">WS API: %2$s/%3$s (%4$s%%)</span>',
					esc_attr( $color ),
					esc_html( $hits ),
					esc_html( $limit ),
					esc_html( $pct )
				),
				'href'  => admin_url( 'index.php' ),
			)
		);
	}
}

add_action( 'admin_menu', 'dts_ws_register_tools_page' );
if ( ! function_exists( 'dts_ws_register_tools_page' ) ) {
	/** Register the Wheel-Size maintenance screen. */
	function dts_ws_register_tools_page() {
		add_management_page(
			'Wheel-Size API Tools',
			'WS API Tools',
			'manage_options',
			'dts-ws-api-tools',
			'dts_ws_api_tools_page'
		);
	}
}

if ( ! function_exists( 'dts_ws_api_tools_page' ) ) {
	/**
	 * Maintenance screen: brand-map refresh, diagnostics, cache warming, cache purge.
	 */
	function dts_ws_api_tools_page() {
		$message    = '';
		$brand_map  = dts_get_brand_map();
		$hits_today = dts_ws_usage_today();

		if ( isset( $_POST['dts_ws_refresh_brands'] ) && check_admin_referer( 'dts_ws_refresh_brands_nonce' ) ) {
			if ( '' === dts_get_api_key() ) {
				$message = '<div class="notice notice-error is-dismissible"><p><strong>Cannot refresh:</strong> <code>WHEEL_SIZE_API_KEY</code> is not defined. Upload <code>.env</code> to the theme root.</p></div>';
			} else {
				delete_transient( 'dts_ws_quota_exceeded' );

				if ( dts_ws_refresh_brand_map() ) {
					$brand_map = dts_get_brand_map();
					$message  = sprintf(
						'<div class="notice notice-success is-dismissible"><p>Brand map refreshed (%d brands).</p></div>',
						count( $brand_map )
					);
				} else {
					$message = '<div class="notice notice-error is-dismissible"><p>Failed to refresh the brand map. Check the circuit breaker state and the vendor quota.</p></div>';
				}
			}
		}

		if ( isset( $_POST['dts_ws_warm_cache'] ) && check_admin_referer( 'dts_ws_warm_cache_nonce' ) ) {
			dts_ws_cache_warm();
			$message = '<div class="notice notice-success is-dismissible"><p>Cache warming complete.</p></div>';
		}

		if ( isset( $_POST['dts_ws_clear_cache'] ) && check_admin_referer( 'dts_ws_clear_cache_nonce' ) ) {
			dts_ws_clear_cache();
			$message = '<div class="notice notice-success is-dismissible"><p>Wheel-Size transients and stale backups cleared.</p></div>';
		}

		if ( isset( $_POST['dts_ws_test_call'] ) && check_admin_referer( 'dts_ws_test_call_nonce' ) ) {
			$message = sprintf(
				'<div class="notice notice-info is-dismissible"><p><strong>Environment diagnostics:</strong><pre style="margin:10px 0;">%s</pre></p></div>',
				esc_html( print_r( dts_ws_diagnostics(), true ) )
			);
		}
		?>
		<div class="wrap">
			<h1>Wheel-Size API Tools</h1>
			<?php echo $message; ?>

			<h2>Brand Map</h2>
			<p>Brands stored locally: <strong><?php echo esc_html( count( $brand_map ) ); ?></strong></p>
			<form method="post" style="display:inline;">
				<?php wp_nonce_field( 'dts_ws_refresh_brands_nonce' ); ?>
				<button type="submit" name="dts_ws_refresh_brands" class="button button-primary" style="margin-right:10px;">Refresh Brand Map Now</button>
			</form>
			<p class="description">Fetches the makes list and stores it locally so renders cost no API calls. Runs daily at 03:15. Resets the circuit breaker first.</p>

			<h2>Diagnostics</h2>
			<form method="post" style="display:inline;">
				<?php wp_nonce_field( 'dts_ws_test_call_nonce' ); ?>
				<button type="submit" name="dts_ws_test_call" class="button button-secondary" style="margin-right:10px;">Run Environment Test</button>
			</form>
			<p class="description">Checks .env presence, object-cache type, outbound HTTPS reachability and runs one debug call through the gateway. Costs one API hit.</p>

			<h2>Cache Warming</h2>
			<form method="post" style="display:inline;">
				<?php wp_nonce_field( 'dts_ws_warm_cache_nonce' ); ?>
				<button type="submit" name="dts_ws_warm_cache" class="button button-secondary" style="margin-right:10px;">Warm Cache Now</button>
			</form>
			<p class="description">Pre-loads makes plus the top 20 models. Runs daily at 00:20.</p>

			<h2>Clear All Wheel-Size Cache</h2>
			<form method="post" style="display:inline;">
				<?php wp_nonce_field( 'dts_ws_clear_cache_nonce' ); ?>
				<button type="submit" name="dts_ws_clear_cache" class="button button-secondary" style="margin-right:10px;">Clear Cache</button>
			</form>
			<p class="description">Removes every gateway transient and serve-stale backup, and flushes the object cache when one is active.</p>

			<h2>Usage Stats</h2>
			<p>Hits today: <strong><?php echo esc_html( number_format_i18n( $hits_today ) ); ?></strong> / <?php echo esc_html( number_format_i18n( DTS_WS_API_DAILY_LIMIT ) ); ?></p>
			<p>Circuit breaker: <strong><?php echo dts_ws_breaker_active() ? 'ACTIVE' : 'inactive'; ?></strong></p>
			<pre style="background:#f0f0f1;padding:10px;max-height:300px;overflow:auto;"><?php
				echo esc_html( print_r( get_option( 'dts_ws_hits_log', array() ), true ) );
			?></pre>
		</div>
		<?php
	}
}

if ( ! function_exists( 'dts_ws_clear_cache' ) ) {
	/** Purge gateway transients, serve-stale backups and the object cache. */
	function dts_ws_clear_cache() {
		global $wpdb;

		// Hosts without a persistent object cache keep transients in the options table.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_dts\_ws\_%' OR option_name LIKE '\_transient\_timeout\_dts\_ws\_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dts\_ws\_%\_backup'" );

		if ( wp_using_ext_object_cache() ) {
			wp_cache_flush();
		}

		delete_transient( 'dts_ws_quota_exceeded' );
	}
}

if ( ! function_exists( 'dts_ws_diagnostics' ) ) {
	/** Environment report used by the tools screen. */
	function dts_ws_diagnostics() {
		$dbg = array(
			'php_version'      => PHP_VERSION,
			'env_file'         => get_template_directory() . '/.env',
			'env_file_exists'  => file_exists( get_template_directory() . '/.env' ),
			'key_defined'      => '' !== dts_get_api_key(),
			'object_cache'     => wp_using_ext_object_cache() ? 'external' : 'database',
			'circuit_breaker'  => dts_ws_breaker_active() ? 'ACTIVE' : 'inactive',
			'hits_today'       => dts_ws_usage_today(),
			'daily_limit'      => DTS_WS_API_DAILY_LIMIT,
			'brand_map_size'   => count( dts_get_brand_map() ),
		);

		// Probe 1: connectivity without a key. Any HTTP code proves the network path.
		$probe = wp_remote_get( DTS_WS_API_BASE . 'regions/', array( 'timeout' => 15 ) );

		$dbg['probe_no_key'] = is_wp_error( $probe )
			? array(
				'result'  => 'WP_ERROR',
				'message' => $probe->get_error_message(),
			)
			: array( 'result' => 'HTTP ' . wp_remote_retrieve_response_code( $probe ) );

		// Probe 2: authenticated call bypassing every cache — the real verdict.
		if ( '' !== dts_get_api_key() ) {
			$keyed_url = DTS_WS_API_BASE . 'makes/?ordering=slug&region=medm&nocache=' . time() . '&user_key=' . rawurlencode( dts_get_api_key() );
			$keyed     = wp_remote_get( $keyed_url, array( 'timeout' => 15 ) );

			if ( is_wp_error( $keyed ) ) {
				$dbg['probe_with_key'] = array(
					'result'  => 'WP_ERROR',
					'message' => $keyed->get_error_message(),
				);
			} else {
				$kcode = (int) wp_remote_retrieve_response_code( $keyed );
				$kbody = wp_remote_retrieve_body( $keyed );
				$kjson = json_decode( $kbody, true );

				if ( 200 === $kcode ) {
					$verdict = 'OK — key and server both work. Remaining issues are cache or nonce related.';
				} elseif ( 403 === $kcode || 429 === $kcode ) {
					$verdict = 'QUOTA/BLOCKED — the vendor rejects a keyed call from this server. Check the dashboard at developer.wheel-size.com.';
				} else {
					$verdict = 'Unexpected status — inspect the body preview.';
				}

				$dbg['probe_with_key'] = array(
					'verdict'      => $verdict,
					'status'       => 'HTTP ' . $kcode,
					'body_preview' => substr( (string) $kbody, 0, 200 ),
					'brand_count'  => isset( $kjson['data'] ) && is_array( $kjson['data'] ) ? count( $kjson['data'] ) : 0,
				);
			}

			$dbg['gateway_debug'] = dts_ws_api_get( 'models/', array( 'make' => 'audi' ), DAY_IN_SECONDS, true );
		} else {
			$dbg['probe_with_key'] = 'skipped — key undefined';
			$dbg['gateway_debug']  = 'skipped — key undefined';
		}

		return $dbg;
	}
}
