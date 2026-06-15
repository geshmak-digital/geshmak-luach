<?php
/** בס״ד
 * Plugin Name: Geshmak! - Luach
 * Plugin URI: https://github.com/geshmak-digital/geshmak-luach/
 * Description: Exposes the full Hebcal API suite — Jewish calendar, candle lighting, parsha, zmanim, Hebrew dates, holidays, leyning and yahrzeits — through WordPress shortcodes, Elementor (V3) dynamic tags and Elementor Atomic (V4) widgets. Aggressively cached, transliteration-aware, RTL-ready.
 * Version: 1.0.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Tom Kriha Goldstein > Geshmak! > https://geshmak.com.au/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/geshmak-digital/geshmak-luach/
 * Author URI: https://geshmak.com.au/
 * Text Domain: geshmak-luach
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// PLUGIN UPDATE CHECKER
//
// Pointed at the PUBLIC GitHub repo on branch `main`. Public repositories
// update tokenless — do NOT call setAuthentication() and never commit a PAT.
// ---------------------------------------------------------------------------

require __DIR__ . '/includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$geshmak_luach_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/geshmak-digital/geshmak-luach/',
	__FILE__,
	'geshmak-luach'
);

// Public repo — branch only, no authentication.
$geshmak_luach_update_checker->setBranch( 'main' );

// ---------------------------------------------------------------------------
// PLUGIN CONSTANTS
//
// All constants are prefixed GESHMAK_LUACH_ to prevent name clashes.
// VERSION is read from the plugin header — update the version in one place only.
// ---------------------------------------------------------------------------

define( 'GESHMAK_LUACH_PLUGIN_FILE', __FILE__ );
define( 'GESHMAK_LUACH_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'GESHMAK_LUACH_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// Read version from the plugin header — only one place to update.
$_geshmak_luach_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
define( 'GESHMAK_LUACH_VERSION', $_geshmak_luach_data['Version'] );
unset( $_geshmak_luach_data );

// Option key for the single serialised settings array.
define( 'GESHMAK_LUACH_OPTION', 'geshmak_luach_settings' );

// Transient / cache namespace prefix.
define( 'GESHMAK_LUACH_CACHE_GROUP', 'geshmak_luach' );

// ---------------------------------------------------------------------------
// TRANSLATIONS
// ---------------------------------------------------------------------------

add_action( 'init', function () {
	load_plugin_textdomain( 'geshmak-luach', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// ---------------------------------------------------------------------------
// AUTOLOAD
//
// Recursively requires PHP files in includes/autoload, in natural sort order,
// ensuring predictable loading of modules and their dependencies.
//
// @param string $dir
// @return void
// ---------------------------------------------------------------------------

function autoload_geshmak_luach( $dir ) {

	if ( ! is_dir( $dir ) ) {
		return;
	}

	// Get all items except . and ..
	$items = array_diff( scandir( $dir ), array( '.', '..' ) );

	// Sort to ensure predictable load order (10-core before 20-features, etc.)
	sort( $items, SORT_NATURAL | SORT_FLAG_CASE );

	foreach ( $items as $item ) {

		$path = $dir . DIRECTORY_SEPARATOR . $item;

		// Recurse into subdirectories
		if ( is_dir( $path ) ) {
			autoload_geshmak_luach( $path );
		}

		// Load PHP files only (README.md and other files are ignored)
		elseif ( is_file( $path ) && pathinfo( $path, PATHINFO_EXTENSION ) === 'php' ) {
			require_once $path;
		}
	}
}

// Run it
autoload_geshmak_luach( GESHMAK_LUACH_PLUGIN_PATH . 'includes/autoload' );

// ---------------------------------------------------------------------------
// ACTIVATION / DEACTIVATION
//
// Cron warmer scheduling lives in the warmer module, but we hook the schedule
// clear-down here so deactivation always tidies the WP-Cron event.
// ---------------------------------------------------------------------------

register_activation_hook( __FILE__, function () {
	if ( function_exists( 'geshmak_luach_cron_schedule' ) ) {
		geshmak_luach_cron_schedule();
	}
} );

register_deactivation_hook( __FILE__, function () {
	if ( function_exists( 'geshmak_luach_cron_unschedule' ) ) {
		geshmak_luach_cron_unschedule();
	}
} );
