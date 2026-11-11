<?php
/** בס״ד
 * ELEMENTOR ATOMIC (V4) WIDGETS
 *
 * High-value Luach display components as native V4 atomic widgets: candle lighting,
 * parsha, Hebrew date, zmanim table and a "today" panel. Each calls the shared
 * Hebcal service and the shared renderers. Adding more widgets later is a one-liner
 * in the $widgets list inside geshmak_luach_register_atomic_widgets().
 *
 * Follows the elementor-atomic-widget skill: define_props_schema / define_atomic_controls
 * / define_base_styles / get_atomic_settings() / single-div root. No V3 wrapper-class CSS.
 *
 * `use` aliases are safe at file level; the CLASS definitions and registration are
 * deferred to `elementor/init`, where the Atomic base class is guaranteed to exist.
 *
 * DEVELOPED BY TOM GOLDSTEIN > GESHMAK! > https://geshmak.com.au/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Classes_Prop_Type;
use Elementor\Modules\AtomicWidgets\Styles\Style_Definition;
use Elementor\Modules\AtomicWidgets\Styles\Style_Variant;
use Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type;

if ( ! class_exists( '\Elementor\Plugin' ) ) {
	return;
}

// Defer everything to elementor/init — the atomic base class only exists when the
// `e_atomic_elements` experiment is active and Elementor is fully loaded.
add_action( 'elementor/init', 'geshmak_luach_define_atomic_widgets' );

if ( ! function_exists( 'geshmak_luach_define_atomic_widgets' ) ) {
	/**
	 * Define the atomic widget classes and hook their registration.
	 *
	 * @return void
	 */
	function geshmak_luach_define_atomic_widgets() {

		// Double-guard: bail unless the atomic base class is present.
		if ( ! class_exists( '\Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base' ) ) {
			return;
		}

		if ( class_exists( 'Geshmak_Luach_Atomic_Base' ) ) {
			return;
		}

		// Primitive prop types moved from PropTypes\ to PropTypes\Primitives\ in
		// Elementor 4.1. The `use` statements above target the 4.1+ location; bridge
		// the alias on older atomic builds so the same code runs on both.
		foreach ( array( 'String_Prop_Type', 'Boolean_Prop_Type', 'Number_Prop_Type' ) as $geshmak_luach_pt ) {
			$primitive = 'Elementor\\Modules\\AtomicWidgets\\PropTypes\\Primitives\\' . $geshmak_luach_pt;
			$legacy    = 'Elementor\\Modules\\AtomicWidgets\\PropTypes\\' . $geshmak_luach_pt;
			if ( ! class_exists( $primitive ) && class_exists( $legacy ) ) {
				class_alias( $legacy, $primitive );
			}
		}
		unset( $geshmak_luach_pt, $primitive, $legacy );

		// -------------------------------------------------------------------
		// SHARED BASE
		// -------------------------------------------------------------------

		/**
		 * Common base for every Luach atomic widget: shared props, controls and
		 * a single-div root renderer carrying the required atomic attributes.
		 */
		abstract class Geshmak_Luach_Atomic_Base extends Atomic_Widget_Base {

			public function get_icon(): string {
				return 'eicon-calendar';
			}

			public function get_keywords(): array {
				return array( 'luach', 'hebcal', 'jewish', 'hebrew', 'zmanim', 'parsha', 'candle' );
			}

			/**
			 * Shared props — always include `classes`.
			 *
			 * @return array
			 */
			protected static function define_props_schema(): array {
				return array(
					'classes'     => Classes_Prop_Type::make()->default( array() ),
					'geonameid'   => String_Prop_Type::make()->default( '' ),
					'translit'    => String_Prop_Type::make()
						->default( '' )
						->enum( array( '', 'ashkenaz', 'sephardi', 'hebrew' ) ),
					'show_hebrew' => Boolean_Prop_Type::make()->default( true ),
					'show_credit' => Boolean_Prop_Type::make()->default( false ),
				);
			}

			/**
			 * Shared "Luach" control section.
			 *
			 * @return array
			 */
			protected function define_atomic_controls(): array {
				return array(
					Section::make()
						->set_label( __( 'Luach', 'geshmak-luach' ) )
						->set_items(
							array(
								Text_Control::bind_to( 'geonameid' )
									->set_label( __( 'GeoNames ID (override)', 'geshmak-luach' ) ),
								Select_Control::bind_to( 'translit' )
									->set_label( __( 'Transliteration', 'geshmak-luach' ) )
									->set_options(
										array(
											array( 'value' => '',         'label' => __( 'Site default', 'geshmak-luach' ) ),
											array( 'value' => 'ashkenaz', 'label' => __( 'Modern Ashkenaz', 'geshmak-luach' ) ),
											array( 'value' => 'sephardi', 'label' => __( 'Sephardi', 'geshmak-luach' ) ),
											array( 'value' => 'hebrew',   'label' => __( 'Hebrew only', 'geshmak-luach' ) ),
										)
									),
								Switch_Control::bind_to( 'show_hebrew' )
									->set_label( __( 'Show Hebrew', 'geshmak-luach' ) ),
								Switch_Control::bind_to( 'show_credit' )
									->set_label( __( 'Show Hebcal credit', 'geshmak-luach' ) ),
							)
						),
				);
			}

			/**
			 * No base styles — all presentation is handled by the namespaced
			 * front-end stylesheet, which keeps these widgets resilient across
			 * fast-moving atomic style-API versions. Spacing/colour remain fully
			 * editable via the standard atomic Style tab.
			 *
			 * @return array
			 */
			protected function define_base_styles(): array {
				return array();
			}

			/**
			 * Build shared Hebcal service args from the resolved settings.
			 *
			 * @param array $settings
			 * @return array
			 */
			protected function service_args( $settings ): array {
				$args = array();
				if ( ! empty( $settings['geonameid'] ) ) {
					$args['geonameid'] = $settings['geonameid'];
				}
				if ( ! empty( $settings['translit'] ) ) {
					$args['translit'] = $settings['translit'];
				}
				return $args;
			}

			/**
			 * Shared display flags from settings.
			 *
			 * @param array $settings
			 * @return array
			 */
			protected function display_args( $settings ): array {
				return array(
					'show_hebrew' => ! empty( $settings['show_hebrew'] ),
					'show_credit' => ! empty( $settings['show_credit'] ),
				);
			}

			/**
			 * Echo the single atomic root div with the required attributes
			 * (id = _cssid, class = resolved classes, data-interaction-id).
			 *
			 * @param array  $settings   Resolved atomic settings.
			 * @param string $inner_html Trusted, already-escaped inner markup.
			 * @param string $extra      Extra class for the widget variant.
			 * @return void
			 */
			protected function render_root( $settings, $inner_html, $extra = '' ): void {
				$cssid   = isset( $settings['_cssid'] ) ? $settings['_cssid'] : '';
				$cssid   = is_array( $cssid ) ? implode( ' ', $cssid ) : (string) $cssid;
				$classes = isset( $settings['classes'] ) ? $settings['classes'] : '';
				$classes = is_array( $classes ) ? implode( ' ', $classes ) : (string) $classes;

				$class_list = trim( 'geshmak-luach-atomic ' . $extra . ' ' . $classes );

				$id_attr = '' !== $cssid ? ' id="' . esc_attr( $cssid ) . '"' : '';

				echo '<div' . $id_attr // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					. ' class="' . esc_attr( $class_list ) . '"'
					. ' data-interaction-id="' . esc_attr( $this->get_id() ) . '">'
					. $inner_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built by escaping renderers.
					. '</div>';
			}
		}

		// -------------------------------------------------------------------
		// CANDLE LIGHTING / HAVDALAH
		// -------------------------------------------------------------------

		class Geshmak_Luach_Atomic_Candles extends Geshmak_Luach_Atomic_Base {
			public static function get_element_type(): string {
				return 'geshmak-luach-candles';
			}
			public function get_title(): string {
				return __( 'Geshmak Luach Candle Lighting', 'geshmak-luach' );
			}
			protected function render(): void {
				$settings = $this->get_atomic_settings();
				$data     = geshmak_luach_service()->get_candle_times( $this->service_args( $settings ) );
				$inner    = geshmak_luach_render_candles( $data, $this->display_args( $settings ) );
				$this->render_root( $settings, $inner, 'geshmak-luach-w-candles' );
			}
		}

		// -------------------------------------------------------------------
		// PARSHA
		// -------------------------------------------------------------------

		class Geshmak_Luach_Atomic_Parsha extends Geshmak_Luach_Atomic_Base {
			public static function get_element_type(): string {
				return 'geshmak-luach-parsha';
			}
			public function get_title(): string {
				return __( 'Geshmak Luach Parsha', 'geshmak-luach' );
			}
			protected static function define_props_schema(): array {
				return array_merge(
					parent::define_props_schema(),
					array(
						'show_leyning' => Boolean_Prop_Type::make()->default( false ),
						'show_special' => Boolean_Prop_Type::make()->default( true ),
					)
				);
			}
			protected function define_atomic_controls(): array {
				$controls   = parent::define_atomic_controls();
				$controls[] = Section::make()
					->set_label( __( 'Parsha', 'geshmak-luach' ) )
					->set_items(
						array(
							Switch_Control::bind_to( 'show_leyning' )
								->set_label( __( 'Show Torah / Haftarah', 'geshmak-luach' ) ),
							Switch_Control::bind_to( 'show_special' )
								->set_label( __( 'Show special Shabbos', 'geshmak-luach' ) ),
						)
					);
				return $controls;
			}
			protected function render(): void {
				$settings        = $this->get_atomic_settings();
				$args            = $this->service_args( $settings );
				$args['leyning'] = ! empty( $settings['show_leyning'] );
				$data            = geshmak_luach_service()->get_parsha( $args );
				$display                 = $this->display_args( $settings );
				$display['show_leyning'] = ! empty( $settings['show_leyning'] );
				$display['show_special'] = ! empty( $settings['show_special'] );
				$inner = geshmak_luach_render_parsha( $data, $display );
				$this->render_root( $settings, $inner, 'geshmak-luach-w-parsha' );
			}
		}

		// -------------------------------------------------------------------
		// HEBREW DATE
		// -------------------------------------------------------------------

		class Geshmak_Luach_Atomic_Hebrew_Date extends Geshmak_Luach_Atomic_Base {
			public static function get_element_type(): string {
				return 'geshmak-luach-hebrew-date';
			}
			public function get_title(): string {
				return __( 'Geshmak Luach Hebrew Date', 'geshmak-luach' );
			}
			protected static function define_props_schema(): array {
				return array_merge(
					parent::define_props_schema(),
					array( 'show_parts' => Boolean_Prop_Type::make()->default( false ) )
				);
			}
			protected function define_atomic_controls(): array {
				$controls   = parent::define_atomic_controls();
				$controls[] = Section::make()
					->set_label( __( 'Hebrew Date', 'geshmak-luach' ) )
					->set_items(
						array(
							Switch_Control::bind_to( 'show_parts' )
								->set_label( __( 'Split day / month / year', 'geshmak-luach' ) ),
						)
					);
				return $controls;
			}
			protected function render(): void {
				$settings = $this->get_atomic_settings();
				$data     = geshmak_luach_service()->get_hebrew_date( $this->service_args( $settings ) );
				$display  = $this->display_args( $settings );
				$display['show_parts'] = ! empty( $settings['show_parts'] );
				$inner    = geshmak_luach_render_hebrew_date( $data, $display );
				$this->render_root( $settings, $inner, 'geshmak-luach-w-hebrew-date' );
			}
		}

		// -------------------------------------------------------------------
		// ZMANIM TABLE
		// -------------------------------------------------------------------

		class Geshmak_Luach_Atomic_Zmanim extends Geshmak_Luach_Atomic_Base {
			public static function get_element_type(): string {
				return 'geshmak-luach-zmanim';
			}
			public function get_title(): string {
				return __( 'Geshmak Luach Zmanim', 'geshmak-luach' );
			}
			protected static function define_props_schema(): array {
				return array_merge(
					parent::define_props_schema(),
					array( 'show_all' => Boolean_Prop_Type::make()->default( false ) )
				);
			}
			protected function define_atomic_controls(): array {
				$controls   = parent::define_atomic_controls();
				$controls[] = Section::make()
					->set_label( __( 'Zmanim', 'geshmak-luach' ) )
					->set_items(
						array(
							Switch_Control::bind_to( 'show_all' )
								->set_label( __( 'Show all available times', 'geshmak-luach' ) ),
						)
					);
				return $controls;
			}
			protected function render(): void {
				$settings = $this->get_atomic_settings();
				$args     = $this->service_args( $settings );
				if ( ! empty( $settings['show_all'] ) ) {
					$args['times'] = array( 'all' );
					$args['all']   = true;
				}
				$data  = geshmak_luach_service()->get_zmanim( $args );
				$inner = geshmak_luach_render_zmanim( $data, $this->display_args( $settings ) );
				$this->render_root( $settings, $inner, 'geshmak-luach-w-zmanim' );
			}
		}

		// -------------------------------------------------------------------
		// TODAY PANEL (composite)
		// -------------------------------------------------------------------

		class Geshmak_Luach_Atomic_Today extends Geshmak_Luach_Atomic_Base {
			public static function get_element_type(): string {
				return 'geshmak-luach-today';
			}
			public function get_title(): string {
				return __( 'Geshmak Luach Today Panel', 'geshmak-luach' );
			}
			protected function render(): void {
				$settings = $this->get_atomic_settings();
				$args     = $this->service_args( $settings );
				$display  = $this->display_args( $settings );
				$service  = geshmak_luach_service();

				geshmak_luach_enqueue_styles();

				$rows = '';

				// Hebrew date.
				$hd = $service->get_hebrew_date( $args );
				if ( ! empty( $hd['display'] ) ) {
					$value = esc_html( $hd['display'] );
					if ( ! empty( $display['show_hebrew'] ) && ! empty( $hd['hebrew'] ) ) {
						$value .= ' ' . geshmak_luach_rtl( $hd['hebrew'] );
					}
					$rows .= '<div class="geshmak-luach-today-row"><span class="geshmak-luach-label">' . esc_html__( 'Today', 'geshmak-luach' ) . '</span><span>' . $value . '</span></div>';
				}

				// Parsha.
				$parsha = $service->get_parsha( $args );
				if ( ! empty( $parsha['items'][0]['title'] ) ) {
					$rows .= '<div class="geshmak-luach-today-row"><span class="geshmak-luach-label">' . esc_html__( 'Parsha', 'geshmak-luach' ) . '</span><span>' . esc_html( $parsha['items'][0]['title'] ) . '</span></div>';
				}

				// Next candle lighting.
				$candles = $service->get_candle_times( $args );
				foreach ( (array) $candles['items'] as $item ) {
					if ( 'candles' === $item['category'] ) {
						$time  = $item['time'] ? $item['time'] : geshmak_luach_format_time( $item['date'] );
						$rows .= '<div class="geshmak-luach-today-row"><span class="geshmak-luach-label">' . esc_html__( 'Candle lighting', 'geshmak-luach' ) . '</span><span>' . esc_html( $time ) . '</span></div>';
						break;
					}
				}

				$inner = '<div class="geshmak-luach geshmak-luach-today">' . $rows;
				$inner .= geshmak_luach_attribution_html( $hd, ! empty( $display['show_credit'] ) );
				$inner .= '</div>';

				$this->render_root( $settings, $inner, 'geshmak-luach-w-today' );
			}
		}

		// -------------------------------------------------------------------
		// LUACH TEXT — one text widget, any calculation, any tag
		// -------------------------------------------------------------------

		/**
		 * A single text element (p / div / h1–h6 / span) that shows one chosen Luach
		 * calculation as plain text, with per-widget overrides for location, schedule,
		 * candle lighting, havdalah, transliteration, date format and time format.
		 * Output carries no markup of its own, so the Style tab (typography, colour,
		 * spacing…) fully controls it.
		 */
		class Geshmak_Luach_Atomic_Text extends Geshmak_Luach_Atomic_Base {

			/** Allowed element tags. */
			const TAGS = array( 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span' );

			public static function get_element_type(): string {
				return 'geshmak-luach-text';
			}

			public function get_title(): string {
				return __( 'Geshmak Luach text', 'geshmak-luach' );
			}

			/**
			 * Menu of calculations: value => label.
			 *
			 * @return array
			 */
			public static function calculations(): array {
				return array(
					'parsha'           => __( 'Parsha', 'geshmak-luach' ),
					'parsha_leyning'   => __( 'Parsha with Torah / Haftarah', 'geshmak-luach' ),
					'special_shabbos'  => __( 'Special Shabbos', 'geshmak-luach' ),
					'candles'          => __( 'Candle lighting time', 'geshmak-luach' ),
					'havdalah'         => __( 'Havdalah time', 'geshmak-luach' ),
					'shabbos_times'    => __( 'Candle lighting and havdalah', 'geshmak-luach' ),
					'hebrew_date'      => __( 'Hebrew date (today)', 'geshmak-luach' ),
					'gregorian_date'   => __( 'Gregorian date (today)', 'geshmak-luach' ),
					'next_holiday'     => __( 'Next holiday', 'geshmak-luach' ),
					'upcoming_holidays' => __( 'Upcoming holidays (next 5)', 'geshmak-luach' ),
					'molad'            => __( 'Next molad', 'geshmak-luach' ),
					'zman'             => __( 'A single zman (choose below)', 'geshmak-luach' ),
					'zmanim'           => __( 'Zmanim (default set)', 'geshmak-luach' ),
					'assur_melacha'    => __( 'Is melacha forbidden now?', 'geshmak-luach' ),
				);
			}

			/**
			 * Turn an associative array into Select_Control option rows.
			 *
			 * @param array $options value => label.
			 * @return array
			 */
			protected static function select_options( $options ): array {
				$rows = array();
				foreach ( $options as $value => $label ) {
					$rows[] = array( 'value' => (string) $value, 'label' => $label );
				}
				return $rows;
			}

			protected static function define_props_schema(): array {
				$parent = parent::define_props_schema();

				return array_merge(
					$parent,
					array(
						'tag'           => String_Prop_Type::make()->default( 'p' )->enum( self::TAGS ),
						'calculation'   => String_Prop_Type::make()->default( 'parsha' )->enum( array_keys( self::calculations() ) ),
						'zman'          => String_Prop_Type::make()->default( 'sunset' )->enum( array_keys( geshmak_luach_zman_labels() ) ),
						'israel'        => String_Prop_Type::make()->default( '' )->enum( array( '', 'diaspora', 'israel' ) ),
						'candle_mins'   => String_Prop_Type::make()->default( '' ),
						'havdalah_mode' => String_Prop_Type::make()->default( '' )->enum( array( '', 'tzeit', 'mins' ) ),
						'havdalah_mins' => String_Prop_Type::make()->default( '' ),
						'date_format'   => String_Prop_Type::make()->default( '' ),
						'time_format'   => String_Prop_Type::make()->default( '' ),
						'prepend'       => String_Prop_Type::make()->default( '' ),
						'append'        => String_Prop_Type::make()->default( '' ),
					)
				);
			}

			protected function define_atomic_controls(): array {
				$tag_options = array();
				foreach ( self::TAGS as $tag ) {
					$tag_options[ $tag ] = strtoupper( $tag );
				}

				$controls = array(
					Section::make()
						->set_label( __( 'Content', 'geshmak-luach' ) )
						->set_items(
							array(
								Select_Control::bind_to( 'tag' )
									->set_label( __( 'HTML tag', 'geshmak-luach' ) )
									->set_options( self::select_options( $tag_options ) ),
								Select_Control::bind_to( 'calculation' )
									->set_label( __( 'Show', 'geshmak-luach' ) )
									->set_options( self::select_options( self::calculations() ) ),
								Select_Control::bind_to( 'zman' )
									->set_label( __( 'Zman (when "A single zman" is chosen)', 'geshmak-luach' ) )
									->set_options( self::select_options( geshmak_luach_zman_labels() ) ),
								Text_Control::bind_to( 'prepend' )
									->set_label( __( 'Text before', 'geshmak-luach' ) ),
								Text_Control::bind_to( 'append' )
									->set_label( __( 'Text after', 'geshmak-luach' ) ),
							)
						),
				);

				// Display options: transliteration, Hebrew, credit.
				$controls[] = Section::make()
					->set_label( __( 'Display options', 'geshmak-luach' ) )
					->set_items(
						array(
							Select_Control::bind_to( 'translit' )
								->set_label( __( 'Transliteration', 'geshmak-luach' ) )
								->set_options(
									self::select_options(
										array(
											''         => __( 'Site default', 'geshmak-luach' ),
											'ashkenaz' => __( 'Modern Ashkenaz', 'geshmak-luach' ),
											'sephardi' => __( 'Sephardi', 'geshmak-luach' ),
											'hebrew'   => __( 'Hebrew only', 'geshmak-luach' ),
										)
									)
								),
							Switch_Control::bind_to( 'show_hebrew' )
								->set_label( __( 'Show Hebrew', 'geshmak-luach' ) ),
							Switch_Control::bind_to( 'show_credit' )
								->set_label( __( 'Show Hebcal credit', 'geshmak-luach' ) ),
						)
					);

				$controls[] = Section::make()
					->set_label( __( 'Luach overrides', 'geshmak-luach' ) )
					->set_items(
						array(
							Text_Control::bind_to( 'geonameid' )
								->set_label( __( 'GeoNames ID (location)', 'geshmak-luach' ) ),
							Select_Control::bind_to( 'israel' )
								->set_label( __( 'Schedule', 'geshmak-luach' ) )
								->set_options(
									self::select_options(
										array(
											''         => __( 'Site default', 'geshmak-luach' ),
											'diaspora' => __( 'Diaspora', 'geshmak-luach' ),
											'israel'   => __( 'Israel', 'geshmak-luach' ),
										)
									)
								),
							Text_Control::bind_to( 'candle_mins' )
								->set_label( __( 'Candle lighting: minutes before sunset', 'geshmak-luach' ) ),
							Select_Control::bind_to( 'havdalah_mode' )
								->set_label( __( 'Havdalah', 'geshmak-luach' ) )
								->set_options(
									self::select_options(
										array(
											''      => __( 'Site default', 'geshmak-luach' ),
											'tzeit' => __( 'Nightfall (3 small stars)', 'geshmak-luach' ),
											'mins'  => __( 'Fixed minutes after sunset', 'geshmak-luach' ),
										)
									)
								),
							Text_Control::bind_to( 'havdalah_mins' )
								->set_label( __( 'Havdalah: minutes after sunset', 'geshmak-luach' ) ),
							Text_Control::bind_to( 'date_format' )
								->set_label( __( 'Date format (PHP, e.g. j F Y)', 'geshmak-luach' ) ),
							Text_Control::bind_to( 'time_format' )
								->set_label( __( 'Time format (PHP, e.g. g:i a)', 'geshmak-luach' ) ),
						)
					);

				return $controls;
			}

			/**
			 * Service args including the schedule / candle / havdalah overrides.
			 *
			 * @param array $settings Resolved settings.
			 * @return array
			 */
			protected function service_args( $settings ): array {
				$args = parent::service_args( $settings );

				if ( ! empty( $settings['israel'] ) ) {
					$args['israel'] = ( 'israel' === $settings['israel'] ) ? 1 : 0;
				}
				if ( isset( $settings['candle_mins'] ) && '' !== trim( (string) $settings['candle_mins'] ) && is_numeric( $settings['candle_mins'] ) ) {
					$args['b'] = absint( $settings['candle_mins'] );
				}
				if ( ! empty( $settings['havdalah_mode'] ) ) {
					$args['havdalah_mode'] = $settings['havdalah_mode'];
				}
				if ( isset( $settings['havdalah_mins'] ) && '' !== trim( (string) $settings['havdalah_mins'] ) && is_numeric( $settings['havdalah_mins'] ) ) {
					$args['havdalah_mins'] = absint( $settings['havdalah_mins'] );
				}
				return $args;
			}

			/**
			 * Hebrew run, bidi-isolated, no styling.
			 *
			 * @param string $hebrew Hebrew text.
			 * @return string Escaped HTML.
			 */
			protected function hebrew_run( $hebrew ): string {
				$hebrew = trim( (string) $hebrew );
				return '' === $hebrew ? '' : '<bdi dir="rtl" lang="he">' . esc_html( $hebrew ) . '</bdi>';
			}

			/**
			 * "Title Hebrew" for a normalised item.
			 *
			 * @param array $item      Item with title / hebrew.
			 * @param bool  $show_he   Include the Hebrew.
			 * @return string Escaped HTML.
			 */
			protected function item_text( $item, $show_he ): string {
				if ( empty( $item['title'] ) ) {
					return '';
				}
				$out = esc_html( $item['title'] );
				if ( $show_he && ! empty( $item['hebrew'] ) && $item['hebrew'] !== $item['title'] ) {
					$out .= ' ' . $this->hebrew_run( $item['hebrew'] );
				}
				return $out;
			}

			/**
			 * Flatten a structured renderer's markup to plain inline text.
			 *
			 * @param string $html Renderer output.
			 * @return string
			 */
			protected function flatten( $html ): string {
				return function_exists( 'geshmak_luach_plain_text' ) ? geshmak_luach_plain_text( $html ) : wp_strip_all_tags( $html );
			}

			/**
			 * Build the text (escaped HTML) for the chosen calculation.
			 *
			 * @param array $settings Resolved settings.
			 * @return string
			 */
			protected function build_text( $settings ): string {
				$service = geshmak_luach_service();
				$args    = $this->service_args( $settings );
				$show_he = ! empty( $settings['show_hebrew'] );
				$display = array(
					'show_hebrew' => $show_he,
					'show_credit' => false, // credit (if wanted) is appended once, in render().
				);
				$calc    = isset( $settings['calculation'] ) ? $settings['calculation'] : 'parsha';

				switch ( $calc ) {

					case 'parsha':
						$data = $service->get_parsha( $args );
						return empty( $data['items'][0] ) ? '' : $this->item_text( $data['items'][0], $show_he );

					case 'parsha_leyning':
						$args['leyning'] = true;
						$data            = $service->get_parsha( $args );
						return $this->flatten( geshmak_luach_render_parsha( $data, array_merge( $display, array( 'show_leyning' => true, 'show_special' => false ) ) ) );

					case 'special_shabbos':
						$data = $service->get_parsha( $args );
						return empty( $data['items'][0]['special'] ) ? '' : $this->item_text( $data['items'][0]['special'], $show_he );

					case 'candles':
					case 'havdalah':
						$data = $service->get_candle_times( $args );
						foreach ( (array) ( isset( $data['items'] ) ? $data['items'] : array() ) as $item ) {
							if ( $calc === $item['category'] ) {
								return $item['time'] ? esc_html( $item['time'] ) : geshmak_luach_format_time( $item['date'] );
							}
						}
						return '';

					case 'shabbos_times':
						return $this->flatten( geshmak_luach_render_candles( $service->get_candle_times( $args ), $display ) );

					case 'hebrew_date':
						$data = $service->get_hebrew_date( $args );
						if ( empty( $data['display'] ) && empty( $data['hebrew'] ) ) {
							return '';
						}
						$out = ! empty( $data['display'] ) ? esc_html( $data['display'] ) : '';
						if ( $show_he && ! empty( $data['hebrew'] ) ) {
							$out .= ' ' . $this->hebrew_run( $data['hebrew'] );
						}
						return trim( $out );

					case 'gregorian_date':
						return geshmak_luach_format_date( current_time( 'Y-m-d' ) );

					case 'next_holiday':
						$args['upcoming'] = true;
						$args['limit']    = 1;
						$data             = $service->get_holidays( $args );
						return empty( $data['items'][0] ) ? '' : $this->item_text( $data['items'][0], $show_he );

					case 'upcoming_holidays':
						$args['upcoming'] = true;
						$args['limit']    = 5;
						return $this->flatten( geshmak_luach_render_holidays( $service->get_holidays( $args ), $display ) );

					case 'molad':
						$args['molad']    = true;
						$args['upcoming'] = true;
						$args['limit']    = 1;
						$data             = $service->get_holidays( $args );
						return empty( $data['items'][0]['title'] ) ? '' : esc_html( $data['items'][0]['title'] );

					case 'zman':
						$key           = ! empty( $settings['zman'] ) ? $settings['zman'] : 'sunset';
						$args['times'] = array( $key );
						$data          = $service->get_zmanim( $args );
						return empty( $data['times'][ $key ]['time'] ) ? '' : geshmak_luach_format_time( $data['times'][ $key ]['time'] );

					case 'zmanim':
						return $this->flatten( geshmak_luach_render_zmanim( $service->get_zmanim( $args ), $display ) );

					case 'assur_melacha':
						return $this->flatten( geshmak_luach_render_assur( $service->get_assur_melacha( $args ), $display ) );
				}

				return '';
			}

			protected function render(): void {
				$settings = $this->get_atomic_settings();

				// Per-widget date / time format, applied only while this widget renders.
				geshmak_luach_format_overrides(
					array(
						'date' => isset( $settings['date_format'] ) ? sanitize_text_field( $settings['date_format'] ) : '',
						'time' => isset( $settings['time_format'] ) ? sanitize_text_field( $settings['time_format'] ) : '',
					)
				);
				try {
					$text = $this->build_text( $settings );
				} catch ( \Throwable $e ) {
					$text = '';
				}
				geshmak_luach_format_overrides( array( 'date' => '', 'time' => '' ) );

				// Text before / after — only when there is something to show, so a
				// bare "Today's daf is " never appears on its own. Spaces are kept
				// exactly as typed.
				if ( '' !== $text ) {
					$before = isset( $settings['prepend'] ) ? (string) $settings['prepend'] : '';
					$after  = isset( $settings['append'] ) ? (string) $settings['append'] : '';
					$text   = esc_html( $before ) . $text . esc_html( $after );
				}

				if ( ! empty( $settings['show_credit'] ) && '' !== $text ) {
					$text .= ' ' . geshmak_luach_attribution_html( array(), true );
				}

				$tag = isset( $settings['tag'] ) ? strtolower( (string) $settings['tag'] ) : 'p';
				$tag = in_array( $tag, self::TAGS, true ) ? $tag : 'p';

				$cssid   = isset( $settings['_cssid'] ) ? $settings['_cssid'] : '';
				$cssid   = is_array( $cssid ) ? implode( ' ', $cssid ) : (string) $cssid;
				$classes = isset( $settings['classes'] ) ? $settings['classes'] : '';
				$classes = is_array( $classes ) ? implode( ' ', $classes ) : (string) $classes;
				$id_attr = '' !== $cssid ? ' id="' . esc_attr( $cssid ) . '"' : '';

				echo '<' . $tag . $id_attr // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — $tag is whitelisted.
					. ' class="' . esc_attr( trim( 'geshmak-luach-text ' . $classes ) ) . '"'
					. ' data-interaction-id="' . esc_attr( $this->get_id() ) . '">'
					. $text // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaping helpers.
					. '</' . $tag . '>';
			}
		}

		// Register on the widgets hook (fires after elementor/init).
		add_action( 'elementor/widgets/register', 'geshmak_luach_register_atomic_widgets' );
	}
}

if ( ! function_exists( 'geshmak_luach_register_atomic_widgets' ) ) {
	/**
	 * Register every Luach atomic widget. Add new widget classes here.
	 *
	 * @param mixed $widgets_manager Elementor Widgets_Manager.
	 * @return void
	 */
	function geshmak_luach_register_atomic_widgets( $widgets_manager ) {

		if ( ! class_exists( 'Geshmak_Luach_Atomic_Base' ) ) {
			return;
		}

		$widgets = array(
			'Geshmak_Luach_Atomic_Candles',
			'Geshmak_Luach_Atomic_Parsha',
			'Geshmak_Luach_Atomic_Hebrew_Date',
			'Geshmak_Luach_Atomic_Zmanim',
			'Geshmak_Luach_Atomic_Today',
			'Geshmak_Luach_Atomic_Text',
		);

		foreach ( $widgets as $widget ) {
			if ( ! class_exists( $widget ) ) {
				continue;
			}

			// Smoke-test the editor-facing API before registering. If the installed
			// Elementor atomic version can't build this widget's schema/controls,
			// skip it gracefully instead of letting the editor fatal. This converts
			// any atomic-API drift into "widget absent" rather than a broken editor.
			try {
				$instance = new $widget();
				$widget::get_props_schema();
				$instance->get_atomic_controls();
				$widgets_manager->register( $instance );
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Geshmak Luach] Atomic widget ' . $widget . ' skipped — incompatible Elementor atomic API: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
				}
			}
		}
	}
}
