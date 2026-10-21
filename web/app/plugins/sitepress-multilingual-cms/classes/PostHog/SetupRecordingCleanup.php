<?php

namespace WPML\PostHog;

class SetupRecordingCleanup {

	const STARTED_AT_OPTION = 'wpml_posthog_setup_recording_started_at';
	const SETUP_PAGE        = 'sitepress-multilingual-cms/menu/setup.php';
	const MAX_AGE_FILTER    = 'wpml_posthog_setup_recording_max_age_seconds';

	public static function markStarted() {
		update_option( self::STARTED_AT_OPTION, time(), true );
	}

	public static function clearStarted() {
		update_option( self::STARTED_AT_OPTION, 0, true );
	}

	public static function maybeCleanup() {
		$startedAt = (int) get_option( self::STARTED_AT_OPTION, 0 );
		if ( ! $startedAt ) {
			return;
		}

		if ( ! self::hasSiteKey() || self::isSetupComplete() ) {
			return;
		}

		if ( self::isAjaxRequest() || self::isRestRequest() || self::isCronRequest() ) {
			return;
		}

		if ( self::isSetupPage() && ! self::isStale( $startedAt ) ) {
			return;
		}

		if ( RefreshRecording::forceRefresh( [ 'during_setup' => false ] ) ) {
			self::clearStarted();
		}
	}

	private static function hasSiteKey(): bool {
		return (bool) wpml_get_setting( 'site_key' );
	}

	private static function isSetupComplete(): bool {
		return (bool) wpml_get_setting( 'setup_complete', false );
	}

	private static function isAjaxRequest(): bool {
		return function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	}

	private static function isCronRequest(): bool {
		return function_exists( 'wp_doing_cron' ) ? wp_doing_cron() : ( defined( 'DOING_CRON' ) && DOING_CRON );
	}

	private static function isRestRequest(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	private static function isSetupPage(): bool {
		if ( ! isset( $_GET['page'] ) || ! is_string( $_GET['page'] ) ) {
			return false;
		}

		return self::SETUP_PAGE === sanitize_text_field( wp_unslash( $_GET['page'] ) );
	}

	private static function isStale( $startedAt ): bool {
		$maxAge = (int) apply_filters( self::MAX_AGE_FILTER, HOUR_IN_SECONDS );

		return $maxAge <= 0 || time() - (int) $startedAt > $maxAge;
	}

}
