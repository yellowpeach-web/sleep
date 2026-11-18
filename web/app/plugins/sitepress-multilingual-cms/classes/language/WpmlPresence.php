<?php

namespace WPML\Language;

use WPML\TM\Settings\RequestSettings;

class WpmlPresence {

	private static $byBlog = [];

	private static $tablesMissingByBlog = [];

	private static $tablesSeen = [];

	private static $tablesMissing = [];

	public static function onCurrentBlog(): bool {
		$blogId = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;

		if ( ! isset( self::$byBlog[ $blogId ] ) ) {
			self::$byBlog[ $blogId ] = ! ( function_exists( 'is_multisite' ) && is_multisite() ) || self::isSetUpOnCurrentBlog();
		}

		return self::$byBlog[ $blogId ];
	}

	public static function tablesMissingOnCurrentBlog(): bool {
		global $wpdb;

		if ( ! ( function_exists( 'is_multisite' ) && is_multisite() ) || ! is_object( $wpdb ) ) {
			return false;
		}

		$blogId = (int) get_current_blog_id();
		if ( ! isset( self::$tablesMissingByBlog[ $blogId ] ) ) {
			$missing = false !== get_option( 'icl_sitepress_version', false )
				&& false === self::lookUpLanguagesTable( $wpdb );

			self::$tablesMissingByBlog[ $blogId ] = $missing;
			if ( $missing ) {
				self::$byBlog[ $blogId ] = false;
			}
		}

		return self::$tablesMissingByBlog[ $blogId ];
	}

	public static function recordAfterBlogSwitch( $newBlogId ): void {
		self::$byBlog[ (int) $newBlogId ] = self::isSetUpOnCurrentBlog();
	}

	private static function isSetUpOnCurrentBlog(): bool {
		global $wpdb;

		$hasTable = self::lookUpLanguagesTable( $wpdb );
		if ( null !== $hasTable ) {
			return $hasTable;
		}

		$settings = RequestSettings::load();

		return ! empty( $settings['setup_complete'] );
	}

	public static function hasLanguagesTable( $wpdb ): bool {
		return true === self::lookUpLanguagesTable( $wpdb );
	}

	public static function languagesTableOnPrefix( $wpdb ) {
		return self::lookUpLanguagesTable( $wpdb );
	}

	private static function lookUpLanguagesTable( $wpdb ) {
		$table = $wpdb->prefix . 'icl_languages';
		if ( isset( self::$tablesSeen[ $table ] ) ) {
			return true;
		}
		if ( isset( self::$tablesMissing[ $table ] ) ) {
			return false;
		}

		if ( self::isShardingDropIn( $wpdb ) ) {
			$pattern = str_replace( [ '\\', '_', '%' ], [ '\\\\', '\\_', '\\%' ], $table );
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
		} else {
			$found = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s LIMIT 1',
					$table
				)
			);
		}
		if ( is_string( $found ) && 0 === strcasecmp( $found, $table ) ) {
			self::$tablesSeen[ $table ] = true;

			return true;
		}

		if ( ! empty( $wpdb->last_error ) ) {
			return null;
		}

		self::$tablesMissing[ $table ] = true;

		return false;
	}

	private static function isShardingDropIn( $wpdb ): bool {
		return ( class_exists( 'hyperdb', false ) && is_a( $wpdb, 'hyperdb' ) )
			|| ( class_exists( 'LudicrousDB', false ) && is_a( $wpdb, 'LudicrousDB' ) );
	}

	public static function languagesTableCreated( $table ): void {
		unset( self::$tablesMissing[ (string) $table ] );

		$blogId = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
		if ( isset( self::$byBlog[ $blogId ] ) && ! self::$byBlog[ $blogId ] ) {
			unset( self::$byBlog[ $blogId ] );
		}
		unset( self::$tablesMissingByBlog[ $blogId ] );
	}

	public static function forget(): void {
		self::$byBlog              = [];
		self::$tablesSeen          = [];
		self::$tablesMissing       = [];
		self::$tablesMissingByBlog = [];
	}
}
