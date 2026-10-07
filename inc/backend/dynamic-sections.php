<?php
/**
 * Dynamic sections: administrator-authored content blocks attached to a URL.
 *
 * This module owns the storage contract and the admin endpoints. Rendering is
 * deliberately absent: a section is a typed record, and the store persists it,
 * sanitises it on the way in, and hands the type registry plus a section's data
 * to whatever is responsible for displaying it.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_get_dynamic_section_types' ) ) {
	/**
	 * Section types an administrator may insert, with their editor labels.
	 *
	 * @return array<string,string> Type key => label.
	 */
	function dts_get_dynamic_section_types() {
		return apply_filters(
			'dts_dynamic_section_types',
			array(
				'faq'        => __( 'FAQ Section', 'custom-dubai-tyre-shop' ),
				'image-text' => __( 'Image + Text Section', 'custom-dubai-tyre-shop' ),
				'cards'      => __( '3 Cards Section', 'custom-dubai-tyre-shop' ),
				'paragraph'  => __( 'Paragraph Section', 'custom-dubai-tyre-shop' ),
			)
		);
	}
}

if ( ! function_exists( 'dts_get_dynamic_section_defaults' ) ) {
	/**
	 * The data a new section of a given type starts with.
	 *
	 * The editor is handed this instead of pre-rendered markup so the form and
	 * the saved record always start from the same shape.
	 *
	 * @param string $type Section type.
	 * @return array Empty array for an unknown type.
	 */
	function dts_get_dynamic_section_defaults( $type ) {
		$defaults = array(
			'faq'        => array(
				'title'       => 'Frequently Asked Questions',
				'title_color' => '',
				'heading'     => 'h2',
				'align'       => 'center',
				'items'       => array(
					array(
						'question'  => 'What services do you offer?',
						'q_heading' => 'span',
						'answer'    => 'We offer a wide range of tyre and automotive services including fitting, balancing, and inspection.',
					),
				),
			),
			'image-text' => array(
				'title'       => 'Our Services',
				'title_color' => '',
				'heading'     => 'h2',
				'align'       => 'left',
				'image_url'   => '',
				'text'        => 'We provide high-quality tyre solutions. Our team of experts ensures you get the best products at competitive prices with professional fitting and after-sales support.',
				'button_text' => 'Learn More',
				'button_url'  => '#',
			),
			'cards'      => array(
				'title'       => 'Why Choose Us',
				'title_color' => '',
				'heading'     => 'h2',
				'align'       => 'center',
				'cards'       => array(
					array(
						'icon'  => 'quality',
						'title' => 'Quality Products',
						'text'  => 'Premium tyres from leading global brands.',
					),
					array(
						'icon'  => 'delivery',
						'title' => 'Fast Delivery',
						'text'  => 'Free doorstep delivery.',
					),
					array(
						'icon'  => 'support',
						'title' => 'Expert Support',
						'text'  => 'Professional fitting and after-sales service.',
					),
				),
			),
			'paragraph'  => array(
				'title'       => 'About Us',
				'title_color' => '',
				'heading'     => 'h2',
				'align'       => 'center',
				'text'        => 'We are a trusted tyre and automotive service provider, committed to delivering quality products and exceptional customer service.',
			),
		);

		return isset( $defaults[ $type ] ) ? $defaults[ $type ] : array();
	}
}

if ( ! function_exists( 'dts_normalize_dynamic_section_path' ) ) {
	/**
	 * Canonical key a section is stored under.
	 *
	 * Sections are keyed by path so that a page and its paginated variants do
	 * not each need their own set, which means the trailing slash has to be
	 * consistent.
	 *
	 * @param string $path Requested path, or empty for the current request.
	 * @return string
	 */
	function dts_normalize_dynamic_section_path( $path = '' ) {
		if ( '' === $path ) {
			$path = isset( $_SERVER['REQUEST_URI'] )
				? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH )
				: '';
		}

		$path = '/' . trim( (string) $path, '/' );

		return '/' === $path ? '/' : trailingslashit( $path );
	}
}

if ( ! function_exists( 'dts_get_dynamic_sections' ) ) {
	/**
	 * Every section stored for a path, in display order.
	 *
	 * @param string $path Path, or empty for the current request.
	 * @return array
	 */
	function dts_get_dynamic_sections( $path = '' ) {
		$all = get_option( 'dts_dynamic_sections', array() );

		if ( ! is_array( $all ) ) {
			return array();
		}

		$path = dts_normalize_dynamic_section_path( $path );

		return isset( $all[ $path ] ) && is_array( $all[ $path ] ) ? $all[ $path ] : array();
	}
}

if ( ! function_exists( 'dts_get_dynamic_section_paths' ) ) {
	/**
	 * Every path that has sections, with its section count.
	 *
	 * @return array<string,int>
	 */
	function dts_get_dynamic_section_paths() {
		$all = get_option( 'dts_dynamic_sections', array() );

		if ( ! is_array( $all ) ) {
			return array();
		}

		$paths = array();

		foreach ( $all as $path => $sections ) {
			if ( is_array( $sections ) && ! empty( $sections ) ) {
				$paths[ (string) $path ] = count( $sections );
			}
		}

		ksort( $paths );

		return $paths;
	}
}

if ( ! function_exists( 'dts_sanitize_dynamic_section_data' ) ) {
	/**
	 * Sanitise a section record for storage.
	 *
	 * Every field is narrowed to the set of values the type defines, so a stored
	 * section can never carry a heading level or alignment the editor does not
	 * offer, and rich text is limited to what a post may contain.
	 *
	 * @param string $type Section type.
	 * @param array  $data Submitted data.
	 * @return array|null Null when the type is unknown.
	 */
	function dts_sanitize_dynamic_section_data( $type, $data ) {
		$types = dts_get_dynamic_section_types();

		if ( ! isset( $types[ $type ] ) || ! is_array( $data ) ) {
			return null;
		}

		$headings = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
		$aligns   = array( 'left', 'center', 'right' );

		$sanitized = array(
			'title'       => sanitize_text_field( isset( $data['title'] ) ? $data['title'] : '' ),
			'title_color' => sanitize_hex_color( isset( $data['title_color'] ) ? $data['title_color'] : '' ),
			'heading'     => in_array( isset( $data['heading'] ) ? $data['heading'] : '', $headings, true ) ? $data['heading'] : 'h2',
			'align'       => in_array( isset( $data['align'] ) ? $data['align'] : '', $aligns, true ) ? $data['align'] : 'center',
		);

		switch ( $type ) {
			case 'faq':
				$sanitized['items'] = array();

				if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
					foreach ( $data['items'] as $item ) {
						if ( ! is_array( $item ) ) {
							continue;
						}

						$q_headings = array_merge( array( 'span' ), $headings );

						$sanitized['items'][] = array(
							'question'  => sanitize_text_field( isset( $item['question'] ) ? $item['question'] : '' ),
							'q_heading' => in_array( isset( $item['q_heading'] ) ? $item['q_heading'] : '', $q_headings, true ) ? $item['q_heading'] : 'span',
							'answer'    => wp_kses_post( isset( $item['answer'] ) ? $item['answer'] : '' ),
						);
					}
				}
				break;

			case 'image-text':
				$sanitized['image_url']   = esc_url_raw( isset( $data['image_url'] ) ? $data['image_url'] : '' );
				$sanitized['text']        = wp_kses_post( isset( $data['text'] ) ? $data['text'] : '' );
				$sanitized['button_text'] = sanitize_text_field( isset( $data['button_text'] ) ? $data['button_text'] : '' );
				$sanitized['button_url']  = esc_url_raw( isset( $data['button_url'] ) ? $data['button_url'] : '#' );
				break;

			case 'cards':
				$sanitized['cards'] = array();

				if ( isset( $data['cards'] ) && is_array( $data['cards'] ) ) {
					foreach ( $data['cards'] as $card ) {
						if ( ! is_array( $card ) ) {
							continue;
						}

						$sanitized['cards'][] = array(
							'icon'  => sanitize_text_field( isset( $card['icon'] ) ? $card['icon'] : 'info' ),
							'title' => sanitize_text_field( isset( $card['title'] ) ? $card['title'] : '' ),
							'text'  => sanitize_text_field( isset( $card['text'] ) ? $card['text'] : '' ),
						);
					}
				}
				break;

			case 'paragraph':
				// A paragraph section may drop its heading entirely; every other
				// type always has one. The text is stored as submitted, including
				// when it is empty, so a field can be cleared.
				$sanitized['heading'] = ( 'none' === ( isset( $data['heading'] ) ? $data['heading'] : '' ) )
					? 'none'
					: $sanitized['heading'];
				$sanitized['text']    = wp_kses_post( isset( $data['text'] ) ? $data['text'] : '' );
				break;
		}

		return $sanitized;
	}
}

if ( ! function_exists( 'dts_get_all_dynamic_sections' ) ) {
	/** The raw storage map, normalised to an array. */
	function dts_get_all_dynamic_sections() {
		$all = get_option( 'dts_dynamic_sections', array() );

		return is_array( $all ) ? $all : array();
	}
}

if ( ! function_exists( 'dts_dynamic_sections_persisted' ) ) {
	/**
	 * Whether a section really landed in the database.
	 *
	 * update_option() returns false both when the write failed and when the
	 * value was already identical, so the only reliable check is to read the
	 * option back.
	 *
	 * @param string $path       Storage path.
	 * @param string $section_id Section ID.
	 * @return bool
	 */
	function dts_dynamic_sections_persisted( $path, $section_id ) {
		foreach ( dts_get_dynamic_sections( $path ) as $section ) {
			if ( isset( $section['id'] ) && $section['id'] === $section_id ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'dts_save_dynamic_section' ) ) {
	/**
	 * Insert or update one section on a path.
	 *
	 * @param string $path       Target path, or empty for the current request.
	 * @param string $section_id Section ID. An unknown ID inserts a new section.
	 * @param string $type       Section type.
	 * @param array  $data       Section data, sanitised before storage.
	 * @return true|WP_Error
	 */
	function dts_save_dynamic_section( $path, $section_id, $type, $data ) {
		$section_id = sanitize_text_field( $section_id );
		$type       = sanitize_text_field( $type );
		$path       = dts_normalize_dynamic_section_path( $path );

		if ( '' === $section_id || '' === $type ) {
			return new WP_Error( 'dts_missing_parameters', __( 'A path, section ID and type are required.', 'custom-dubai-tyre-shop' ) );
		}

		$sanitized = dts_sanitize_dynamic_section_data( $type, $data );

		if ( null === $sanitized ) {
			return new WP_Error( 'dts_invalid_type', __( 'Unknown section type.', 'custom-dubai-tyre-shop' ) );
		}

		$all   = dts_get_all_dynamic_sections();

		if ( ! isset( $all[ $path ] ) || ! is_array( $all[ $path ] ) ) {
			$all[ $path ] = array();
		}

		$record = array(
			'id'   => $section_id,
			'type' => $type,
			'data' => $sanitized,
		);

		$found = false;

		foreach ( $all[ $path ] as $index => $section ) {
			if ( isset( $section['id'] ) && $section['id'] === $section_id ) {
				$all[ $path ][ $index ] = $record;
				$found                  = true;
				break;
			}
		}

		if ( ! $found ) {
			$all[ $path ][] = $record;
		}

		update_option( 'dts_dynamic_sections', $all );

		if ( ! dts_dynamic_sections_persisted( $path, $section_id ) ) {
			return new WP_Error( 'dts_write_failed', __( 'The section could not be saved. Please try again.', 'custom-dubai-tyre-shop' ) );
		}

		return true;
	}
}

if ( ! function_exists( 'dts_delete_dynamic_section' ) ) {
	/**
	 * Remove a section, dropping the path once its last section is gone.
	 *
	 * @param string $path       Target path, or empty for the current request.
	 * @param string $section_id Section ID.
	 * @return true|WP_Error
	 */
	function dts_delete_dynamic_section( $path, $section_id ) {
		$section_id = sanitize_text_field( $section_id );
		$path       = dts_normalize_dynamic_section_path( $path );

		if ( '' === $section_id ) {
			return new WP_Error( 'dts_missing_parameters', __( 'A path and section ID are required.', 'custom-dubai-tyre-shop' ) );
		}

		$all     = dts_get_all_dynamic_sections();
		$deleted = false;

		if ( isset( $all[ $path ] ) && is_array( $all[ $path ] ) ) {
			foreach ( $all[ $path ] as $index => $section ) {
				if ( isset( $section['id'] ) && $section['id'] === $section_id ) {
					unset( $all[ $path ][ $index ] );
					$deleted = true;
					break;
				}
			}

			if ( $deleted ) {
				// Re-index so the stored list stays a JSON array.
				$all[ $path ] = array_values( $all[ $path ] );

				if ( empty( $all[ $path ] ) ) {
					unset( $all[ $path ] );
				}

				update_option( 'dts_dynamic_sections', $all );
			}
		}

		if ( ! $deleted ) {
			return new WP_Error( 'dts_not_found', __( 'Section not found.', 'custom-dubai-tyre-shop' ) );
		}

		return true;
	}
}

if ( ! function_exists( 'dts_get_dynamic_sections_nonce' ) ) {
	/** Nonce action shared by the dynamic-section admin endpoints. */
	function dts_get_dynamic_sections_nonce() {
		return wp_create_nonce( 'dts_dynamic_section' );
	}
}

if ( ! function_exists( 'dts_verify_dynamic_sections_request' ) ) {
	/**
	 * Guard a dynamic-section admin endpoint.
	 *
	 * The capability is checked first so a logged-out request never reaches the
	 * nonce layer.
	 */
	function dts_verify_dynamic_sections_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'custom-dubai-tyre-shop' ) ), 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'dts_dynamic_section' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'custom-dubai-tyre-shop' ) ), 403 );
		}
	}
}

if ( ! function_exists( 'dts_ajax_add_dynamic_section' ) ) {
	/**
	 * Return the starter data for a new section of the requested type.
	 *
	 * @return void
	 */
	function dts_ajax_add_dynamic_section() {
		dts_verify_dynamic_sections_request();

		$type = isset( $_POST['section_type'] ) ? sanitize_text_field( wp_unslash( $_POST['section_type'] ) ) : '';

		if ( ! array_key_exists( $type, dts_get_dynamic_section_types() ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid section type.', 'custom-dubai-tyre-shop' ) ), 400 );
		}

		wp_send_json_success(
			array(
				'section_id' => 'dts_sec_new_' . uniqid(),
				'type'       => $type,
				'data'       => dts_get_dynamic_section_defaults( $type ),
			)
		);
	}
}
add_action( 'wp_ajax_dts_add_dynamic_section', 'dts_ajax_add_dynamic_section' );

if ( ! function_exists( 'dts_ajax_save_dynamic_section' ) ) {
	/**
	 * Persist a section submitted by the editor.
	 *
	 * @return void
	 */
	function dts_ajax_save_dynamic_section() {
		dts_verify_dynamic_sections_request();

		$page_url   = isset( $_POST['page_url'] ) ? sanitize_text_field( wp_unslash( $_POST['page_url'] ) ) : '';
		$section_id = isset( $_POST['section_id'] ) ? sanitize_text_field( wp_unslash( $_POST['section_id'] ) ) : '';
		$type       = isset( $_POST['section_type'] ) ? sanitize_text_field( wp_unslash( $_POST['section_type'] ) ) : '';
		$raw_data   = isset( $_POST['section_data'] ) ? wp_unslash( $_POST['section_data'] ) : '';

		$data = is_string( $raw_data ) ? json_decode( $raw_data, true ) : $raw_data;

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid section data.', 'custom-dubai-tyre-shop' ) ), 400 );
		}

		$result = dts_save_dynamic_section( $page_url, $section_id, $type, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'section_id' => $section_id,
				'path'       => dts_normalize_dynamic_section_path( $page_url ),
			)
		);
	}
}
add_action( 'wp_ajax_dts_save_dynamic_section', 'dts_ajax_save_dynamic_section' );

if ( ! function_exists( 'dts_ajax_delete_dynamic_section' ) ) {
	/**
	 * Remove a section submitted by the editor.
	 *
	 * @return void
	 */
	function dts_ajax_delete_dynamic_section() {
		dts_verify_dynamic_sections_request();

		$page_url   = isset( $_POST['page_url'] ) ? sanitize_text_field( wp_unslash( $_POST['page_url'] ) ) : '';
		$section_id = isset( $_POST['section_id'] ) ? sanitize_text_field( wp_unslash( $_POST['section_id'] ) ) : '';

		$result = dts_delete_dynamic_section( $page_url, $section_id );

		if ( is_wp_error( $result ) ) {
			$status = ( 'dts_not_found' === $result->get_error_code() ) ? 404 : 400;

			wp_send_json_error( array( 'message' => $result->get_error_message() ), $status );
		}

		wp_send_json_success();
	}
}
add_action( 'wp_ajax_dts_delete_dynamic_section', 'dts_ajax_delete_dynamic_section' );

if ( ! function_exists( 'dts_admin_bar_add_section' ) ) {
	/**
	 * Offer the insertable section types in the admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	function dts_admin_bar_add_section( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'dts-add-section',
				'title' => __( 'Add Section', 'custom-dubai-tyre-shop' ),
				'href'  => '#',
			)
		);

		foreach ( dts_get_dynamic_section_types() as $type => $label ) {
			$wp_admin_bar->add_node(
				array(
					'parent' => 'dts-add-section',
					'id'     => 'dts-add-section-' . $type,
					'title'  => $label,
					'href'   => '#',
					'meta'   => array( 'class' => 'dts-add-section-' . $type ),
				)
			);
		}
	}
}
add_action( 'admin_bar_menu', 'dts_admin_bar_add_section', 100 );
