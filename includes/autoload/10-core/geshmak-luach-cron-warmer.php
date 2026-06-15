<?php
/** בס״ד
 * CRON WARMER
 *
 * A daily WP-Cron event that pre-fetches the current and next week for the site's
 * default location, so no visitor ever hits a cold cache. All fetches go through
 * the Hebcal service, which caches them; the warmer simply primes that cache.
 *
 * DEVELOPED BY TOM GOLDSTEIN > GESHMAK! > https://geshmak.com.au/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GESHMAK_LUACH_CRON_HOOK', 'geshmak_luach_daily_warm' );

if ( ! function_exists( 'geshmak_luach_cron_schedule' ) ) {
	/**
	 * Schedule the daily warmer (idempotent). Called on activation and on init.
	 *
	 * @return void
	 */
	function geshmak_luach_cron_schedule() {
		if ( ! wp_next_scheduled( GESHMAK_LUACH_CRON_HOOK ) ) {
			// Run a little after midnight site time.
			$first = strtotime( 'tomorrow 00:30' );
			wp_schedule_event( $first ? $first : time() + HOUR_IN_SECONDS, 'daily', GESHMAK_LUACH_CRON_HOOK );
		}
	}
}

if ( ! function_exists( 'geshmak_luach_cron_unschedule' ) ) {
	/**
	 * Clear the scheduled warmer. Called on deactivation.
	 *
	 * @return void
	 */
	function geshmak_luach_cron_unschedule() {
		$timestamp = wp_next_scheduled( GESHMAK_LUACH_CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, GESHMAK_LUACH_CRON_HOOK );
		}
		wp_clear_scheduled_hook( GESHMAK_LUACH_CRON_HOOK );
	}
}

if ( ! function_exists( 'geshmak_luach_cron_warm' ) ) {
	/**
	 * Warmer callback — primes this week and next week for the default location.
	 *
	 * @return void
	 */
	function geshmak_luach_cron_warm() {

		if ( ! function_exists( 'geshmak_luach_service' ) ) {
			return;
		}

		$service = geshmak_luach_service();

		// Warm the exact default surface calls a visitor hits today (each method now
		// defaults to a forward window from today), so the cache keys line up.
		$service->get_candle_times();
		$service->get_parsha();
		$service->get_parsha( array( 'leyning' => true ) );
		$service->get_holidays();
		$service->get_leyning();

		// Today's zmanim — default subset and the full set.
		$service->get_zmanim();
		$service->get_zmanim( array( 'times' => array( 'all' ) ) );

		// Today's Hebrew date.
		$service->get_hebrew_date();
	}
}

add_action( GESHMAK_LUACH_CRON_HOOK, 'geshmak_luach_cron_warm' );

// Safety net: ensure the event exists even if the activation hook was missed
// (e.g. plugin updated in place). Runs cheaply once scheduled.
add_action( 'init', 'geshmak_luach_cron_schedule' );
