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
use Elementor\Modules\AtomicWidgets\PropTypes\String_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Boolean_Prop_Type;
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
					array( 'show_leyning' => Boolean_Prop_Type::make()->default( false ) )
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
			protected function render(): void {
				$settings = $this->get_atomic_settings();
				$data     = geshmak_luach_service()->get_hebrew_date( $this->service_args( $settings ) );
				$inner    = geshmak_luach_render_hebrew_date( $data, $this->display_args( $settings ) );
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
