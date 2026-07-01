<?php
/** בס״ד
 * SHARED FRONT-END RENDERERS
 *
 * HTML renderers shared by every surface (shortcodes, Elementor dynamic tags,
 * atomic widgets). Each takes a structured array from the Hebcal service and
 * returns escaped, RTL-aware HTML. Keeping rendering here keeps the surfaces DRY.
 *
 * Front-end CSS is registered once and enqueued only when a surface renders.
 *
 * DEVELOPED BY TOM GOLDSTEIN > GESHMAK! > https://geshmak.com.au/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// ASSETS — registered up front, enqueued on demand.
// ---------------------------------------------------------------------------

add_action( 'wp_enqueue_scripts', 'geshmak_luach_register_styles' );

if ( ! function_exists( 'geshmak_luach_register_styles' ) ) {
	function geshmak_luach_register_styles() {
		wp_register_style(
			'geshmak-luach',
			GESHMAK_LUACH_PLUGIN_URL . 'assets/geshmak-luach.css',
			array(),
			defined( 'GESHMAK_LUACH_VERSION' ) ? GESHMAK_LUACH_VERSION : '1.0'
		);
	}
}

if ( ! function_exists( 'geshmak_luach_enqueue_styles' ) ) {
	/**
	 * Enqueue the front-end stylesheet (safe to call from a late render).
	 *
	 * @return void
	 */
	function geshmak_luach_enqueue_styles() {
		if ( ! wp_style_is( 'geshmak-luach', 'registered' ) ) {
			geshmak_luach_register_styles();
		}
		wp_enqueue_style( 'geshmak-luach' );
	}
}

// ---------------------------------------------------------------------------
// SMALL HELPERS
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_format_time' ) ) {
	/**
	 * Format a Hebcal ISO datetime into a localised time string.
	 *
	 * @param string $iso
	 * @return string
	 */
	function geshmak_luach_format_time( $iso ) {
		if ( '' === (string) $iso ) {
			return '';
		}
		// Honour the timezone offset Hebcal embeds in the ISO datetime.
		$formatted = geshmak_luach_format_iso_time( $iso );
		return '' === $formatted ? esc_html( (string) $iso ) : esc_html( $formatted );
	}
}

if ( ! function_exists( 'geshmak_luach_format_date' ) ) {
	/**
	 * Format a date (Y-m-d or ISO) into a localised date string.
	 *
	 * @param string $date
	 * @return string
	 */
	function geshmak_luach_format_date( $date ) {
		if ( '' === (string) $date ) {
			return '';
		}
		$formatted = geshmak_luach_format_iso_date( $date );
		return '' === $formatted ? esc_html( (string) $date ) : esc_html( $formatted );
	}
}

if ( ! function_exists( 'geshmak_luach_attribution_html' ) ) {
	/**
	 * Small CC BY 4.0 Hebcal credit.
	 *
	 * @param array $data Structured result containing an 'attribution' block.
	 * @param bool  $show
	 * @return string
	 */
	function geshmak_luach_attribution_html( $data, $show = true ) {
		if ( ! $show ) {
			return '';
		}
		$url  = isset( $data['attribution']['url'] ) ? $data['attribution']['url'] : 'https://www.hebcal.com/';
		$text = isset( $data['attribution']['text'] ) ? $data['attribution']['text'] : 'Calendar data by Hebcal.com (CC BY 4.0)';
		return '<small class="geshmak-luach-credit"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $text ) . '</a></small>';
	}
}

if ( ! function_exists( 'geshmak_luach_empty_html' ) ) {
	/**
	 * Empty-state / error message wrapper (never blanks a surface).
	 *
	 * @param array  $data
	 * @param string $fallback
	 * @return string
	 */
	function geshmak_luach_empty_html( $data, $fallback ) {
		$message = ! empty( $data['error'] )
			? __( 'Luach data is temporarily unavailable.', 'geshmak-luach' )
			: $fallback;
		return '<div class="geshmak-luach geshmak-luach-empty"><p>' . esc_html( $message ) . '</p></div>';
	}
}

if ( ! function_exists( 'geshmak_luach_title_html' ) ) {
	/**
	 * Render a transliterated title with optional Hebrew alongside.
	 *
	 * @param array $item    Normalised item (title, hebrew).
	 * @param bool  $show_he
	 * @return string
	 */
	function geshmak_luach_title_html( $item, $show_he = true ) {
		$out = '<span class="geshmak-luach-title">' . esc_html( $item['title'] ) . '</span>';
		if ( $show_he && ! empty( $item['hebrew'] ) && $item['hebrew'] !== $item['title'] ) {
			$out .= ' ' . geshmak_luach_rtl( $item['hebrew'] );
		}
		return $out;
	}
}

// ---------------------------------------------------------------------------
// SURFACE RENDERERS — one per Hebcal family.
// Each accepts the service result ($data) and a display-args array ($a).
// ---------------------------------------------------------------------------

if ( ! function_exists( 'geshmak_luach_render_candles' ) ) {
	function geshmak_luach_render_candles( $data, $a = array() ) {
		geshmak_luach_enqueue_styles();
		$show_he = ! isset( $a['show_hebrew'] ) || geshmak_luach_to_bool( $a['show_hebrew'] );
		$credit  = ! isset( $a['show_credit'] ) || geshmak_luach_to_bool( $a['show_credit'] );

		if ( empty( $data['items'] ) ) {
			return geshmak_luach_empty_html( $data, __( 'No candle-lighting times in range.', 'geshmak-luach' ) );
		}

		$html  = '<div class="geshmak-luach geshmak-luach-candles">';
		$html .= '<ul class="geshmak-luach-list">';
		foreach ( $data['items'] as $item ) {
			$time  = $item['time'] ? $item['time'] : geshmak_luach_format_time( $item['date'] );
			$html .= '<li class="geshmak-luach-item geshmak-luach-' . esc_attr( $item['category'] ) . '">';
			$html .= geshmak_luach_title_html( $item, $show_he );
			if ( $time ) {
				$html .= ' <span class="geshmak-luach-time">' . esc_html( $time ) . '</span>';
			}
			$html .= '</li>';
		}
		$html .= '</ul>';
		$html .= geshmak_luach_attribution_html( $data, $credit );
		$html .= '</div>';
		return $html;
	}
}

if ( ! function_exists( 'geshmak_luach_render_parsha' ) ) {
	function geshmak_luach_render_parsha( $data, $a = array() ) {
		geshmak_luach_enqueue_styles();
		$show_he = ! isset( $a['show_hebrew'] ) || geshmak_luach_to_bool( $a['show_hebrew'] );
		$credit  = ! isset( $a['show_credit'] ) || geshmak_luach_to_bool( $a['show_credit'] );

		if ( empty( $data['items'] ) ) {
			return geshmak_luach_empty_html( $data, __( 'No parsha found in range.', 'geshmak-luach' ) );
		}

		$html = '<div class="geshmak-luach geshmak-luach-parsha">';
		foreach ( $data['items'] as $item ) {
			$html .= '<div class="geshmak-luach-item">';
			$html .= '<span class="geshmak-luach-parsha-name">' . geshmak_luach_title_html( $item, $show_he ) . '</span>';
			if ( ! empty( $item['date'] ) ) {
				$html .= ' <span class="geshmak-luach-date">' . geshmak_luach_format_date( $item['date'] ) . '</span>';
			}
			if ( ! empty( $a['show_leyning'] ) && geshmak_luach_to_bool( $a['show_leyning'] ) && ! empty( $item['leyning']['torah'] ) ) {
				$html .= '<div class="geshmak-luach-leyning-summary"><span class="geshmak-luach-label">' . esc_html__( 'Torah:', 'geshmak-luach' ) . '</span> ' . esc_html( $item['leyning']['torah'] );
				if ( ! empty( $item['leyning']['haftarah'] ) ) {
					$html .= ' &middot; <span class="geshmak-luach-label">' . esc_html__( 'Haftarah:', 'geshmak-luach' ) . '</span> ' . esc_html( $item['leyning']['haftarah'] );
				}
				$html .= '</div>';
			}
			$html .= '</div>';
		}
		$html .= geshmak_luach_attribution_html( $data, $credit );
		$html .= '</div>';
		return $html;
	}
}

if ( ! function_exists( 'geshmak_luach_render_zmanim' ) ) {
	function geshmak_luach_render_zmanim( $data, $a = array() ) {
		geshmak_luach_enqueue_styles();
		$credit = ! isset( $a['show_credit'] ) || geshmak_luach_to_bool( $a['show_credit'] );

		if ( empty( $data['times'] ) ) {
			return geshmak_luach_empty_html( $data, __( 'No zmanim available.', 'geshmak-luach' ) );
		}

		$html = '<div class="geshmak-luach geshmak-luach-zmanim">';
		if ( ! empty( $data['date'] ) ) {
			$html .= '<div class="geshmak-luach-zmanim-date">' . geshmak_luach_format_date( $data['date'] ) . '</div>';
		}
		$html .= '<table class="geshmak-luach-table"><tbody>';
		foreach ( $data['times'] as $zman ) {
			$html .= '<tr><th scope="row">' . esc_html( $zman['label'] ) . '</th><td>' . geshmak_luach_format_time( $zman['time'] ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		$html .= geshmak_luach_attribution_html( $data, $credit );
		$html .= '</div>';
		return $html;
	}
}

if ( ! function_exists( 'geshmak_luach_render_hebrew_date' ) ) {
	function geshmak_luach_render_hebrew_date( $data, $a = array() ) {
		geshmak_luach_enqueue_styles();
		$show_he = ! isset( $a['show_hebrew'] ) || geshmak_luach_to_bool( $a['show_hebrew'] );
		$credit  = isset( $a['show_credit'] ) && geshmak_luach_to_bool( $a['show_credit'] );

		if ( empty( $data['display'] ) && empty( $data['hebrew'] ) ) {
			return geshmak_luach_empty_html( $data, __( 'Date unavailable.', 'geshmak-luach' ) );
		}

		$html  = '<div class="geshmak-luach geshmak-luach-hebrew-date">';
		$html .= '<span class="geshmak-luach-title">' . esc_html( $data['display'] ) . '</span>';
		if ( $show_he && ! empty( $data['hebrew'] ) ) {
			$html .= ' ' . geshmak_luach_rtl( $data['hebrew'] );
		}
		$html .= geshmak_luach_attribution_html( $data, $credit );
		$html .= '</div>';
		return $html;
	}
}

if ( ! function_exists( 'geshmak_luach_render_holidays' ) ) {
	function geshmak_luach_render_holidays( $data, $a = array() ) {
		geshmak_luach_enqueue_styles();
		$show_he = ! isset( $a['show_hebrew'] ) || geshmak_luach_to_bool( $a['show_hebrew'] );
		$credit  = ! isset( $a['show_credit'] ) || geshmak_luach_to_bool( $a['show_credit'] );

		if ( empty( $data['items'] ) ) {
			return geshmak_luach_empty_html( $data, __( 'No holidays found in range.', 'geshmak-luach' ) );
		}

		$html  = '<div class="geshmak-luach geshmak-luach-holidays">';
		$html .= '<ul class="geshmak-luach-list">';
		foreach ( $data['items'] as $item ) {
			$html .= '<li class="geshmak-luach-item">';
			if ( ! empty( $item['date'] ) ) {
				$html .= '<span class="geshmak-luach-date">' . geshmak_luach_format_date( $item['date'] ) . '</span> ';
			}
			$html .= geshmak_luach_title_html( $item, $show_he );
			$html .= '</li>';
		}
		$html .= '</ul>';
		$html .= geshmak_luach_attribution_html( $data, $credit );
		$html .= '</div>';
		return $html;
	}
}

if ( ! function_exists( 'geshmak_luach_render_leyning' ) ) {
	function geshmak_luach_render_leyning( $data, $a = array() ) {
		geshmak_luach_enqueue_styles();
		$show_he = ! isset( $a['show_hebrew'] ) || geshmak_luach_to_bool( $a['show_hebrew'] );
		$credit  = ! isset( $a['show_credit'] ) || geshmak_luach_to_bool( $a['show_credit'] );

		if ( empty( $data['items'] ) ) {
			return geshmak_luach_empty_html( $data, __( 'No Torah reading found in range.', 'geshmak-luach' ) );
		}

		$html = '<div class="geshmak-luach geshmak-luach-leyning">';
		foreach ( $data['items'] as $item ) {
			$html .= '<div class="geshmak-luach-item">';
			$html .= '<div class="geshmak-luach-leyning-name">' . geshmak_luach_title_html( $item, $show_he );
			if ( ! empty( $item['date'] ) ) {
				$html .= ' <span class="geshmak-luach-date">' . geshmak_luach_format_date( $item['date'] ) . '</span>';
			}
			$html .= '</div>';

			if ( ! empty( $item['fullkriyah'] ) && is_array( $item['fullkriyah'] ) ) {
				$html .= '<table class="geshmak-luach-table"><tbody>';
				foreach ( $item['fullkriyah'] as $num => $aliyah ) {
					$ref   = isset( $aliyah['k'], $aliyah['b'], $aliyah['e'] ) ? $aliyah['k'] . ' ' . $aliyah['b'] . '-' . $aliyah['e'] : '';
					$label = is_numeric( $num ) ? sprintf( /* translators: %s: aliyah number */ __( 'Aliyah %s', 'geshmak-luach' ), $num ) : ucfirst( (string) $num );
					$html .= '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $ref ) . '</td></tr>';
				}
				$html .= '</tbody></table>';
			}
			if ( ! empty( $item['haftarah'] ) ) {
				$html .= '<div class="geshmak-luach-haftarah"><span class="geshmak-luach-label">' . esc_html__( 'Haftarah:', 'geshmak-luach' ) . '</span> ' . esc_html( $item['haftarah'] ) . '</div>';
			}
			$html .= '</div>';
		}
		$html .= geshmak_luach_attribution_html( $data, $credit );
		$html .= '</div>';
		return $html;
	}
}

if ( ! function_exists( 'geshmak_luach_render_yahrzeit' ) ) {
	function geshmak_luach_render_yahrzeit( $data, $a = array() ) {
		geshmak_luach_enqueue_styles();
		$show_he = ! isset( $a['show_hebrew'] ) || geshmak_luach_to_bool( $a['show_hebrew'] );
		$credit  = ! isset( $a['show_credit'] ) || geshmak_luach_to_bool( $a['show_credit'] );

		if ( empty( $data['items'] ) ) {
			return geshmak_luach_empty_html( $data, __( 'No dates calculated.', 'geshmak-luach' ) );
		}

		$html  = '<div class="geshmak-luach geshmak-luach-yahrzeit">';
		$html .= '<ul class="geshmak-luach-list">';
		foreach ( $data['items'] as $item ) {
			$html .= '<li class="geshmak-luach-item">';
			if ( ! empty( $item['date'] ) ) {
				$html .= '<span class="geshmak-luach-date">' . geshmak_luach_format_date( $item['date'] ) . '</span> ';
			}
			$html .= geshmak_luach_title_html( $item, $show_he );
			if ( ! empty( $item['hdate'] ) ) {
				$html .= ' <span class="geshmak-luach-hdate">(' . esc_html( $item['hdate'] ) . ')</span>';
			}
			$html .= '</li>';
		}
		$html .= '</ul>';
		$html .= geshmak_luach_attribution_html( $data, $credit );
		$html .= '</div>';
		return $html;
	}
}

if ( ! function_exists( 'geshmak_luach_render_assur' ) ) {
	/**
	 * Render the Assur Melacha (work-forbidden) status.
	 *
	 * @param array $data
	 * @param array $a
	 * @return string
	 */
	function geshmak_luach_render_assur( $data, $a = array() ) {
		geshmak_luach_enqueue_styles();
		$credit = ! isset( $a['show_credit'] ) || geshmak_luach_to_bool( $a['show_credit'] );

		if ( empty( $data['ok'] ) ) {
			return geshmak_luach_empty_html( $data, __( 'Status unavailable.', 'geshmak-luach' ) );
		}

		$assur = ! empty( $data['is_assur'] );
		$label = $assur
			? __( 'Melacha is currently forbidden', 'geshmak-luach' )
			: __( 'Melacha is currently permitted', 'geshmak-luach' );
		$state = $assur ? 'assur' : 'mutar';

		$html  = '<div class="geshmak-luach geshmak-luach-assur geshmak-luach-' . esc_attr( $state ) . '">';
		$html .= '<span class="geshmak-luach-assur-status">' . esc_html( $label ) . '</span>';
		$html .= geshmak_luach_attribution_html( $data, $credit );
		$html .= '</div>';
		return $html;
	}
}
