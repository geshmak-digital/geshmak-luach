<?php
/** בס״ד
 * LOCATION LOOKUP
 *
 * Admin-only AJAX proxy to Hebcal's city search, powering the location picker on
 * the Luach settings page. The browser talks to this site; this site talks to
 * Hebcal (cached, capability- and nonce-checked), so there are no cross-origin
 * problems and Hebcal sees one polite caller.
 *
 *   search  -> https://www.hebcal.com/complete.php?q=...   (id = GeoNames ID)
 *   details -> https://www.hebcal.com/shabbat?cfg=json&geonameid=...  (tzid, elevation)
 *
 * The manual GeoNames ID field always remains, as a fallback if Hebcal changes
 * or removes the (undocumented) search endpoint.
 *
 * DEVELOPED BY TOM GOLDSTEIN > GESHMAK! > https://geshmak.com.au/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GESHMAK_LUACH_LOOKUP_NONCE', 'geshmak_luach_location_lookup' );

// ---------------------------------------------------------------------------
// HTTP
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_lookup_get' ) ) {
	/**
	 * Cached GET against a Hebcal lookup URL.
	 *
	 * @param string $url Full URL.
	 * @param int    $ttl Cache lifetime in seconds.
	 * @return array|null Decoded JSON, or null on failure.
	 */
	function geshmak_luach_lookup_get( $url, $ttl ) {
		$key    = 'geshmak_luach_lk_' . md5( $url );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'user-agent' => sprintf( 'Geshmak-Luach/%s (+https://geshmak.com.au; %s)', defined( 'GESHMAK_LUACH_VERSION' ) ? GESHMAK_LUACH_VERSION : '1.0', home_url( '/' ) ),
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		set_transient( $key, $data, $ttl );
		return $data;
	}
}

// ---------------------------------------------------------------------------
// AJAX: SEARCH
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_geshmak_luach_location_search', 'geshmak_luach_ajax_location_search' );

if ( ! function_exists( 'geshmak_luach_ajax_location_search' ) ) {
	/**
	 * Return matching places for a typed query.
	 *
	 * @return void
	 */
	function geshmak_luach_ajax_location_search() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'geshmak-luach' ) ), 403 );
		}
		check_ajax_referer( GESHMAK_LUACH_LOOKUP_NONCE, 'nonce' );

		$q = isset( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['q'] ) ) ) : '';
		if ( strlen( $q ) < 2 ) {
			wp_send_json_success( array() );
		}

		$data = geshmak_luach_lookup_get(
			add_query_arg( 'q', rawurlencode( $q ), 'https://www.hebcal.com/complete.php' ),
			DAY_IN_SECONDS
		);
		if ( null === $data ) {
			wp_send_json_error( array( 'message' => __( 'Hebcal city search is unavailable. Enter the GeoNames ID manually.', 'geshmak-luach' ) ) );
		}

		$out = array();
		foreach ( array_slice( $data, 0, 12 ) as $row ) {
			if ( empty( $row['id'] ) || empty( $row['value'] ) ) {
				continue;
			}
			$out[] = array(
				'id'    => (string) absint( $row['id'] ),
				'label' => wp_strip_all_tags( $row['value'] ),
				'flag'  => isset( $row['flag'] ) ? wp_strip_all_tags( $row['flag'] ) : '',
			);
		}
		wp_send_json_success( $out );
	}
}

// ---------------------------------------------------------------------------
// AJAX: DETAILS (timezone, elevation, coordinates for a chosen place)
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_geshmak_luach_location_details', 'geshmak_luach_ajax_location_details' );

if ( ! function_exists( 'geshmak_luach_ajax_location_details' ) ) {
	/**
	 * Return timezone / elevation for a GeoNames ID.
	 *
	 * @return void
	 */
	function geshmak_luach_ajax_location_details() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'geshmak-luach' ) ), 403 );
		}
		check_ajax_referer( GESHMAK_LUACH_LOOKUP_NONCE, 'nonce' );

		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Missing ID.', 'geshmak-luach' ) ) );
		}

		$data = geshmak_luach_lookup_get(
			add_query_arg(
				array(
					'cfg'       => 'json',
					'geonameid' => $id,
					'M'         => 'on',
				),
				'https://www.hebcal.com/shabbat'
			),
			WEEK_IN_SECONDS
		);
		if ( null === $data || empty( $data['location'] ) || ! is_array( $data['location'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not read that place from Hebcal.', 'geshmak-luach' ) ) );
		}

		$loc = $data['location'];
		wp_send_json_success(
			array(
				'tzid'      => isset( $loc['tzid'] ) ? sanitize_text_field( $loc['tzid'] ) : '',
				'elevation' => isset( $loc['elevation'] ) ? (string) absint( $loc['elevation'] ) : '',
				'title'     => isset( $loc['title'] ) ? sanitize_text_field( $loc['title'] ) : '',
			)
		);
	}
}

// ---------------------------------------------------------------------------
// ASSETS (settings page only)
// ---------------------------------------------------------------------------

add_action( 'admin_enqueue_scripts', 'geshmak_luach_enqueue_location_lookup' );

if ( ! function_exists( 'geshmak_luach_enqueue_location_lookup' ) ) {
	/**
	 * Load the picker script on the Luach settings screen only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	function geshmak_luach_enqueue_location_lookup( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( GESHMAK_LUACH_SETTINGS_SLUG !== $page ) {
			return;
		}

		wp_enqueue_script(
			'geshmak-luach-location',
			GESHMAK_LUACH_PLUGIN_URL . 'assets/geshmak-luach-location.js',
			array(),
			GESHMAK_LUACH_VERSION,
			true
		);
		wp_localize_script(
			'geshmak-luach-location',
			'geshmakLuachLocation',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( GESHMAK_LUACH_LOOKUP_NONCE ),
				'i18n'    => array(
					'noResults' => __( 'No matching places.', 'geshmak-luach' ),
					'failed'    => __( 'Search failed.', 'geshmak-luach' ),
					'filled'    => __( 'Timezone and elevation filled in from Hebcal.', 'geshmak-luach' ),
				),
			)
		);
	}
}
