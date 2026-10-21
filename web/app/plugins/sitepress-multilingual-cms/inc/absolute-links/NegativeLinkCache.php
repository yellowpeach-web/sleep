<?php

namespace WPML\AbsoluteLinks;

class NegativeLinkCache {

	const GROUP = 'wpml_alp_negative';

	private static $hooked = false;

	public static function is_known_miss( $key ) {
		self::init_hooks();

		return false !== wp_cache_get( self::salted( $key ), self::GROUP );
	}

	public static function remember_miss( $key ) {
		self::init_hooks();

		wp_cache_set( self::salted( $key ), 1, self::GROUP, DAY_IN_SECONDS );
	}

	private static function salted( $key ) {
		$version = wp_cache_get( 'version', self::GROUP );
		if ( false === $version ) {
			$version = time();
			wp_cache_set( 'version', $version, self::GROUP );
		}

		return md5( $version . '|' . $key );
	}

	public static function bump_version() {
		wp_cache_set( 'version', time() . wp_rand( 100, 999 ), self::GROUP );
	}

	private static function init_hooks() {
		if ( self::$hooked || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$hooked = true;

		add_action( 'save_post', [ self::class, 'bump_version' ] );
		add_action( 'created_term', [ self::class, 'bump_version' ] );
		add_action( 'edited_term', [ self::class, 'bump_version' ] );
		add_action( 'deleted_post', [ self::class, 'bump_version' ] );
	}
}
