<?php
/** בס״ד
 * SHORTCODES
 *
 * One shortcode per Hebcal family. Every shortcode accepts attributes that
 * override the global settings per instance (location, geonameid, i, translit,
 * date, times, etc.). All of them call the shared Hebcal service and the shared
 * renderers — no shortcode talks to Hebcal directly.
 *
 *   [geshmak_luach_candles]       candle lighting / havdalah
 *   [geshmak_luach_parsha]        this week's parsha (+ leyning detail)
 *   [geshmak_luach_zmanim]        zmanim table (all or selected times)
 *   [geshmak_luach_hebrew_date]   today's / a given date's Hebrew date
 *   [geshmak_luach_convert]       date converter (g2h / h2g)
 *   [geshmak_luach_holidays]      upcoming / today's holidays
 *   [geshmak_luach_leyning]       Torah reading detail
 *   [geshmak_luach_yahrzeit]      yahrzeit / Hebrew birthday / anniversary
 *
 * DEVELOPED BY TOM GOLDSTEIN > GESHMAK! > https://geshmak.com.au/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'geshmak_luach_common_atts' ) ) {
	/**
	 * Pull the shared location / display overrides out of a shortcode atts array.
	 *
	 * @param array $atts
	 * @return array
	 */
	function geshmak_luach_common_atts( $atts ) {
		$args = array();
		foreach ( array( 'geonameid', 'location', 'latitude', 'longitude', 'tzid', 'elevation', 'translit', 'i', 'israel' ) as $key ) {
			if ( isset( $atts[ $key ] ) && '' !== $atts[ $key ] ) {
				$args[ $key ] = $atts[ $key ];
			}
		}
		// Friendly aliases: location= maps to geonameid when numeric; i= maps to israel.
		if ( isset( $args['location'] ) && ! isset( $args['geonameid'] ) && ctype_digit( (string) $args['location'] ) ) {
			$args['geonameid'] = $args['location'];
		}
		if ( isset( $args['i'] ) && ! isset( $args['israel'] ) ) {
			$args['israel'] = $args['i'];
		}
		return $args;
	}
}

if ( ! function_exists( 'geshmak_luach_display_atts' ) ) {
	/**
	 * Extract display-only flags from atts.
	 *
	 * @param array $atts
	 * @return array
	 */
	function geshmak_luach_display_atts( $atts ) {
		return array(
			'show_hebrew'  => isset( $atts['show_hebrew'] ) ? $atts['show_hebrew'] : '1',
			'show_credit'  => isset( $atts['show_credit'] ) ? $atts['show_credit'] : '1',
			'show_leyning' => isset( $atts['show_leyning'] ) ? $atts['show_leyning'] : '0',
		);
	}
}

// ---------------------------------------------------------------------------
// CANDLE LIGHTING / HAVDALAH
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_shortcode_candles' ) ) {
	function geshmak_luach_shortcode_candles( $atts ) {
		$atts = shortcode_atts(
			array(
				'geonameid'     => '', 'location' => '', 'latitude' => '', 'longitude' => '',
				'tzid'          => '', 'translit' => '', 'i' => '', 'israel' => '',
				'b'             => '', 'havdalah_mode' => '', 'havdalah_mins' => '',
				'year'          => '', 'month' => '', 'start' => '', 'end' => '',
				'show_hebrew'   => '1', 'show_credit' => '1',
			),
			$atts,
			'geshmak_luach_candles'
		);

		$args = geshmak_luach_common_atts( $atts );
		foreach ( array( 'b', 'havdalah_mode', 'havdalah_mins', 'year', 'month', 'start', 'end' ) as $k ) {
			if ( '' !== $atts[ $k ] ) {
				$args[ $k ] = $atts[ $k ];
			}
		}

		return geshmak_luach_render_candles( geshmak_luach_service()->get_candle_times( $args ), geshmak_luach_display_atts( $atts ) );
	}
}
add_shortcode( 'geshmak_luach_candles', 'geshmak_luach_shortcode_candles' );

// ---------------------------------------------------------------------------
// PARSHA
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_shortcode_parsha' ) ) {
	function geshmak_luach_shortcode_parsha( $atts ) {
		$atts = shortcode_atts(
			array(
				'geonameid'   => '', 'location' => '', 'latitude' => '', 'longitude' => '', 'tzid' => '',
				'translit'    => '', 'i' => '', 'israel' => '',
				'year'        => '', 'month' => '', 'start' => '', 'end' => '',
				'leyning'     => '0', 'show_leyning' => '', 'show_hebrew' => '1', 'show_credit' => '1',
			),
			$atts,
			'geshmak_luach_parsha'
		);

		$args = geshmak_luach_common_atts( $atts );
		foreach ( array( 'year', 'month', 'start', 'end' ) as $k ) {
			if ( '' !== $atts[ $k ] ) {
				$args[ $k ] = $atts[ $k ];
			}
		}
		$show_leyning   = ( '' !== $atts['show_leyning'] ) ? $atts['show_leyning'] : $atts['leyning'];
		$args['leyning'] = geshmak_luach_to_bool( $show_leyning );

		$display                 = geshmak_luach_display_atts( $atts );
		$display['show_leyning'] = $show_leyning;

		return geshmak_luach_render_parsha( geshmak_luach_service()->get_parsha( $args ), $display );
	}
}
add_shortcode( 'geshmak_luach_parsha', 'geshmak_luach_shortcode_parsha' );

// ---------------------------------------------------------------------------
// ZMANIM
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_shortcode_zmanim' ) ) {
	function geshmak_luach_shortcode_zmanim( $atts ) {
		$atts = shortcode_atts(
			array(
				'geonameid' => '', 'location' => '', 'latitude' => '', 'longitude' => '', 'tzid' => '',
				'elevation' => '', 'translit' => '',
				'date'      => '', 'start' => '', 'end' => '', 'times' => '', 'all' => '',
				'show_credit' => '1',
			),
			$atts,
			'geshmak_luach_zmanim'
		);

		$args = geshmak_luach_common_atts( $atts );
		foreach ( array( 'date', 'start', 'end', 'times', 'all' ) as $k ) {
			if ( '' !== $atts[ $k ] ) {
				$args[ $k ] = $atts[ $k ];
			}
		}

		return geshmak_luach_render_zmanim( geshmak_luach_service()->get_zmanim( $args ), geshmak_luach_display_atts( $atts ) );
	}
}
add_shortcode( 'geshmak_luach_zmanim', 'geshmak_luach_shortcode_zmanim' );

// ---------------------------------------------------------------------------
// HEBREW DATE
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_shortcode_hebrew_date' ) ) {
	function geshmak_luach_shortcode_hebrew_date( $atts ) {
		$atts = shortcode_atts(
			array(
				'date'        => '', 'translit' => '', 'after_sunset' => '',
				'show_hebrew' => '1', 'show_credit' => '0',
			),
			$atts,
			'geshmak_luach_hebrew_date'
		);

		$args = array();
		if ( '' !== $atts['translit'] ) {
			$args['translit'] = $atts['translit'];
		}
		if ( '' !== $atts['date'] ) {
			$args['date'] = $atts['date'];
		}
		if ( '' !== $atts['after_sunset'] ) {
			$args['after_sunset'] = $atts['after_sunset'];
		}

		return geshmak_luach_render_hebrew_date( geshmak_luach_service()->get_hebrew_date( $args ), geshmak_luach_display_atts( $atts ) );
	}
}
add_shortcode( 'geshmak_luach_hebrew_date', 'geshmak_luach_shortcode_hebrew_date' );

// ---------------------------------------------------------------------------
// DATE CONVERTER (g2h / h2g)
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_shortcode_convert' ) ) {
	function geshmak_luach_shortcode_convert( $atts ) {
		$atts = shortcode_atts(
			array(
				'direction'   => 'g2h', 'date' => '', 'translit' => '', 'after_sunset' => '',
				'gy'          => '', 'gm' => '', 'gd' => '',
				'hy'          => '', 'hm' => '', 'hd' => '',
				'show_hebrew' => '1', 'show_credit' => '0',
			),
			$atts,
			'geshmak_luach_convert'
		);

		$args = array( 'direction' => ( 'h2g' === $atts['direction'] ? 'h2g' : 'g2h' ) );
		foreach ( array( 'date', 'translit', 'after_sunset', 'gy', 'gm', 'gd', 'hy', 'hm', 'hd' ) as $k ) {
			if ( '' !== $atts[ $k ] ) {
				$args[ $k ] = $atts[ $k ];
			}
		}

		return geshmak_luach_render_hebrew_date( geshmak_luach_service()->convert_date( $args ), geshmak_luach_display_atts( $atts ) );
	}
}
add_shortcode( 'geshmak_luach_convert', 'geshmak_luach_shortcode_convert' );

// ---------------------------------------------------------------------------
// HOLIDAYS
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_shortcode_holidays' ) ) {
	function geshmak_luach_shortcode_holidays( $atts ) {
		$atts = shortcode_atts(
			array(
				'geonameid' => '', 'location' => '', 'latitude' => '', 'longitude' => '', 'tzid' => '',
				'translit'  => '', 'i' => '', 'israel' => '',
				'year'      => '', 'month' => '', 'start' => '', 'end' => '',
				'major'     => '', 'minor' => '', 'modern' => '', 'roshchodesh' => '', 'fasts' => '',
				'special'   => '', 'omer' => '', 'molad' => '', 'dafyomi' => '',
				'mishnayomi' => '', 'yerushalmi' => '', 'nachyomi' => '',
				'upcoming'  => '', 'limit' => '', 'show_hebrew' => '1', 'show_credit' => '1',
			),
			$atts,
			'geshmak_luach_holidays'
		);

		$args = geshmak_luach_common_atts( $atts );
		foreach ( array(
			'year', 'month', 'start', 'end', 'major', 'minor', 'modern', 'roshchodesh', 'fasts',
			'special', 'omer', 'molad', 'dafyomi', 'mishnayomi', 'yerushalmi', 'nachyomi', 'upcoming', 'limit',
		) as $k ) {
			if ( '' !== $atts[ $k ] ) {
				$args[ $k ] = $atts[ $k ];
			}
		}

		return geshmak_luach_render_holidays( geshmak_luach_service()->get_holidays( $args ), geshmak_luach_display_atts( $atts ) );
	}
}
add_shortcode( 'geshmak_luach_holidays', 'geshmak_luach_shortcode_holidays' );

// ---------------------------------------------------------------------------
// LEYNING
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_shortcode_leyning' ) ) {
	function geshmak_luach_shortcode_leyning( $atts ) {
		$atts = shortcode_atts(
			array(
				'translit'  => '', 'i' => '', 'israel' => '',
				'year'      => '', 'month' => '', 'start' => '', 'end' => '',
				'triennial' => '', 'weekday' => '', 'show_hebrew' => '1', 'show_credit' => '1',
			),
			$atts,
			'geshmak_luach_leyning'
		);

		$args = geshmak_luach_common_atts( $atts );
		foreach ( array( 'year', 'month', 'start', 'end', 'triennial', 'weekday' ) as $k ) {
			if ( '' !== $atts[ $k ] ) {
				$args[ $k ] = $atts[ $k ];
			}
		}

		return geshmak_luach_render_leyning( geshmak_luach_service()->get_leyning( $args ), geshmak_luach_display_atts( $atts ) );
	}
}
add_shortcode( 'geshmak_luach_leyning', 'geshmak_luach_shortcode_leyning' );

// ---------------------------------------------------------------------------
// YAHRZEIT / HEBREW BIRTHDAY / ANNIVERSARY
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_shortcode_yahrzeit' ) ) {
	function geshmak_luach_shortcode_yahrzeit( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'        => 'Yahrzeit', 'name' => '', 'date' => '',
				'gy'          => '', 'gm' => '', 'gd' => '',
				'after_sunset' => '', 'years' => '', 'translit' => '',
				'show_hebrew' => '1', 'show_credit' => '1',
			),
			$atts,
			'geshmak_luach_yahrzeit'
		);

		$args = array( 'type' => $atts['type'] );
		foreach ( array( 'name', 'date', 'gy', 'gm', 'gd', 'after_sunset', 'years', 'translit' ) as $k ) {
			if ( '' !== $atts[ $k ] ) {
				$args[ $k ] = $atts[ $k ];
			}
		}

		return geshmak_luach_render_yahrzeit( geshmak_luach_service()->get_yahrzeit( $args ), geshmak_luach_display_atts( $atts ) );
	}
}
add_shortcode( 'geshmak_luach_yahrzeit', 'geshmak_luach_shortcode_yahrzeit' );
