<?php

namespace WPML\TM\Settings;

use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;

class SettingsRemoval {

	const OPTION_NAME = 'icl_sitepress_settings';

	private static $removed = array();

	private static $guard_added = false;

	public static function remove( $key, $sub_key = null ) {
		global $sitepress_settings;

		if ( ! self::isRemovable( $key, $sub_key ) ) {
			return false;
		}

		if ( ! is_array( $sitepress_settings ) ) {
			$sitepress_settings = RequestSettings::load();
		}

		$blog_id = self::currentBlogId();
		if ( ! isset( self::$removed[ $blog_id ] ) ) {
			self::$removed[ $blog_id ] = array();
		}

		if ( null === $sub_key ) {
			$was_present = array_key_exists( $key, $sitepress_settings );
			unset( $sitepress_settings[ $key ] );
			self::$removed[ $blog_id ][ $key ] = true;
		} else {
			$was_present = isset( $sitepress_settings[ $key ] )
				&& is_array( $sitepress_settings[ $key ] )
				&& array_key_exists( $sub_key, $sitepress_settings[ $key ] );
			if ( $was_present ) {
				unset( $sitepress_settings[ $key ][ $sub_key ] );
			}
			if ( ! isset( self::$removed[ $blog_id ][ $key ] ) || ! is_array( self::$removed[ $blog_id ][ $key ] ) ) {
				self::$removed[ $blog_id ][ $key ] = array();
			}
			self::$removed[ $blog_id ][ $key ][ $sub_key ] = true;
		}

		self::addGuard();

		$saved = update_option( self::OPTION_NAME, $sitepress_settings );

		do_action( 'icl_save_settings', $sitepress_settings );

		return $saved || ! $was_present;
	}

	public static function stripRemoved( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$blog_id = self::currentBlogId();
		if ( empty( self::$removed[ $blog_id ] ) ) {
			return $value;
		}

		foreach ( self::$removed[ $blog_id ] as $key => $sub_keys ) {
			if ( true === $sub_keys ) {
				unset( $value[ $key ] );
				continue;
			}
			if ( ! isset( $value[ $key ] ) || ! is_array( $value[ $key ] ) ) {
				continue;
			}
			foreach ( array_keys( $sub_keys ) as $sub_key ) {
				unset( $value[ $key ][ $sub_key ] );
			}
		}

		return $value;
	}

	public static function reset() {
		self::$removed     = array();
		self::$guard_added = false;
	}

	private static function currentBlogId() {
		return function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
	}

	private static function isRemovable( $key, $sub_key ) {
		if ( '' === (string) $key ) {
			return false;
		}
		if ( ElementType::SETTINGS_KEY !== $key ) {
			return true;
		}

		return null !== $sub_key && ! in_array( $sub_key, ElementType::BLOB_KEYS, true );
	}

	private static function addGuard() {
		if ( self::$guard_added ) {
			return;
		}
		self::$guard_added = true;
		add_filter( 'pre_update_option_' . self::OPTION_NAME, array( self::class, 'stripRemoved' ) );
	}
}
