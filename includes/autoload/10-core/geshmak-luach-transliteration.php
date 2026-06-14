<?php
/** בס״ד
 * TRANSLITERATION REMAP
 *
 * Hebcal returns Sephardi romanisation ("Shavuot", "Parashat Bereshit"). This layer
 * remaps it to the scheme chosen in settings — Modern Ashkenaz by default
 * ("Shavuos", "Parshas Bereshis") — while always preserving the original Hebrew.
 *
 * Schemes:
 *   - sephardi : passthrough (Hebcal default).
 *   - ashkenaz : Modern Ashkenaz remap (default).
 *   - hebrew   : show the Hebrew string only.
 *
 * The mapping dictionary is filterable via `geshmak_luach_translit_map`.
 *
 * DEVELOPED BY TOM GOLDSTEIN > GESHMAK! > https://geshmak.com.au/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'geshmak_luach_translit_map' ) ) {
	/**
	 * Sephardi → Modern Ashkenaz term dictionary.
	 *
	 * Ordered longest-first within groups so multi-word phrases match before
	 * their constituent words. Matching is whole-word and case-insensitive.
	 *
	 * @return array
	 */
	function geshmak_luach_translit_map() {

		$map = array(

			// --- Leyning / reading labels ------------------------------------
			'Parashat'        => 'Parshas',
			'Parashah'        => 'Parsha',

			// --- Festivals & fasts -------------------------------------------
			'Shabbat'         => 'Shabbos',
			'Sukkot'          => 'Sukkos',
			'Shavuot'         => 'Shavuos',
			'Shmini Atzeret'  => 'Shemini Atzeres',
			'Shemini Atzeret' => 'Shemini Atzeres',
			'Simchat Torah'   => 'Simchas Torah',
			'Rosh Hashana'    => 'Rosh Hashanah',
			'Shabbat Shuva'   => 'Shabbos Shuvah',
			'Tu BiShvat'      => 'Tu BiShvat',
			"Asara B'Tevet"   => "Asara B'Teves",
			"Tzom Tevet"      => 'Tzom Teves',
			'Tzom Gedaliah'   => 'Tzom Gedalia',
			'Sigd'            => 'Sigd',
			'Lag BaOmer'      => 'Lag BaOmer',

			// --- Hebrew months ------------------------------------------------
			'Tevet'           => 'Teves',
			'Shvat'           => 'Shevat',
			'Sivan'           => 'Sivan',

			// --- Parshiyos (Sephardi → Ashkenaz, those that differ) ----------
			'Bereshit'        => 'Bereshis',
			'Toldot'          => 'Toldos',
			'Shemot'          => 'Shemos',
			'Yitro'           => 'Yisro',
			'Mishpatim'       => 'Mishpatim',
			'Ki Tisa'         => 'Ki Sisa',
			'Acharei Mot'     => 'Acharei Mos',
			'Bechukotai'      => 'Bechukosai',
			'Chukat'          => 'Chukas',
			'Matot'           => 'Matos',
			'Ki Teitzei'      => 'Ki Seitzei',
			'Ki Tavo'         => 'Ki Savo',
			'Vezot Haberakhah'=> 'Vezos Haberachah',
			'Vezot Habracha'  => 'Vezos Haberachah',
		);

		return apply_filters( 'geshmak_luach_translit_map', $map );
	}
}

if ( ! function_exists( 'geshmak_luach_transliterate' ) ) {
	/**
	 * Remap a Hebcal romanised string to the chosen scheme.
	 *
	 * @param string $string Hebcal's (Sephardi) romanisation.
	 * @param string $scheme 'sephardi' | 'ashkenaz' | 'hebrew'.
	 * @param string $hebrew Optional original Hebrew string (used by the 'hebrew' scheme).
	 * @return string
	 */
	function geshmak_luach_transliterate( $string, $scheme = 'ashkenaz', $hebrew = '' ) {

		$string = (string) $string;

		if ( 'hebrew' === $scheme ) {
			return '' !== (string) $hebrew ? $hebrew : $string;
		}

		if ( 'ashkenaz' !== $scheme ) {
			// Sephardi (or unknown) → passthrough.
			return $string;
		}

		if ( '' === $string ) {
			return $string;
		}

		$map = geshmak_luach_translit_map();

		// Longest keys first so multi-word phrases win over their parts.
		$keys = array_keys( $map );
		usort( $keys, function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		} );

		foreach ( $keys as $from ) {
			$to     = $map[ $from ];
			$string = preg_replace(
				'/\b' . preg_quote( $from, '/' ) . '\b/u',
				$to,
				$string
			);
		}

		return $string;
	}
}

if ( ! function_exists( 'geshmak_luach_rtl' ) ) {
	/**
	 * Wrap a Hebrew string for correct RTL display.
	 *
	 * @param string $hebrew
	 * @return string Escaped, RTL-wrapped HTML (empty string when no input).
	 */
	function geshmak_luach_rtl( $hebrew ) {
		$hebrew = trim( (string) $hebrew );
		if ( '' === $hebrew ) {
			return '';
		}
		return '<span dir="rtl" lang="he" class="geshmak-luach-he">' . esc_html( $hebrew ) . '</span>';
	}
}

if ( ! function_exists( 'geshmak_luach_translit_schemes' ) ) {
	/**
	 * Available transliteration schemes (for the settings dropdown).
	 *
	 * @return array
	 */
	function geshmak_luach_translit_schemes() {
		return array(
			'ashkenaz' => __( 'Modern Ashkenaz (Shavuos, Parshas)', 'geshmak-luach' ),
			'sephardi' => __( 'Sephardi (Hebcal default — Shavuot, Parashat)', 'geshmak-luach' ),
			'hebrew'   => __( 'Hebrew only', 'geshmak-luach' ),
		);
	}
}

if ( ! function_exists( 'geshmak_luach_zman_labels' ) ) {
	/**
	 * Human-readable labels for every zman key Hebcal returns.
	 *
	 * @return array key => label
	 */
	function geshmak_luach_zman_labels() {
		return apply_filters(
			'geshmak_luach_zman_labels',
			array(
				'chatzotNight'    => __( 'Midnight (Chatzos)', 'geshmak-luach' ),
				'alotHaShachar'   => __( 'Dawn (Alos Hashachar)', 'geshmak-luach' ),
				'misheyakir'      => __( 'Earliest Tallis (Misheyakir)', 'geshmak-luach' ),
				'misheyakirMachmir' => __( 'Earliest Tallis — stringent', 'geshmak-luach' ),
				'dawn'            => __( 'Civil Dawn', 'geshmak-luach' ),
				'sunrise'         => __( 'Sunrise (Neitz Hachamah)', 'geshmak-luach' ),
				'sofZmanShmaMGA'  => __( 'Latest Shema (MG"A)', 'geshmak-luach' ),
				'sofZmanShma'     => __( 'Latest Shema (Gr"a)', 'geshmak-luach' ),
				'sofZmanTfillaMGA'=> __( 'Latest Shacharis (MG"A)', 'geshmak-luach' ),
				'sofZmanTfilla'   => __( 'Latest Shacharis (Gr"a)', 'geshmak-luach' ),
				'chatzot'         => __( 'Midday (Chatzos)', 'geshmak-luach' ),
				'minchaGedola'    => __( 'Earliest Mincha (Mincha Gedola)', 'geshmak-luach' ),
				'minchaGedolaMGA' => __( 'Earliest Mincha (MG"A)', 'geshmak-luach' ),
				'minchaKetana'    => __( 'Mincha Ketana', 'geshmak-luach' ),
				'minchaKetanaMGA' => __( 'Mincha Ketana (MG"A)', 'geshmak-luach' ),
				'plagHaMincha'    => __( 'Plag Hamincha', 'geshmak-luach' ),
				'sunset'          => __( 'Sunset (Shkiah)', 'geshmak-luach' ),
				'dusk'            => __( 'Civil Dusk', 'geshmak-luach' ),
				'beinHaShmashos'  => __( 'Bein Hashmashos', 'geshmak-luach' ),
				'tzeit7083deg'    => __( 'Nightfall (Tzeis 7.083°)', 'geshmak-luach' ),
				'tzeit85deg'      => __( 'Nightfall (Tzeis 8.5°)', 'geshmak-luach' ),
				'tzeit42min'      => __( 'Nightfall (Tzeis 42 min)', 'geshmak-luach' ),
				'tzeit50min'      => __( 'Nightfall (Tzeis 50 min)', 'geshmak-luach' ),
				'tzeit72min'      => __( 'Rabbeinu Tam (Tzeis 72 min)', 'geshmak-luach' ),
			)
		);
	}
}

if ( ! function_exists( 'geshmak_luach_zman_label' ) ) {
	/**
	 * Label for a single zman key (falls back to a humanised key).
	 *
	 * @param string $key
	 * @return string
	 */
	function geshmak_luach_zman_label( $key ) {
		$labels = geshmak_luach_zman_labels();
		if ( isset( $labels[ $key ] ) ) {
			return $labels[ $key ];
		}
		// Humanise camelCase fallback.
		$human = preg_replace( '/(?<!^)([A-Z])/', ' $1', $key );
		return ucfirst( $human );
	}
}
