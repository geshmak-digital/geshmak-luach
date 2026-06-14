<?php
/** בס״ד
 * ELEMENTOR (V3) DYNAMIC TAGS
 *
 * Registers Luach dynamic tags so any Heading / Text / Button widget can bind to
 * this week's parsha, candle lighting, today's Hebrew date, the next holiday, a
 * chosen zman, etc. — mirroring the shortcode coverage. Every tag calls the shared
 * Hebcal service. Tag settings allow per-instance location / transliteration override.
 *
 * Output is plain text (dynamic tags feed into widgets that handle their own markup).
 *
 * DEVELOPED BY TOM GOLDSTEIN > GESHMAK! > https://geshmak.com.au/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Plugin' ) ) {
	return;
}

// Tag classes are defined lazily on the registration hook, when Elementor's
// base Tag class is guaranteed to be loaded.
add_action( 'elementor/dynamic_tags/register', 'geshmak_luach_register_dynamic_tags' );

if ( ! function_exists( 'geshmak_luach_register_dynamic_tags' ) ) {
	/**
	 * Define and register the Luach dynamic tags + group.
	 *
	 * @param mixed $tags Elementor dynamic tags manager.
	 * @return void
	 */
	function geshmak_luach_register_dynamic_tags( $tags ) {

		if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {
			return;
		}

		geshmak_luach_define_dynamic_tag_classes();

		// Register the Luach group (literal string key — no Pro-only constants).
		if ( method_exists( $tags, 'register_group' ) ) {
			$tags->register_group( 'geshmak-luach', array( 'title' => __( 'Luach', 'geshmak-luach' ) ) );
		}

		$classes = array(
			'Geshmak_Luach_Tag_Parsha',
			'Geshmak_Luach_Tag_Candles',
			'Geshmak_Luach_Tag_Havdalah',
			'Geshmak_Luach_Tag_Hebrew_Date',
			'Geshmak_Luach_Tag_Next_Holiday',
			'Geshmak_Luach_Tag_Zman',
		);

		foreach ( $classes as $class ) {
			if ( class_exists( $class ) ) {
				if ( method_exists( $tags, 'register' ) ) {
					$tags->register( new $class() );
				} elseif ( method_exists( $tags, 'register_tag' ) ) {
					$tags->register_tag( $class );
				}
			}
		}
	}
}

if ( ! function_exists( 'geshmak_luach_define_dynamic_tag_classes' ) ) {
	/**
	 * Define the Luach dynamic tag classes (guarded against redefinition).
	 *
	 * @return void
	 */
	function geshmak_luach_define_dynamic_tag_classes() {

		if ( class_exists( 'Geshmak_Luach_Tag_Base' ) ) {
			return;
		}

		/**
		 * Shared base for all Luach text tags.
		 */
		abstract class Geshmak_Luach_Tag_Base extends \Elementor\Core\DynamicTags\Tag {

			public function get_group() {
				return 'geshmak-luach';
			}

			public function get_categories() {
				// Literal category keys — avoids Pro-only Module constants.
				return array( 'text' );
			}

			/**
			 * Common location / transliteration override controls.
			 */
			protected function register_controls() {
				$this->add_control(
					'geonameid',
					array(
						'label'       => __( 'GeoNames ID (override)', 'geshmak-luach' ),
						'type'        => \Elementor\Controls_Manager::TEXT,
						'description' => __( 'Leave blank to use the site default location.', 'geshmak-luach' ),
					)
				);
				$this->add_control(
					'translit',
					array(
						'label'   => __( 'Transliteration', 'geshmak-luach' ),
						'type'    => \Elementor\Controls_Manager::SELECT,
						'default' => '',
						'options' => array_merge(
							array( '' => __( 'Site default', 'geshmak-luach' ) ),
							geshmak_luach_translit_schemes()
						),
					)
				);
			}

			/**
			 * Build service args from the shared controls.
			 *
			 * @return array
			 */
			protected function service_args() {
				$settings = $this->get_settings();
				$args     = array();
				if ( ! empty( $settings['geonameid'] ) ) {
					$args['geonameid'] = $settings['geonameid'];
				}
				if ( ! empty( $settings['translit'] ) ) {
					$args['translit'] = $settings['translit'];
				}
				return $args;
			}

			/**
			 * Echo a plain-text value.
			 *
			 * @param string $value
			 * @return void
			 */
			protected function output( $value ) {
				echo esc_html( $value );
			}
		}

		/**
		 * This week's parsha.
		 */
		class Geshmak_Luach_Tag_Parsha extends Geshmak_Luach_Tag_Base {
			public function get_name() {
				return 'geshmak-luach-parsha';
			}
			public function get_title() {
				return __( 'Luach: Parsha', 'geshmak-luach' );
			}
			public function render() {
				$data = geshmak_luach_service()->get_parsha( $this->service_args() );
				if ( ! empty( $data['items'][0]['title'] ) ) {
					$this->output( $data['items'][0]['title'] );
				}
			}
		}

		/**
		 * Next candle lighting time.
		 */
		class Geshmak_Luach_Tag_Candles extends Geshmak_Luach_Tag_Base {
			public function get_name() {
				return 'geshmak-luach-candles';
			}
			public function get_title() {
				return __( 'Luach: Candle Lighting', 'geshmak-luach' );
			}
			public function render() {
				$data = geshmak_luach_service()->get_candle_times( $this->service_args() );
				foreach ( (array) $data['items'] as $item ) {
					if ( 'candles' === $item['category'] ) {
						$this->output( $item['time'] ? $item['time'] : geshmak_luach_format_time( $item['date'] ) );
						return;
					}
				}
			}
		}

		/**
		 * Next havdalah time.
		 */
		class Geshmak_Luach_Tag_Havdalah extends Geshmak_Luach_Tag_Base {
			public function get_name() {
				return 'geshmak-luach-havdalah';
			}
			public function get_title() {
				return __( 'Luach: Havdalah', 'geshmak-luach' );
			}
			public function render() {
				$data = geshmak_luach_service()->get_candle_times( $this->service_args() );
				foreach ( (array) $data['items'] as $item ) {
					if ( 'havdalah' === $item['category'] ) {
						$this->output( $item['time'] ? $item['time'] : geshmak_luach_format_time( $item['date'] ) );
						return;
					}
				}
			}
		}

		/**
		 * Today's Hebrew date.
		 */
		class Geshmak_Luach_Tag_Hebrew_Date extends Geshmak_Luach_Tag_Base {
			public function get_name() {
				return 'geshmak-luach-hebrew-date';
			}
			public function get_title() {
				return __( 'Luach: Hebrew Date (today)', 'geshmak-luach' );
			}
			public function render() {
				$data  = geshmak_luach_service()->get_hebrew_date( $this->service_args() );
				$value = ! empty( $data['display'] ) ? $data['display'] : ( ! empty( $data['hebrew'] ) ? $data['hebrew'] : '' );
				if ( $value ) {
					$this->output( $value );
				}
			}
		}

		/**
		 * Next holiday.
		 */
		class Geshmak_Luach_Tag_Next_Holiday extends Geshmak_Luach_Tag_Base {
			public function get_name() {
				return 'geshmak-luach-next-holiday';
			}
			public function get_title() {
				return __( 'Luach: Next Holiday', 'geshmak-luach' );
			}
			public function render() {
				$args             = $this->service_args();
				$args['upcoming'] = true;
				$args['limit']    = 1;
				$data             = geshmak_luach_service()->get_holidays( $args );
				if ( ! empty( $data['items'][0]['title'] ) ) {
					$this->output( $data['items'][0]['title'] );
				}
			}
		}

		/**
		 * A chosen zman for today.
		 */
		class Geshmak_Luach_Tag_Zman extends Geshmak_Luach_Tag_Base {
			public function get_name() {
				return 'geshmak-luach-zman';
			}
			public function get_title() {
				return __( 'Luach: Zman (today)', 'geshmak-luach' );
			}
			protected function register_controls() {
				parent::register_controls();
				$this->add_control(
					'zman',
					array(
						'label'   => __( 'Which time', 'geshmak-luach' ),
						'type'    => \Elementor\Controls_Manager::SELECT,
						'default' => 'sunset',
						'options' => geshmak_luach_zman_labels(),
					)
				);
			}
			public function render() {
				$settings = $this->get_settings();
				$key      = ! empty( $settings['zman'] ) ? $settings['zman'] : 'sunset';
				$args     = $this->service_args();
				$args['times'] = array( $key );
				$data     = geshmak_luach_service()->get_zmanim( $args );
				if ( ! empty( $data['times'][ $key ]['time'] ) ) {
					$this->output( geshmak_luach_format_time( $data['times'][ $key ]['time'] ) );
				}
			}
		}
	}
}
