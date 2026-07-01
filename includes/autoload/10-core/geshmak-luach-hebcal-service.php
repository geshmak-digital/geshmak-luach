<?php
/** בס״ד
 * HEBCAL SERVICE — THE DATA SPINE
 *
 * Single gateway to the Hebcal REST API. Every front-end surface (shortcodes,
 * Elementor dynamic tags, atomic widgets, theme template tags) calls THIS class.
 * No surface ever calls Hebcal directly.
 *
 * Responsibilities:
 *   - Build normalised requests for every Hebcal REST family.
 *   - Fetch via wp_remote_get with a descriptive User-Agent (Hebcal etiquette).
 *   - Cache aggressively (persistent object cache when available, transients otherwise).
 *   - Serve stale cache on failure (never blank a surface).
 *   - Return clean, structured PHP arrays — not raw JSON.
 *
 * DEVELOPED BY TOM GOLDSTEIN > GESHMAK! > https://geshmak.com.au/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Geshmak_Luach_Hebcal_Service' ) ) {

	class Geshmak_Luach_Hebcal_Service {

		/**
		 * Hebcal REST endpoints.
		 */
		const ENDPOINT_CALENDAR  = 'https://www.hebcal.com/hebcal';
		const ENDPOINT_CONVERTER = 'https://www.hebcal.com/converter';
		const ENDPOINT_ZMANIM    = 'https://www.hebcal.com/zmanim';
		const ENDPOINT_LEYNING   = 'https://www.hebcal.com/leyning';
		const ENDPOINT_YAHRZEIT  = 'https://www.hebcal.com/yahrzeit';

		/**
		 * Hebcal data attribution (CC BY 4.0) — surfaced in output.
		 */
		const ATTRIBUTION_TEXT = 'Calendar data by Hebcal.com (CC BY 4.0)';
		const ATTRIBUTION_URL  = 'https://www.hebcal.com/';

		/**
		 * Singleton instance.
		 *
		 * @var Geshmak_Luach_Hebcal_Service|null
		 */
		protected static $instance = null;

		/**
		 * Resolved settings array (lazy-loaded).
		 *
		 * @var array|null
		 */
		protected $settings = null;

		/**
		 * Accessor.
		 *
		 * @return Geshmak_Luach_Hebcal_Service
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		// -------------------------------------------------------------------
		// SETTINGS
		// -------------------------------------------------------------------

		/**
		 * Default settings — used before the settings page has ever saved.
		 *
		 * @return array
		 */
		public static function default_settings() {
			return array(
				'geonameid'    => '',      // GeoNames numeric ID (primary location).
				'latitude'     => '',      // Fallback latitude.
				'longitude'    => '',      // Fallback longitude.
				'tzid'         => '',      // IANA timezone, e.g. Australia/Melbourne.
				'elevation'    => '',      // Metres, for zmanim.
				'inherit_site' => 1,       // Inherit the site timezone when no tzid set.
				'israel'       => 0,       // 1 = Israel schedule, 0 = Diaspora.
				'candle_mins'  => 18,      // Candle-lighting offset before sunset.
				'havdalah_mode'=> 'tzeit', // 'tzeit' or 'mins'.
				'havdalah_mins'=> 50,      // Fixed havdalah minutes when mode = mins.
				'translit'     => 'ashkenaz', // 'sephardi' | 'ashkenaz' | 'hebrew'.
				'zmanim_keys'  => array(   // Curated default subset (all are available).
					'alotHaShachar', 'misheyakir', 'sunrise', 'sofZmanShma',
					'sofZmanShmaMGA', 'sofZmanTfilla', 'chatzot', 'minchaGedola',
					'minchaKetana', 'plagHaMincha', 'sunset', 'tzeit7083deg',
				),
				'date_format'  => '', // PHP date() format; empty = site default.
				'cache_ttl'    => 7 * DAY_IN_SECONDS,
			);
		}

		/**
		 * Get the merged settings (saved option over defaults).
		 *
		 * @return array
		 */
		public function get_settings() {
			if ( null === $this->settings ) {
				$saved          = get_option( GESHMAK_LUACH_OPTION, array() );
				$this->settings = wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
			}
			return $this->settings;
		}

		/**
		 * Get a single setting.
		 *
		 * @param string $key
		 * @param mixed  $fallback
		 * @return mixed
		 */
		public function get_setting( $key, $fallback = null ) {
			$settings = $this->get_settings();
			return isset( $settings[ $key ] ) ? $settings[ $key ] : $fallback;
		}

		/**
		 * Flush the in-memory settings cache (used after a settings save in admin).
		 *
		 * @return void
		 */
		public function reset_settings_cache() {
			$this->settings = null;
		}

		// -------------------------------------------------------------------
		// LOCATION & SHARED PARAMS
		// -------------------------------------------------------------------

		/**
		 * Resolve the location params for a request, merging per-instance overrides
		 * over the global settings.
		 *
		 * @param array $args Optional overrides: geonameid, latitude, longitude, tzid, elevation, israel.
		 * @return array Hebcal geo params.
		 */
		public function resolve_location( $args = array() ) {
			$s = $this->get_settings();

			$geonameid = isset( $args['geonameid'] ) && '' !== $args['geonameid'] ? $args['geonameid'] : $s['geonameid'];
			$latitude  = isset( $args['latitude'] )  && '' !== $args['latitude']  ? $args['latitude']  : $s['latitude'];
			$longitude = isset( $args['longitude'] ) && '' !== $args['longitude'] ? $args['longitude'] : $s['longitude'];
			$tzid      = isset( $args['tzid'] )      && '' !== $args['tzid']      ? $args['tzid']      : $s['tzid'];
			$elevation = isset( $args['elevation'] ) && '' !== $args['elevation'] ? $args['elevation'] : $s['elevation'];

			// Inherit the site timezone when none configured.
			if ( '' === $tzid && ! empty( $s['inherit_site'] ) ) {
				$site_tz = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : get_option( 'timezone_string' );
				if ( $site_tz && false === strpos( $site_tz, '+' ) && false === strpos( $site_tz, '-' ) ) {
					$tzid = $site_tz;
				}
			}

			$params = array();

			if ( '' !== (string) $geonameid ) {
				$params['geo']       = 'geoname';
				$params['geonameid'] = preg_replace( '/[^0-9]/', '', (string) $geonameid );
			} elseif ( '' !== (string) $latitude && '' !== (string) $longitude ) {
				$params['geo']       = 'pos';
				$params['latitude']  = (float) $latitude;
				$params['longitude'] = (float) $longitude;
				if ( '' !== (string) $tzid ) {
					$params['tzid'] = $tzid;
				}
			}

			if ( '' !== (string) $elevation ) {
				$params['elev'] = (int) $elevation;
				$params['ue']   = 'on'; // Use elevation in zmanim calculations.
			}

			return $params;
		}

		/**
		 * Resolve the Diaspora/Israel flag (override beats global).
		 *
		 * @param array $args
		 * @return bool
		 */
		protected function resolve_israel( $args ) {
			if ( isset( $args['israel'] ) && '' !== $args['israel'] ) {
				return (bool) geshmak_luach_to_bool( $args['israel'] );
			}
			return (bool) $this->get_setting( 'israel' );
		}

		/**
		 * Resolve the transliteration scheme (override beats global).
		 *
		 * @param array $args
		 * @return string
		 */
		public function resolve_translit( $args ) {
			if ( ! empty( $args['translit'] ) ) {
				return sanitize_key( $args['translit'] );
			}
			return $this->get_setting( 'translit', 'ashkenaz' );
		}

		// -------------------------------------------------------------------
		// CACHING (persistent object cache → transients fallback) + STALE-ON-ERROR
		// -------------------------------------------------------------------

		/**
		 * Build a cache key from an endpoint and its params.
		 *
		 * @param string $endpoint
		 * @param array  $params
		 * @return string
		 */
		protected function cache_key( $endpoint, $params ) {
			ksort( $params );
			return 'gl' . $this->cache_version() . '_' . md5( $endpoint . '|' . wp_json_encode( $params ) );
		}

		/**
		 * Current cache version — bumped by "Clear cache" so old entries are abandoned
		 * regardless of the storage backend.
		 *
		 * @return int
		 */
		protected function cache_version() {
			return (int) get_option( 'geshmak_luach_cache_version', 1 );
		}

		/**
		 * Read from cache.
		 *
		 * @param string $key
		 * @return mixed false when missing.
		 */
		protected function cache_get( $key ) {
			if ( wp_using_ext_object_cache() ) {
				$found = false;
				$value = wp_cache_get( $key, GESHMAK_LUACH_CACHE_GROUP, false, $found );
				return $found ? $value : false;
			}
			return get_transient( $key );
		}

		/**
		 * Write to cache.
		 *
		 * @param string $key
		 * @param mixed  $value
		 * @param int    $ttl
		 * @return void
		 */
		protected function cache_set( $key, $value, $ttl ) {
			if ( wp_using_ext_object_cache() ) {
				wp_cache_set( $key, $value, GESHMAK_LUACH_CACHE_GROUP, $ttl );
				return;
			}
			set_transient( $key, $value, $ttl );
		}

		/**
		 * Clear all Luach caches: bump the version (abandons object-cache + future
		 * transient keys) and delete our existing transients directly.
		 *
		 * @return void
		 */
		public function clear_cache() {
			update_option( 'geshmak_luach_cache_version', $this->cache_version() + 1, false );

			global $wpdb;
			$like = $wpdb->esc_like( '_transient_gl' ) . '%';
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
			$like_to = $wpdb->esc_like( '_transient_timeout_gl' ) . '%';
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_to ) );

			if ( wp_using_ext_object_cache() && function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( GESHMAK_LUACH_CACHE_GROUP );
			}
		}

		// -------------------------------------------------------------------
		// CORE FETCH
		// -------------------------------------------------------------------

		/**
		 * Fetch a Hebcal endpoint with caching and stale-on-error.
		 *
		 * @param string $endpoint    One of the ENDPOINT_* constants.
		 * @param array  $params      Query params (cfg=json is added automatically).
		 * @param int    $ttl         Optional TTL override (seconds).
		 * @param bool   $allow_stale Serve a long-lived stale copy on failure (default true).
		 *                            Set false for real-time data (e.g. Assur Melacha "now")
		 *                            where a stale value would be misleading.
		 * @return array|WP_Error  Decoded body array, or WP_Error when no data at all.
		 */
		public function fetch( $endpoint, $params, $ttl = null, $allow_stale = true ) {
			$params['cfg'] = 'json';
			ksort( $params );

			$key       = $this->cache_key( $endpoint, $params );
			$stale_key = $key . '_s';

			// Fresh cache hit.
			$cached = $this->cache_get( $key );
			if ( false !== $cached ) {
				return $cached;
			}

			if ( null === $ttl ) {
				$ttl = (int) $this->get_setting( 'cache_ttl', 7 * DAY_IN_SECONDS );
			}
			$ttl = max( MINUTE_IN_SECONDS, $ttl );

			$url      = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $params ) ), $endpoint );
			$response = wp_remote_get(
				$url,
				array(
					'timeout'    => 15,
					'user-agent' => $this->user_agent(),
					'headers'    => array( 'Accept' => 'application/json' ),
				)
			);

			$error = '';

			if ( is_wp_error( $response ) ) {
				$error = $response->get_error_message();
			} else {
				$code = (int) wp_remote_retrieve_response_code( $response );
				$body = wp_remote_retrieve_body( $response );
				$data = json_decode( $body, true );

				if ( 200 === $code && is_array( $data ) ) {
					$this->cache_set( $key, $data, $ttl );
					// Long-lived stale copy for resilience (skipped for real-time data).
					if ( $allow_stale ) {
						$this->cache_set( $stale_key, $data, YEAR_IN_SECONDS );
					}
					return $data;
				}

				$error = 'HTTP ' . $code . ( isset( $data['error'] ) ? ' — ' . $data['error'] : '' );
			}

			// Failure path — serve the last good value rather than blanking the surface.
			$this->log( 'Fetch failed for ' . $url . ' :: ' . $error );

			if ( $allow_stale ) {
				$stale = $this->cache_get( $stale_key );
				if ( false !== $stale ) {
					return $stale;
				}
			}

			return new WP_Error( 'geshmak_luach_fetch_failed', $error );
		}

		/**
		 * Descriptive User-Agent (Hebcal etiquette).
		 *
		 * @return string
		 */
		protected function user_agent() {
			return sprintf(
				'Geshmak-Luach/%s (+https://geshmak.com.au; %s)',
				defined( 'GESHMAK_LUACH_VERSION' ) ? GESHMAK_LUACH_VERSION : '1.0',
				home_url( '/' )
			);
		}

		/**
		 * Log a failure when WP_DEBUG is on.
		 *
		 * @param string $message
		 * @return void
		 */
		protected function log( $message ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Geshmak Luach] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
		}

		// -------------------------------------------------------------------
		// CALENDAR-FAMILY PARAM BUILDER (shared by candles / parsha / holidays / leyning)
		// -------------------------------------------------------------------

		/**
		 * Build the shared /hebcal calendar params.
		 *
		 * @param array $args
		 * @return array
		 */
		protected function build_calendar_params( $args, $default_days = null ) {
			$s      = $this->get_settings();
			$params = array( 'v' => '1' );

			$params += $this->resolve_location( $args );

			if ( $this->resolve_israel( $args ) ) {
				$params['i'] = 'on';
			}

			// Candle lighting / havdalah configuration.
			$params['b'] = isset( $args['b'] ) && '' !== $args['b'] ? (int) $args['b'] : (int) $s['candle_mins'];

			$havdalah_mode = ! empty( $args['havdalah_mode'] ) ? $args['havdalah_mode'] : $s['havdalah_mode'];
			if ( 'mins' === $havdalah_mode ) {
				$params['m'] = isset( $args['havdalah_mins'] ) && '' !== $args['havdalah_mins'] ? (int) $args['havdalah_mins'] : (int) $s['havdalah_mins'];
			} else {
				$params['M'] = 'on'; // Havdalah by tzeit (3 small stars).
			}

			// Date window. Precedence: explicit start/end > year(+month) > a forward
			// window from today. The forward window matters because "this week / next"
			// surfaces must NOT fall back to year=now, which returns the whole Gregorian
			// year starting in January (so items[0] would be January's parsha, not the
			// upcoming one).
			$params += $this->resolve_date_window( $args, $default_days );

			return $params;
		}

		/**
		 * Resolve a Hebcal date window from args.
		 *
		 * @param array    $args
		 * @param int|null $default_days When set and no explicit window is given, query
		 *                               today → today + N days instead of the whole year.
		 * @return array start/end or year(+month) params.
		 */
		protected function resolve_date_window( $args, $default_days = null ) {
			if ( ! empty( $args['start'] ) || ! empty( $args['end'] ) ) {
				$start = ! empty( $args['start'] ) ? sanitize_text_field( $args['start'] ) : current_time( 'Y-m-d' );
				return array(
					'start' => $start,
					'end'   => ! empty( $args['end'] ) ? sanitize_text_field( $args['end'] ) : $start,
				);
			}

			if ( ! empty( $args['year'] ) ) {
				$window = array( 'year' => preg_replace( '/[^0-9a-z]/i', '', (string) $args['year'] ) );
				if ( ! empty( $args['month'] ) ) {
					$window['month'] = preg_replace( '/[^0-9a-z]/i', '', (string) $args['month'] );
				}
				return $window;
			}

			if ( null !== $default_days ) {
				$start = current_time( 'Y-m-d' );
				return array(
					'start' => $start,
					'end'   => gmdate( 'Y-m-d', strtotime( $start . ' +' . (int) $default_days . ' days' ) ),
				);
			}

			return array( 'year' => 'now' );
		}

		/**
		 * Normalise a raw Hebcal calendar item into a clean array, applying transliteration.
		 *
		 * @param array  $item
		 * @param string $scheme
		 * @return array
		 */
		protected function normalize_event( $item, $scheme ) {
			$title  = isset( $item['title'] ) ? $item['title'] : '';
			$hebrew = isset( $item['hebrew'] ) ? $item['hebrew'] : '';

			return array(
				'title'    => geshmak_luach_transliterate( $title, $scheme, $hebrew ),
				'title_en' => $title,
				'hebrew'   => $hebrew,
				'date'     => isset( $item['date'] ) ? $item['date'] : '',
				'category' => isset( $item['category'] ) ? $item['category'] : '',
				'subcat'   => isset( $item['subcat'] ) ? $item['subcat'] : '',
				'memo'     => isset( $item['memo'] ) ? $item['memo'] : '',
				'link'     => isset( $item['link'] ) ? $item['link'] : '',
				'leyning'  => isset( $item['leyning'] ) ? $item['leyning'] : array(),
				'raw'      => $item,
			);
		}

		/**
		 * Attribution block appended to every structured result.
		 *
		 * @return array
		 */
		public function attribution() {
			return array(
				'text' => self::ATTRIBUTION_TEXT,
				'url'  => self::ATTRIBUTION_URL,
			);
		}

		// ===================================================================
		// PUBLIC API — one method per Hebcal family. These double as template tags.
		// ===================================================================

		/**
		 * Candle lighting & havdalah times.
		 *
		 * @param array $args geonameid|latitude|longitude|tzid|israel|b|havdalah_mode|havdalah_mins|translit|year|month|start|end
		 * @return array { items: [...], attribution: [...] } or { error }
		 */
		public function get_candle_times( $args = array() ) {
			// Default to this week + a little (next Shabbos) rather than the whole year.
			$params      = $this->build_calendar_params( $args, 8 );
			$params['c'] = 'on'; // Candle lighting.

			$data = $this->fetch( self::ENDPOINT_CALENDAR, $params );
			if ( is_wp_error( $data ) ) {
				return $this->error_result( $data );
			}

			$scheme = $this->resolve_translit( $args );
			$items  = array();

			foreach ( (array) ( isset( $data['items'] ) ? $data['items'] : array() ) as $item ) {
				$cat = isset( $item['category'] ) ? $item['category'] : '';
				if ( in_array( $cat, array( 'candles', 'havdalah' ), true ) ) {
					$event             = $this->normalize_event( $item, $scheme );
					$event['time']     = $this->extract_time( $item );
					$items[]           = $event;
				}
			}

			return array(
				'location'    => isset( $data['location'] ) ? $data['location'] : array(),
				'items'       => $items,
				'attribution' => $this->attribution(),
			);
		}

		/**
		 * This week's / the requested window's parsha (sedrot), with optional leyning.
		 *
		 * @param array $args israel|translit|year|month|start|end + leyning(bool)
		 * @return array
		 */
		public function get_parsha( $args = array() ) {
			// Default to the upcoming Shabbos, not the whole Gregorian year.
			$params      = $this->build_calendar_params( $args, 8 );
			$params['s'] = 'on'; // Sedrot / parsha.
			if ( ! empty( $args['leyning'] ) ) {
				$params['leyning'] = 'on';
			}

			$data = $this->fetch( self::ENDPOINT_CALENDAR, $params );
			if ( is_wp_error( $data ) ) {
				return $this->error_result( $data );
			}

			$scheme = $this->resolve_translit( $args );
			$items  = array();
			foreach ( (array) ( isset( $data['items'] ) ? $data['items'] : array() ) as $item ) {
				if ( isset( $item['category'] ) && 'parashat' === $item['category'] ) {
					$items[] = $this->normalize_event( $item, $scheme );
				}
			}

			return array(
				'items'       => $items,
				'attribution' => $this->attribution(),
			);
		}

		/**
		 * Holidays — major/minor/modern, Rosh Chodesh, fasts, special Shabbatos, Omer, molad,
		 * and daily learning (Daf Yomi, etc.). Toggle families via args.
		 *
		 * @param array $args Booleans: major,minor,modern,roshchodesh,fasts,special,omer,molad,dafyomi.
		 *                    Plus israel|translit|year|month|start|end.
		 * @return array
		 */
		public function get_holidays( $args = array() ) {
			// Default to the next ~6 months from today so "next holiday" always resolves
			// (and never starts back in January). Callers can still pass year/start/end.
			$params = $this->build_calendar_params( $args, 180 );

			// Family toggles — default to the standard holiday set when nothing specified.
			$map = array(
				'major'       => 'maj',
				'minor'       => 'min',
				'modern'      => 'mod',
				'roshchodesh' => 'nx',
				'fasts'       => 'mf',
				'special'     => 'ss',
				'omer'        => 'o',
				'molad'       => 'molad',
				// Daily learning schedules.
				'dafyomi'                  => 'F',
				'dafweek'                  => 'dw',
				'mishnayomi'               => 'myomi',
				'nachyomi'                 => 'nyomi',
				'yerushalmi'               => 'yyomi',
				'yerushalmi_schottenstein' => 'yys',
				'tanachyomi'               => 'dty',
				'tehillim'                 => 'dps',
				'rambam1'                  => 'dr1',
				'rambam3'                  => 'dr3',
				'seferhamitzvos'           => 'dsm',
				'kitzur'                   => 'dksa',
				'arukhhashulchan'          => 'ahsy',
				'chofetzchaim'             => 'dcc',
				'shemirashalashon'         => 'dshl',
				'pirkeiavos'               => 'dpa',
			);

			// Convenience: a `learning` CSV expands into the individual toggles above.
			if ( ! empty( $args['learning'] ) ) {
				$wanted = is_array( $args['learning'] ) ? $args['learning'] : array_map( 'trim', explode( ',', $args['learning'] ) );
				foreach ( $wanted as $slug ) {
					$args[ sanitize_key( $slug ) ] = 1;
				}
			}

			$any = false;
			foreach ( $map as $arg_key => $hc_key ) {
				if ( isset( $args[ $arg_key ] ) && geshmak_luach_to_bool( $args[ $arg_key ] ) ) {
					$params[ $hc_key ] = 'on';
					$any               = true;
				}
			}
			if ( ! $any ) {
				$params['maj'] = 'on';
				$params['min'] = 'on';
				$params['mod'] = 'on';
				$params['nx']  = 'on';
				$params['mf']  = 'on';
				$params['ss']  = 'on';
			}

			$data = $this->fetch( self::ENDPOINT_CALENDAR, $params );
			if ( is_wp_error( $data ) ) {
				return $this->error_result( $data );
			}

			$scheme = $this->resolve_translit( $args );
			$items  = array();
			$today  = current_time( 'Y-m-d' );
			$upcoming_only = ! empty( $args['upcoming'] );

			foreach ( (array) ( isset( $data['items'] ) ? $data['items'] : array() ) as $item ) {
				if ( $upcoming_only && isset( $item['date'] ) && substr( $item['date'], 0, 10 ) < $today ) {
					continue;
				}
				$items[] = $this->normalize_event( $item, $scheme );
			}

			if ( ! empty( $args['limit'] ) ) {
				$items = array_slice( $items, 0, (int) $args['limit'] );
			}

			return array(
				'items'       => $items,
				'attribution' => $this->attribution(),
			);
		}

		/**
		 * Full Torah-reading (leyning) detail — full kriyah, optional triennial, weekday.
		 *
		 * @param array $args translit|israel|start|end|year|month + triennial(bool)|weekday(bool)
		 * @return array
		 */
		public function get_leyning( $args = array() ) {
			$params = array();

			// Default to the upcoming Shabbos rather than the whole year from January.
			$params += $this->resolve_date_window( $args, 8 );
			if ( isset( $params['year'] ) && ! isset( $params['month'] ) ) {
				$params['month'] = 'x'; // Explicit year → whole-year leyning.
			}

			if ( $this->resolve_israel( $args ) ) {
				$params['i'] = 'on';
			}
			if ( ! empty( $args['triennial'] ) ) {
				$params['triennial'] = 'on';
			}
			if ( ! empty( $args['weekday'] ) ) {
				$params['weekday'] = 'on';
			}

			$data = $this->fetch( self::ENDPOINT_LEYNING, $params );
			if ( is_wp_error( $data ) ) {
				return $this->error_result( $data );
			}

			$scheme = $this->resolve_translit( $args );
			$items  = array();
			foreach ( (array) ( isset( $data['items'] ) ? $data['items'] : array() ) as $item ) {
				$name              = isset( $item['name']['en'] ) ? $item['name']['en'] : ( isset( $item['name'] ) ? $item['name'] : '' );
				$hebrew            = isset( $item['name']['he'] ) ? $item['name']['he'] : '';
				$items[] = array(
					'title'    => geshmak_luach_transliterate( $name, $scheme, $hebrew ),
					'title_en' => $name,
					'hebrew'   => $hebrew,
					'date'     => isset( $item['date'] ) ? $item['date'] : '',
					'summary'  => isset( $item['summary'] ) ? $item['summary'] : '',
					'fullkriyah' => isset( $item['fullkriyah'] ) ? $item['fullkriyah'] : array(),
					'haftarah' => isset( $item['haftara'] ) ? $item['haftara'] : '',
					'triennial' => isset( $item['triennial'] ) ? $item['triennial'] : array(),
					'raw'      => $item,
				);
			}

			return array(
				'items'       => $items,
				'attribution' => $this->attribution(),
			);
		}

		/**
		 * Halachic times (zmanim) for a date and location, with elevation support.
		 *
		 * @param array $args date|start|end|geonameid|latitude|longitude|tzid|elevation|times(array|csv)
		 * @return array { date, location, times: [ key => [label,time], ... ], attribution }
		 */
		public function get_zmanim( $args = array() ) {
			$params = $this->resolve_location( $args );

			if ( ! empty( $args['start'] ) && ! empty( $args['end'] ) ) {
				$params['start'] = sanitize_text_field( $args['start'] );
				$params['end']   = sanitize_text_field( $args['end'] );
			} else {
				$params['date'] = ! empty( $args['date'] ) ? sanitize_text_field( $args['date'] ) : current_time( 'Y-m-d' );
			}

			$data = $this->fetch( self::ENDPOINT_ZMANIM, $params );
			if ( is_wp_error( $data ) ) {
				return $this->error_result( $data );
			}

			// Which times to surface: per-instance override, else the configured subset, else all.
			$wanted = array();
			if ( ! empty( $args['times'] ) ) {
				$wanted = is_array( $args['times'] ) ? $args['times'] : array_map( 'trim', explode( ',', $args['times'] ) );
			}
			if ( ( empty( $wanted ) || in_array( 'all', $wanted, true ) ) ) {
				$cfg = $this->get_setting( 'zmanim_keys', array() );
				$wanted = ( ! empty( $args['all'] ) || in_array( 'all', (array) $wanted, true ) || empty( $cfg ) ) ? array() : $cfg;
			}

			$times_raw = isset( $data['times'] ) ? $data['times'] : array();
			$times     = array();

			foreach ( $times_raw as $key => $value ) {
				if ( ! empty( $wanted ) && ! in_array( $key, $wanted, true ) ) {
					continue;
				}
				$times[ $key ] = array(
					'key'   => $key,
					'label' => geshmak_luach_zman_label( $key ),
					'time'  => $value,
				);
			}

			return array(
				'date'        => isset( $data['date'] ) ? $data['date'] : '',
				'location'    => isset( $data['location'] ) ? $data['location'] : array(),
				'times'       => $times,
				'attribution' => $this->attribution(),
			);
		}

		/**
		 * Convert a Gregorian date to a Hebrew date (g2h). Defaults to today.
		 *
		 * @param array $args date(Y-m-d)|gy|gm|gd|after_sunset|translit
		 * @return array
		 */
		public function get_hebrew_date( $args = array() ) {
			$args['direction'] = 'g2h';
			return $this->convert_date( $args );
		}

		/**
		 * Hebrew-date converter — both directions.
		 *
		 * @param array $args direction(g2h|h2g)|date|gy|gm|gd|hy|hm|hd|after_sunset|translit
		 * @return array
		 */
		public function convert_date( $args = array() ) {
			$direction = ! empty( $args['direction'] ) ? $args['direction'] : 'g2h';
			$params    = array();

			if ( 'h2g' === $direction ) {
				$params['h2g'] = '1';
				$params['hy']  = isset( $args['hy'] ) ? (int) $args['hy'] : '';
				$params['hm']  = isset( $args['hm'] ) ? sanitize_text_field( $args['hm'] ) : '';
				$params['hd']  = isset( $args['hd'] ) ? (int) $args['hd'] : '';
			} else {
				$params['g2h'] = '1';

				if ( ! empty( $args['date'] ) ) {
					$ts            = strtotime( $args['date'] );
					$params['gy']  = (int) gmdate( 'Y', $ts );
					$params['gm']  = (int) gmdate( 'n', $ts );
					$params['gd']  = (int) gmdate( 'j', $ts );
				} elseif ( isset( $args['gy'], $args['gm'], $args['gd'] ) ) {
					$params['gy'] = (int) $args['gy'];
					$params['gm'] = (int) $args['gm'];
					$params['gd'] = (int) $args['gd'];
				} else {
					$params['gy'] = (int) current_time( 'Y' );
					$params['gm'] = (int) current_time( 'n' );
					$params['gd'] = (int) current_time( 'j' );
				}

				if ( ! empty( $args['after_sunset'] ) && geshmak_luach_to_bool( $args['after_sunset'] ) ) {
					$params['gs'] = 'on'; // After sunset → next Hebrew day.
				}
			}

			// Hebrew-date conversions are effectively permanent — cache for a year.
			$data = $this->fetch( self::ENDPOINT_CONVERTER, $params, YEAR_IN_SECONDS );
			if ( is_wp_error( $data ) ) {
				return $this->error_result( $data );
			}

			$scheme = $this->resolve_translit( $args );
			$hebrew = isset( $data['hebrew'] ) ? $data['hebrew'] : '';
			$en     = isset( $data['hy'], $data['hm'], $data['hd'] ) ? trim( $data['hd'] . ' ' . $data['hm'] . ' ' . $data['hy'] ) : '';

			return array(
				'gregorian'    => isset( $data['gy'] ) ? sprintf( '%04d-%02d-%02d', $data['gy'], $data['gm'], $data['gd'] ) : '',
				'hebrew'       => $hebrew,
				'display'      => geshmak_luach_transliterate( $en, $scheme, $hebrew ),
				'display_en'   => $en,
				'hy'           => isset( $data['hy'] ) ? $data['hy'] : '',
				'hm'           => isset( $data['hm'] ) ? $data['hm'] : '',
				'hd'           => isset( $data['hd'] ) ? $data['hd'] : '',
				'after_sunset' => ! empty( $data['afterSunset'] ),
				'events'       => isset( $data['events'] ) ? $data['events'] : array(),
				'attribution'  => $this->attribution(),
			);
		}

		/**
		 * Yahrzeit / Hebrew birthday / anniversary calculation.
		 *
		 * @param array $args type(Yahrzeit|Birthday|Anniversary)|name|date|gy|gm|gd|after_sunset|years|translit
		 * @return array
		 */
		public function get_yahrzeit( $args = array() ) {
			$type = ! empty( $args['type'] ) ? ucfirst( strtolower( $args['type'] ) ) : 'Yahrzeit';
			if ( ! in_array( $type, array( 'Yahrzeit', 'Birthday', 'Anniversary' ), true ) ) {
				$type = 'Yahrzeit';
			}

			if ( ! empty( $args['date'] ) ) {
				$ts = strtotime( $args['date'] );
				$gy = (int) gmdate( 'Y', $ts );
				$gm = (int) gmdate( 'n', $ts );
				$gd = (int) gmdate( 'j', $ts );
			} else {
				$gy = isset( $args['gy'] ) ? (int) $args['gy'] : 0;
				$gm = isset( $args['gm'] ) ? (int) $args['gm'] : 0;
				$gd = isset( $args['gd'] ) ? (int) $args['gd'] : 0;
			}

			if ( ! $gy || ! $gm || ! $gd ) {
				return $this->error_result( new WP_Error( 'geshmak_luach_bad_date', __( 'A valid date is required.', 'geshmak-luach' ) ) );
			}

			$params = array(
				'v'       => 'yahrzeit',
				'years'   => ! empty( $args['years'] ) ? (int) $args['years'] : 1,
				'hebdate' => 'on',
				'yizkor'  => 'off',
				'i1'      => '0',
				't1'      => $type,
				'd1'      => $gd,
				'm1'      => $gm,
				'y1'      => $gy,
				'n1'      => ! empty( $args['name'] ) ? sanitize_text_field( $args['name'] ) : __( 'Anniversary', 'geshmak-luach' ),
			);
			if ( ! empty( $args['after_sunset'] ) && geshmak_luach_to_bool( $args['after_sunset'] ) ) {
				$params['s1'] = 'on';
			}

			$data = $this->fetch( self::ENDPOINT_YAHRZEIT, $params, YEAR_IN_SECONDS );
			if ( is_wp_error( $data ) ) {
				return $this->error_result( $data );
			}

			$scheme = $this->resolve_translit( $args );
			$items  = array();
			foreach ( (array) ( isset( $data['items'] ) ? $data['items'] : array() ) as $item ) {
				$title   = isset( $item['title'] ) ? $item['title'] : '';
				$hebrew  = isset( $item['hebrew'] ) ? $item['hebrew'] : '';
				// The yahrzeit API returns the Hebrew date as a transliterated string
				// (e.g. "18 Adar 5786") in `hdate`, not Hebrew script — remap it too.
				$hdate   = isset( $item['hdate'] ) ? $item['hdate'] : '';
				$items[] = array(
					'title'    => geshmak_luach_transliterate( $title, $scheme, $hebrew ),
					'title_en' => $title,
					'hebrew'   => $hebrew,
					'hdate'    => geshmak_luach_transliterate( $hdate, $scheme, '' ),
					'hdate_en' => $hdate,
					'date'     => isset( $item['date'] ) ? $item['date'] : '',
					'category' => isset( $item['category'] ) ? $item['category'] : '',
					'memo'     => isset( $item['memo'] ) ? $item['memo'] : '',
					'raw'      => $item,
				);
			}

			return array(
				'type'        => $type,
				'items'       => $items,
				'attribution' => $this->attribution(),
			);
		}

		/**
		 * Assur Melacha (work-forbidden) status for a location and moment.
		 *
		 * Uses the /zmanim endpoint in issur-melacha mode (im=1). This is REAL-TIME
		 * data — it flips at candle lighting / havdalah — so it is only briefly cached
		 * (default now) and never served stale on error.
		 *
		 * @param array $args geonameid|latitude|longitude|tzid|elevation|datetime(ISO 8601)
		 * @return array { ok, is_assur, local_time, location, attribution } or { error }
		 */
		public function get_assur_melacha( $args = array() ) {
			$params       = $this->resolve_location( $args );
			$params['im'] = '1';

			$has_dt = ! empty( $args['datetime'] );
			if ( $has_dt ) {
				$params['dt'] = sanitize_text_field( $args['datetime'] );
			}

			// Explicit moment → deterministic (cache long). "Now" → cache ~1 min, no stale.
			$ttl         = $has_dt ? YEAR_IN_SECONDS : MINUTE_IN_SECONDS;
			$allow_stale = $has_dt;

			$data = $this->fetch( self::ENDPOINT_ZMANIM, $params, $ttl, $allow_stale );
			if ( is_wp_error( $data ) ) {
				return $this->error_result( $data );
			}

			$status = isset( $data['status'] ) ? $data['status'] : array();

			return array(
				'ok'          => isset( $status['isAssurBemlacha'] ),
				'is_assur'    => ! empty( $status['isAssurBemlacha'] ),
				'local_time'  => isset( $status['localTime'] ) ? $status['localTime'] : '',
				'location'    => isset( $data['location'] ) ? $data['location'] : array(),
				'attribution' => $this->attribution(),
			);
		}

		// -------------------------------------------------------------------
		// HELPERS
		// -------------------------------------------------------------------

		/**
		 * Pull a clean time string out of a candle/havdalah event.
		 *
		 * @param array $item
		 * @return string
		 */
		protected function extract_time( $item ) {
			// Hebcal exposes an ISO datetime (with the location's UTC offset) in `date`
			// for timed events. Format honouring that embedded offset.
			if ( ! empty( $item['date'] ) && false !== strpos( $item['date'], 'T' ) ) {
				$formatted = geshmak_luach_format_iso_time( $item['date'] );
				if ( '' !== $formatted ) {
					return $formatted;
				}
			}
			// Fallback: a trailing time in the title ("Candle lighting: 6:12pm").
			if ( ! empty( $item['title'] ) && preg_match( '/(\d{1,2}:\d{2}\s*[ap]m?)/i', $item['title'], $m ) ) {
				return $m[1];
			}
			return '';
		}

		/**
		 * Wrap a WP_Error as a structured error result.
		 *
		 * @param WP_Error $error
		 * @return array
		 */
		protected function error_result( $error ) {
			return array(
				'error'       => is_wp_error( $error ) ? $error->get_error_message() : (string) $error,
				'items'       => array(),
				'attribution' => $this->attribution(),
			);
		}
	}
}

// ---------------------------------------------------------------------------
// TEMPLATE TAGS — clean procedural wrappers for theme developers.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_service' ) ) {
	/**
	 * Get the shared Hebcal service instance.
	 *
	 * @return Geshmak_Luach_Hebcal_Service
	 */
	function geshmak_luach_service() {
		return Geshmak_Luach_Hebcal_Service::instance();
	}
}

if ( ! function_exists( 'geshmak_luach_to_bool' ) ) {
	/**
	 * Loose-to-strict boolean for shortcode/widget attributes.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	function geshmak_luach_to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( strtolower( (string) $value ), array( '1', 'on', 'yes', 'true' ), true );
	}
}

if ( ! function_exists( 'geshmak_luach_format_iso_time' ) ) {
	/**
	 * Format a Hebcal ISO datetime as a localised time, honouring the UTC offset
	 * embedded in the string.
	 *
	 * Hebcal returns each time in the LOCATION's timezone (e.g. "...T18:12:00+10:00").
	 * We format using that embedded offset via wp_date() — NOT date_i18n() with a raw
	 * timestamp (which renders the UTC wall-clock and lands ~half a day out) and NOT a
	 * forced site timezone (which would be wrong when showing another city's zmanim).
	 *
	 * @param string $iso
	 * @param string $format Optional; defaults to the site time format.
	 * @return string Plain (unescaped) localised time, or '' on failure.
	 */
	function geshmak_luach_format_iso_time( $iso, $format = '' ) {
		$iso = (string) $iso;
		if ( '' === $iso ) {
			return '';
		}
		$dt = date_create( $iso );
		if ( ! $dt ) {
			return '';
		}
		if ( '' === $format ) {
			$format = get_option( 'time_format', 'g:i a' );
		}
		return wp_date( $format, $dt->getTimestamp(), $dt->getTimezone() );
	}
}

if ( ! function_exists( 'geshmak_luach_format_iso_date' ) ) {
	/**
	 * Format a Hebcal date (ISO datetime or plain Y-m-d) as a localised date,
	 * honouring any embedded UTC offset.
	 *
	 * @param string $iso
	 * @param string $format Optional; defaults to the Luach date format then the site format.
	 * @return string Plain (unescaped) localised date, or '' on failure.
	 */
	function geshmak_luach_format_iso_date( $iso, $format = '' ) {
		$iso = (string) $iso;
		if ( '' === $iso ) {
			return '';
		}
		$dt = date_create( $iso );
		if ( ! $dt ) {
			return '';
		}
		if ( '' === $format ) {
			$cfg    = geshmak_luach_service()->get_setting( 'date_format' );
			$format = $cfg ? $cfg : get_option( 'date_format', 'F j, Y' );
		}
		return wp_date( $format, $dt->getTimestamp(), $dt->getTimezone() );
	}
}

if ( ! function_exists( 'geshmak_luach_get_candle_times' ) ) {
	function geshmak_luach_get_candle_times( $args = array() ) {
		return geshmak_luach_service()->get_candle_times( $args );
	}
}
if ( ! function_exists( 'geshmak_luach_get_parsha' ) ) {
	function geshmak_luach_get_parsha( $args = array() ) {
		return geshmak_luach_service()->get_parsha( $args );
	}
}
if ( ! function_exists( 'geshmak_luach_get_zmanim' ) ) {
	function geshmak_luach_get_zmanim( $args = array() ) {
		return geshmak_luach_service()->get_zmanim( $args );
	}
}
if ( ! function_exists( 'geshmak_luach_get_hebrew_date' ) ) {
	function geshmak_luach_get_hebrew_date( $args = array() ) {
		return geshmak_luach_service()->get_hebrew_date( $args );
	}
}
if ( ! function_exists( 'geshmak_luach_get_holidays' ) ) {
	function geshmak_luach_get_holidays( $args = array() ) {
		return geshmak_luach_service()->get_holidays( $args );
	}
}
if ( ! function_exists( 'geshmak_luach_get_leyning' ) ) {
	function geshmak_luach_get_leyning( $args = array() ) {
		return geshmak_luach_service()->get_leyning( $args );
	}
}
if ( ! function_exists( 'geshmak_luach_get_yahrzeit' ) ) {
	function geshmak_luach_get_yahrzeit( $args = array() ) {
		return geshmak_luach_service()->get_yahrzeit( $args );
	}
}
if ( ! function_exists( 'geshmak_luach_convert_date' ) ) {
	function geshmak_luach_convert_date( $args = array() ) {
		return geshmak_luach_service()->convert_date( $args );
	}
}
if ( ! function_exists( 'geshmak_luach_get_assur_melacha' ) ) {
	function geshmak_luach_get_assur_melacha( $args = array() ) {
		return geshmak_luach_service()->get_assur_melacha( $args );
	}
}
