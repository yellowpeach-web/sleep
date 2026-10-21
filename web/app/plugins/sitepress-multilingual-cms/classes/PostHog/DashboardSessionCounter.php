<?php

namespace WPML\PostHog;

use WPML\Core\Component\PostHog\Application\Service\CountDashboardSessionService;
use WPML\LIB\WP\User;

class DashboardSessionCounter {

	const SESSION_ID_COOKIE = 'wpml_ph_session_id';
	const DASHBOARD_PAGE     = 'tm/menu/main.php';

	public static function maybeCount() {
		if ( ! self::isTranslationDashboard() || ! self::currentUserCanSeeTheDashboard() ) {
			return;
		}

		$sessionId = self::getSessionIdFromCookie();
		if ( '' === $sessionId ) {
			return;
		}

		self::count( $sessionId );
	}

	private static function isTranslationDashboard(): bool {
		if ( ! is_admin() || ! isset( $_GET['page'] ) || ! is_string( $_GET['page'] ) ) {
			return false;
		}

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		if ( self::DASHBOARD_PAGE !== $page ) {
			return false;
		}

		if ( ! isset( $_GET['sm'] ) ) {
			return true;
		}

		if ( ! is_string( $_GET['sm'] ) ) {
			return false;
		}

		return 'dashboard' === sanitize_text_field( wp_unslash( $_GET['sm'] ) );
	}

	private static function currentUserCanSeeTheDashboard(): bool {
		return User::currentUserCan( User::CAP_MANAGE_TRANSLATIONS ) || User::currentUserIsAdmin();
	}

	private static function getSessionIdFromCookie(): string {
		if ( empty( $_COOKIE[ self::SESSION_ID_COOKIE ] ) || ! is_string( $_COOKIE[ self::SESSION_ID_COOKIE ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_COOKIE[ self::SESSION_ID_COOKIE ] ) );
	}

	private static function count( $sessionId ) {
		global $wpml_dic;

		if ( ! $wpml_dic ) {
			return;
		}

		try {
			$service = $wpml_dic->make( CountDashboardSessionService::class );
			$service->count( $sessionId );
		} catch ( \Throwable $e ) {
		}
	}

}
