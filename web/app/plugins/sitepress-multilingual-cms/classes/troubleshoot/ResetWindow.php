<?php

namespace WPML\Troubleshooting;

class ResetWindow {

	const OPTION = 'wpml_reset_in_progress';

	const MAX_AGE = 900;

	const DRAIN_SECONDS = 3;

	const SUPPORT_PAGE = '/menu/support.php';
	const RESET_TOOL   = 'reset';

	public static function open() {
		update_option(
			self::OPTION,
			[
				'started' => time(),
				'blog'    => (int) get_current_blog_id(),
			],
			false
		);
	}

	public static function drain() {
		$seconds = apply_filters( 'wpml_reset_window_drain_seconds', self::DRAIN_SECONDS );
		$seconds = is_numeric( $seconds ) ? max( 0.0, min( 30.0, (float) $seconds ) ) : (float) self::DRAIN_SECONDS;
		if ( $seconds > 0 ) {
			usleep( (int) round( $seconds * 1000000 ) );
		}
	}

	public static function close() {
		delete_option( self::OPTION );
	}

	public static function primeBootOptions() {
		if ( function_exists( 'wp_prime_option_caches' ) ) {
			wp_prime_option_caches( [ '_wpml_inactive', self::OPTION ] );
		}
	}

	public static function isOpen() {
		return self::isFresh( get_option( self::OPTION, false ) );
	}

	public static function isOpenNow() {
		wp_cache_delete( self::OPTION, 'options' );
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ self::OPTION ] ) ) {
			unset( $notoptions[ self::OPTION ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}

		return self::isOpen();
	}

	public static function isResetPage( ?array $get = null ) {
		if ( null === $get ) {
			$get = [
				'page' => filter_input( INPUT_GET, 'page' ),
				'tool' => filter_input( INPUT_GET, 'tool' ),
			];
		}
		$page = isset( $get['page'] ) && is_string( $get['page'] ) ? $get['page'] : '';
		$tool = isset( $get['tool'] ) && is_string( $get['tool'] ) ? $get['tool'] : '';

		return defined( 'WPML_PLUGIN_FOLDER' )
			&& WPML_PLUGIN_FOLDER . self::SUPPORT_PAGE === $page
			&& self::RESET_TOOL === $tool;
	}

	private static function isFresh( $marker ) {
		if ( false === $marker ) {
			return false;
		}

		$started = is_array( $marker ) && isset( $marker['started'] ) && is_numeric( $marker['started'] )
			? (int) $marker['started']
			: null;
		if ( null !== $started && abs( time() - $started ) < self::MAX_AGE ) {
			return true;
		}

		self::close();

		return false;
	}
}
