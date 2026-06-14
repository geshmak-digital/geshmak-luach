<?php
/** בס״ד
 * SETTINGS PAGE
 *
 * Top-level "Luach" admin menu built on the WordPress Settings API. Everything is
 * stored as a single sanitised option array (GESHMAK_LUACH_OPTION). Includes a
 * nonce-protected "Clear Luach cache" action.
 *
 * Per-instance overrides on shortcodes / Elementor surfaces take precedence over
 * these site-wide globals.
 *
 * DEVELOPED BY TOM GOLDSTEIN > GESHMAK! > https://geshmak.com.au/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GESHMAK_LUACH_SETTINGS_GROUP', 'geshmak_luach_settings_group' );
define( 'GESHMAK_LUACH_SETTINGS_SLUG', 'geshmak-luach' );

// ---------------------------------------------------------------------------
// MENU
// ---------------------------------------------------------------------------

add_action( 'admin_menu', 'geshmak_luach_register_menu' );

if ( ! function_exists( 'geshmak_luach_register_menu' ) ) {
	/**
	 * Register the top-level Luach menu.
	 *
	 * @return void
	 */
	function geshmak_luach_register_menu() {
		add_menu_page(
			__( 'Luach Settings', 'geshmak-luach' ),
			__( 'Luach', 'geshmak-luach' ),
			'manage_options',
			GESHMAK_LUACH_SETTINGS_SLUG,
			'geshmak_luach_render_settings_page',
			'dashicons-calendar-alt',
			81
		);
	}
}

// ---------------------------------------------------------------------------
// SETTINGS REGISTRATION
// ---------------------------------------------------------------------------

add_action( 'admin_init', 'geshmak_luach_register_settings' );

if ( ! function_exists( 'geshmak_luach_register_settings' ) ) {
	/**
	 * Register the option, sections and fields.
	 *
	 * @return void
	 */
	function geshmak_luach_register_settings() {

		register_setting(
			GESHMAK_LUACH_SETTINGS_GROUP,
			GESHMAK_LUACH_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'geshmak_luach_sanitize_settings',
				'default'           => Geshmak_Luach_Hebcal_Service::default_settings(),
			)
		);

		// --- Location ----------------------------------------------------------
		add_settings_section( 'geshmak_luach_location', __( 'Default Location', 'geshmak-luach' ), 'geshmak_luach_section_location', GESHMAK_LUACH_SETTINGS_SLUG );
		add_settings_field( 'geonameid', __( 'GeoNames ID', 'geshmak-luach' ), 'geshmak_luach_field_geonameid', GESHMAK_LUACH_SETTINGS_SLUG, 'geshmak_luach_location' );
		add_settings_field( 'latlng', __( 'Latitude / Longitude', 'geshmak-luach' ), 'geshmak_luach_field_latlng', GESHMAK_LUACH_SETTINGS_SLUG, 'geshmak_luach_location' );
		add_settings_field( 'tzid', __( 'Timezone', 'geshmak-luach' ), 'geshmak_luach_field_tzid', GESHMAK_LUACH_SETTINGS_SLUG, 'geshmak_luach_location' );
		add_settings_field( 'elevation', __( 'Elevation (m)', 'geshmak-luach' ), 'geshmak_luach_field_elevation', GESHMAK_LUACH_SETTINGS_SLUG, 'geshmak_luach_location' );

		// --- Calendar ----------------------------------------------------------
		add_settings_section( 'geshmak_luach_calendar', __( 'Calendar & Times', 'geshmak-luach' ), '__return_false', GESHMAK_LUACH_SETTINGS_SLUG );
		add_settings_field( 'israel', __( 'Schedule', 'geshmak-luach' ), 'geshmak_luach_field_israel', GESHMAK_LUACH_SETTINGS_SLUG, 'geshmak_luach_calendar' );
		add_settings_field( 'candle_mins', __( 'Candle-lighting offset', 'geshmak-luach' ), 'geshmak_luach_field_candle_mins', GESHMAK_LUACH_SETTINGS_SLUG, 'geshmak_luach_calendar' );
		add_settings_field( 'havdalah', __( 'Havdalah', 'geshmak-luach' ), 'geshmak_luach_field_havdalah', GESHMAK_LUACH_SETTINGS_SLUG, 'geshmak_luach_calendar' );

		// --- Display -----------------------------------------------------------
		add_settings_section( 'geshmak_luach_display', __( 'Display', 'geshmak-luach' ), '__return_false', GESHMAK_LUACH_SETTINGS_SLUG );
		add_settings_field( 'translit', __( 'Transliteration', 'geshmak-luach' ), 'geshmak_luach_field_translit', GESHMAK_LUACH_SETTINGS_SLUG, 'geshmak_luach_display' );
		add_settings_field( 'zmanim_keys', __( 'Default zmanim shown', 'geshmak-luach' ), 'geshmak_luach_field_zmanim_keys', GESHMAK_LUACH_SETTINGS_SLUG, 'geshmak_luach_display' );
		add_settings_field( 'date_format', __( 'Date format', 'geshmak-luach' ), 'geshmak_luach_field_date_format', GESHMAK_LUACH_SETTINGS_SLUG, 'geshmak_luach_display' );

		// --- Cache -------------------------------------------------------------
		add_settings_section( 'geshmak_luach_cache', __( 'Cache', 'geshmak-luach' ), '__return_false', GESHMAK_LUACH_SETTINGS_SLUG );
		add_settings_field( 'cache_ttl', __( 'Cache lifetime (seconds)', 'geshmak-luach' ), 'geshmak_luach_field_cache_ttl', GESHMAK_LUACH_SETTINGS_SLUG, 'geshmak_luach_cache' );
	}
}

// ---------------------------------------------------------------------------
// SANITISATION
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_sanitize_settings' ) ) {
	/**
	 * Sanitise the whole settings array on save.
	 *
	 * @param array $input
	 * @return array
	 */
	function geshmak_luach_sanitize_settings( $input ) {

		$input    = is_array( $input ) ? $input : array();
		$defaults = Geshmak_Luach_Hebcal_Service::default_settings();
		$out      = array();

		$out['geonameid']    = isset( $input['geonameid'] ) ? preg_replace( '/[^0-9]/', '', $input['geonameid'] ) : '';
		$out['latitude']     = isset( $input['latitude'] ) && '' !== $input['latitude'] ? (string) floatval( $input['latitude'] ) : '';
		$out['longitude']    = isset( $input['longitude'] ) && '' !== $input['longitude'] ? (string) floatval( $input['longitude'] ) : '';
		$out['tzid']         = isset( $input['tzid'] ) ? sanitize_text_field( $input['tzid'] ) : '';
		$out['elevation']    = isset( $input['elevation'] ) && '' !== $input['elevation'] ? (string) absint( $input['elevation'] ) : '';
		$out['inherit_site'] = empty( $input['inherit_site'] ) ? 0 : 1;
		$out['israel']       = empty( $input['israel'] ) ? 0 : 1;
		$out['candle_mins']  = isset( $input['candle_mins'] ) ? absint( $input['candle_mins'] ) : 18;

		$out['havdalah_mode'] = ( isset( $input['havdalah_mode'] ) && 'mins' === $input['havdalah_mode'] ) ? 'mins' : 'tzeit';
		$out['havdalah_mins'] = isset( $input['havdalah_mins'] ) ? absint( $input['havdalah_mins'] ) : 50;

		$schemes          = array_keys( geshmak_luach_translit_schemes() );
		$out['translit']  = ( isset( $input['translit'] ) && in_array( $input['translit'], $schemes, true ) ) ? $input['translit'] : 'ashkenaz';

		$valid_zmanim       = array_keys( geshmak_luach_zman_labels() );
		$out['zmanim_keys'] = array();
		if ( ! empty( $input['zmanim_keys'] ) && is_array( $input['zmanim_keys'] ) ) {
			foreach ( $input['zmanim_keys'] as $key ) {
				if ( in_array( $key, $valid_zmanim, true ) ) {
					$out['zmanim_keys'][] = $key;
				}
			}
		}
		if ( empty( $out['zmanim_keys'] ) ) {
			$out['zmanim_keys'] = $defaults['zmanim_keys'];
		}

		$out['date_format'] = isset( $input['date_format'] ) ? sanitize_text_field( $input['date_format'] ) : '';
		$out['cache_ttl']   = isset( $input['cache_ttl'] ) ? max( MINUTE_IN_SECONDS, absint( $input['cache_ttl'] ) ) : $defaults['cache_ttl'];

		// Drop the in-memory settings cache so the next request reads fresh values.
		if ( function_exists( 'geshmak_luach_service' ) ) {
			geshmak_luach_service()->reset_settings_cache();
		}

		return $out;
	}
}

// ---------------------------------------------------------------------------
// FIELD RENDERERS
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_get_opt' ) ) {
	/**
	 * Read a single saved setting (merged over defaults) for the admin form.
	 *
	 * @param string $key
	 * @return mixed
	 */
	function geshmak_luach_get_opt( $key ) {
		$saved    = get_option( GESHMAK_LUACH_OPTION, array() );
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), Geshmak_Luach_Hebcal_Service::default_settings() );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
	}
}

if ( ! function_exists( 'geshmak_luach_section_location' ) ) {
	function geshmak_luach_section_location() {
		echo '<p>' . esc_html__( 'Set the site-wide default location. Every shortcode and Elementor widget can override this per instance.', 'geshmak-luach' ) . '</p>';
	}
}

if ( ! function_exists( 'geshmak_luach_field_geonameid' ) ) {
	function geshmak_luach_field_geonameid() {
		$val = geshmak_luach_get_opt( 'geonameid' );
		printf(
			'<input type="text" name="%1$s[geonameid]" value="%2$s" class="regular-text" inputmode="numeric" /> ',
			esc_attr( GESHMAK_LUACH_OPTION ),
			esc_attr( $val )
		);
		printf(
			'<p class="description">%s <a href="%s" target="_blank" rel="noopener">%s</a>.</p>',
			esc_html__( 'Primary location. Look up your city ID at', 'geshmak-luach' ),
			esc_url( 'https://www.geonames.org/' ),
			esc_html__( 'GeoNames', 'geshmak-luach' )
		);
	}
}

if ( ! function_exists( 'geshmak_luach_field_latlng' ) ) {
	function geshmak_luach_field_latlng() {
		$lat = geshmak_luach_get_opt( 'latitude' );
		$lng = geshmak_luach_get_opt( 'longitude' );
		printf(
			'<input type="text" name="%1$s[latitude]" value="%2$s" placeholder="%3$s" class="small-text" /> ',
			esc_attr( GESHMAK_LUACH_OPTION ),
			esc_attr( $lat ),
			esc_attr__( 'Latitude', 'geshmak-luach' )
		);
		printf(
			'<input type="text" name="%1$s[longitude]" value="%2$s" placeholder="%3$s" class="small-text" />',
			esc_attr( GESHMAK_LUACH_OPTION ),
			esc_attr( $lng ),
			esc_attr__( 'Longitude', 'geshmak-luach' )
		);
		echo '<p class="description">' . esc_html__( 'Fallback used only when no GeoNames ID is set.', 'geshmak-luach' ) . '</p>';
	}
}

if ( ! function_exists( 'geshmak_luach_field_tzid' ) ) {
	function geshmak_luach_field_tzid() {
		$tzid    = geshmak_luach_get_opt( 'tzid' );
		$inherit = geshmak_luach_get_opt( 'inherit_site' );
		printf(
			'<input type="text" name="%1$s[tzid]" value="%2$s" placeholder="%3$s" class="regular-text" />',
			esc_attr( GESHMAK_LUACH_OPTION ),
			esc_attr( $tzid ),
			esc_attr( 'Australia/Melbourne' )
		);
		echo '<p><label><input type="checkbox" name="' . esc_attr( GESHMAK_LUACH_OPTION ) . '[inherit_site]" value="1" ' . checked( 1, $inherit, false ) . ' /> ';
		echo esc_html__( 'Inherit the site timezone when none is set above (used with latitude/longitude).', 'geshmak-luach' ) . '</label></p>';
	}
}

if ( ! function_exists( 'geshmak_luach_field_elevation' ) ) {
	function geshmak_luach_field_elevation() {
		$val = geshmak_luach_get_opt( 'elevation' );
		printf(
			'<input type="number" min="0" step="1" name="%1$s[elevation]" value="%2$s" class="small-text" />',
			esc_attr( GESHMAK_LUACH_OPTION ),
			esc_attr( $val )
		);
		echo '<p class="description">' . esc_html__( 'Optional. Used for elevation-aware zmanim.', 'geshmak-luach' ) . '</p>';
	}
}

if ( ! function_exists( 'geshmak_luach_field_israel' ) ) {
	function geshmak_luach_field_israel() {
		$val = geshmak_luach_get_opt( 'israel' );
		echo '<label><input type="checkbox" name="' . esc_attr( GESHMAK_LUACH_OPTION ) . '[israel]" value="1" ' . checked( 1, $val, false ) . ' /> ';
		echo esc_html__( 'Use the Israel schedule (one day of yom tov, Israeli parsha). Leave unchecked for Diaspora.', 'geshmak-luach' ) . '</label>';
	}
}

if ( ! function_exists( 'geshmak_luach_field_candle_mins' ) ) {
	function geshmak_luach_field_candle_mins() {
		$val = geshmak_luach_get_opt( 'candle_mins' );
		printf(
			'<input type="number" min="0" step="1" name="%1$s[candle_mins]" value="%2$s" class="small-text" /> %3$s',
			esc_attr( GESHMAK_LUACH_OPTION ),
			esc_attr( $val ),
			esc_html__( 'minutes before sunset', 'geshmak-luach' )
		);
	}
}

if ( ! function_exists( 'geshmak_luach_field_havdalah' ) ) {
	function geshmak_luach_field_havdalah() {
		$mode = geshmak_luach_get_opt( 'havdalah_mode' );
		$mins = geshmak_luach_get_opt( 'havdalah_mins' );
		echo '<select name="' . esc_attr( GESHMAK_LUACH_OPTION ) . '[havdalah_mode]">';
		echo '<option value="tzeit" ' . selected( 'tzeit', $mode, false ) . '>' . esc_html__( 'Nightfall (3 small stars)', 'geshmak-luach' ) . '</option>';
		echo '<option value="mins" ' . selected( 'mins', $mode, false ) . '>' . esc_html__( 'Fixed minutes after sunset', 'geshmak-luach' ) . '</option>';
		echo '</select> ';
		printf(
			'<input type="number" min="0" step="1" name="%1$s[havdalah_mins]" value="%2$s" class="small-text" /> %3$s',
			esc_attr( GESHMAK_LUACH_OPTION ),
			esc_attr( $mins ),
			esc_html__( 'minutes (when "Fixed minutes" is selected)', 'geshmak-luach' )
		);
	}
}

if ( ! function_exists( 'geshmak_luach_field_translit' ) ) {
	function geshmak_luach_field_translit() {
		$val = geshmak_luach_get_opt( 'translit' );
		echo '<select name="' . esc_attr( GESHMAK_LUACH_OPTION ) . '[translit]">';
		foreach ( geshmak_luach_translit_schemes() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'The original Hebrew is always available alongside the transliteration.', 'geshmak-luach' ) . '</p>';
	}
}

if ( ! function_exists( 'geshmak_luach_field_zmanim_keys' ) ) {
	function geshmak_luach_field_zmanim_keys() {
		$selected = (array) geshmak_luach_get_opt( 'zmanim_keys' );
		echo '<fieldset>';
		foreach ( geshmak_luach_zman_labels() as $key => $label ) {
			echo '<label style="display:inline-block;min-width:280px;margin:2px 0;">';
			echo '<input type="checkbox" name="' . esc_attr( GESHMAK_LUACH_OPTION ) . '[zmanim_keys][]" value="' . esc_attr( $key ) . '" ' . checked( true, in_array( $key, $selected, true ), false ) . ' /> ';
			echo esc_html( $label );
			echo '</label>';
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Times shown by default in zmanim output. Shortcodes/widgets can request all times or a custom subset.', 'geshmak-luach' ) . '</p>';
	}
}

if ( ! function_exists( 'geshmak_luach_field_date_format' ) ) {
	function geshmak_luach_field_date_format() {
		$val = geshmak_luach_get_opt( 'date_format' );
		printf(
			'<input type="text" name="%1$s[date_format]" value="%2$s" placeholder="%3$s" class="regular-text" />',
			esc_attr( GESHMAK_LUACH_OPTION ),
			esc_attr( $val ),
			esc_attr( get_option( 'date_format', 'F j, Y' ) )
		);
		echo '<p class="description">' . esc_html__( 'PHP date() format for Gregorian dates. Leave blank to use the site default.', 'geshmak-luach' ) . '</p>';
	}
}

if ( ! function_exists( 'geshmak_luach_field_cache_ttl' ) ) {
	function geshmak_luach_field_cache_ttl() {
		$val = geshmak_luach_get_opt( 'cache_ttl' );
		printf(
			'<input type="number" min="60" step="60" name="%1$s[cache_ttl]" value="%2$s" class="regular-text" />',
			esc_attr( GESHMAK_LUACH_OPTION ),
			esc_attr( $val )
		);
		echo '<p class="description">' . esc_html__( 'Default 604800 (7 days). Calendar and zmanim data is deterministic, so long lifetimes are safe.', 'geshmak-luach' ) . '</p>';
	}
}

// ---------------------------------------------------------------------------
// PAGE RENDER
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_render_settings_page' ) ) {
	/**
	 * Render the settings page (settings form + clear-cache action).
	 *
	 * @return void
	 */
	function geshmak_luach_render_settings_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['geshmak_luach_cache_cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Luach cache cleared.', 'geshmak-luach' ) . '</p></div>';
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Geshmak! - Luach', 'geshmak-luach' ); ?></h1>

			<form action="options.php" method="post">
				<?php
				settings_fields( GESHMAK_LUACH_SETTINGS_GROUP );
				do_settings_sections( GESHMAK_LUACH_SETTINGS_SLUG );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Cache maintenance', 'geshmak-luach' ); ?></h2>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="geshmak_luach_clear_cache" />
				<?php wp_nonce_field( 'geshmak_luach_clear_cache', 'geshmak_luach_clear_cache_nonce' ); ?>
				<?php submit_button( __( 'Clear Luach cache', 'geshmak-luach' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr />
			<p class="description">
				<?php
				printf(
					/* translators: %s: Hebcal link. */
					esc_html__( 'Calendar, zmanim and leyning data by %s, licensed CC BY 4.0.', 'geshmak-luach' ),
					'<a href="' . esc_url( 'https://www.hebcal.com/' ) . '" target="_blank" rel="noopener">Hebcal.com</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}

// ---------------------------------------------------------------------------
// CLEAR-CACHE HANDLER
// ---------------------------------------------------------------------------

add_action( 'admin_post_geshmak_luach_clear_cache', 'geshmak_luach_handle_clear_cache' );

if ( ! function_exists( 'geshmak_luach_handle_clear_cache' ) ) {
	/**
	 * Handle the "Clear Luach cache" submission.
	 *
	 * @return void
	 */
	function geshmak_luach_handle_clear_cache() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'geshmak-luach' ) );
		}

		check_admin_referer( 'geshmak_luach_clear_cache', 'geshmak_luach_clear_cache_nonce' );

		if ( function_exists( 'geshmak_luach_service' ) ) {
			geshmak_luach_service()->clear_cache();
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                        => GESHMAK_LUACH_SETTINGS_SLUG,
					'geshmak_luach_cache_cleared' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
