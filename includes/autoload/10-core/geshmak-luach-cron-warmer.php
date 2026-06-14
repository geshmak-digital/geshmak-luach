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

		$today      = current_time( 'Y-m-d' );
		$next_week  = gmdate( 'Y-m-d', strtotime( $today . ' +13 days' ) );

		// Calendar window covering this week + next week (candles, parsha, holidays).
		$window = array(
			'start' => $today,
			'end'   => $next_week,
		);

		$service->get_candle_times( $window );
		$service->get_parsha( array_merge( $window, array( 'leyning' => true ) ) );
		$service->get_holidays( $window );

		// Zmanim for each of the next 14 days at the default location.
		for ( $i = 0; $i < 14; $i++ ) {
			$date = gmdate( 'Y-m-d', strtotime( $today . ' +' . $i . ' days' ) );
			$service->get_zmanim( array( 'date' => $date, 'times' => array( 'all' ) ) );
		}

		// Today's Hebrew date.
		$service->get_hebrew_date( array( 'date' => $today ) );
	}
}

add_action( GESHMAK_LUACH_CRON_HOOK, 'geshmak_luach_cron_warm' );

// Safety net: ensure the event exists even if the activation hook was missed
// (e.g. plugin updated in place). Runs cheaply once scheduled.
add_action( 'init', 'geshmak_luach_cron_schedule' );
