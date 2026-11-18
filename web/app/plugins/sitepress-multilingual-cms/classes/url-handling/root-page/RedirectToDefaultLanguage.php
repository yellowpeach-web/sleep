<?php

namespace WPML\UrlHandling\RootPage;

class RedirectToDefaultLanguage {

	const PRIORITY = 9;

	const DEFAULT_STATUS = 301;

	const ALLOWED_STATUSES = [ 301, 302, 303, 307, 308 ];

	const TRACKING_PARAMETERS = [
		'gclid',
		'gad_source',
		'gad_campaignid',
		'gbraid',
		'wbraid',
		'dclid',
		'srsltid',
		'_gl',
		'fbclid',
		'msclkid',
		'ttclid',
		'twclid',
		'li_fat_id',
		'yclid',
		'mc_cid',
		'mc_eid',
	];

	public static function add_hooks() {
		add_action( 'template_redirect', [ self::class, 'maybe_redirect' ], self::PRIORITY );
	}

	public static function maybe_redirect() {
		if ( ! self::is_root_front_page_request() ) {
			return;
		}

		$location = self::get_location();

		if ( null === $location ) {
			add_filter( 'redirect_canonical', [ self::class, 'keep_at_the_root' ] );

			return;
		}

		if ( wp_safe_redirect( $location, self::get_status( $location ), 'WPML' ) ) {
			exit;
		}
	}

	public static function keep_at_the_root() {
		return false;
	}

	public static function get_location() {
		if ( ! self::is_root_front_page_request() ) {
			return null;
		}

		$query = self::get_query_string();
		if ( ! self::has_only_tracking_parameters( $query ) ) {
			return null;
		}

		global $sitepress;

		$home = $sitepress->language_url( $sitepress->get_default_language(), true );
		if ( ! is_string( $home ) || '' === $home ) {
			return null;
		}

		return '' === $query ? $home : $home . ( false === strpos( $home, '?' ) ? '?' : '&' ) . $query;
	}

	public static function get_status( $location ) {
		$status = (int) apply_filters( 'wpml_root_redirect_status', self::DEFAULT_STATUS, $location );

		return in_array( $status, self::ALLOWED_STATUSES, true ) ? $status : self::DEFAULT_STATUS;
	}

	private static function is_root_front_page_request() {
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';

		return \WPML_Root_Page::uses_redirect_root()
			&& in_array( $method, [ 'GET', 'HEAD' ], true )
			&& is_front_page()
			&& ! is_search()
			&& ! is_feed()
			&& ! is_paged()
			&& ! is_preview()
			&& ! is_customize_preview()
			&& ! is_embed()
			&& ! is_robots()
			&& ! is_favicon()
			&& ! is_trackback()
			&& ! is_404()
			&& ! wp_doing_ajax()
			&& ! wpml_is_rest_request()
			&& \WPML_Root_Page::is_current_request_root();
	}

	private static function get_query_string() {
		return isset( $_SERVER['QUERY_STRING'] ) ? wp_sanitize_redirect( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
	}

	private static function has_only_tracking_parameters( $query ) {
		if ( '' === $query ) {
			return true;
		}

		$tracking = (array) apply_filters( 'wpml_root_redirect_tracking_parameters', self::TRACKING_PARAMETERS );

		foreach ( explode( '&', $query ) as $pair ) {
			if ( '' === $pair ) {
				continue;
			}

			$name = urldecode( explode( '=', $pair, 2 )[0] );

			if ( 0 !== strpos( $name, 'utm_' ) && ! in_array( $name, $tracking, true ) ) {
				return false;
			}
		}

		return true;
	}
}
