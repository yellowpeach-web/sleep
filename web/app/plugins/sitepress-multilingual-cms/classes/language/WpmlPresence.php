<?php

namespace WPML\Language;

use WPML\TM\Settings\RequestSettings;

class WpmlPresence {

	private static $byBlog = [];

	private static $tablesSeen = [];

	public static function onCurrentBlog(): bool {
		$blogId = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;

		if ( ! isset( self::$byBlog[ $blogId ] ) ) {
			self::$byBlog[ $blogId ] = ! ( function_exists( 'is_multisite' ) && is_multisite() ) || self::isSetUpOnCurrentBlog();
		}

		return self::$byBlog[ $blogId ];
	}

	public static function recordAfterBlogSwitch( $newBlogId ): void {
		self::$byBlog[ (int) $newBlogId ] = self::isSetUpOnCurrentBlog();
	}

	private static function isSetUpOnCurrentBlog(): bool {
		global $wpdb;

		$settings = RequestSettings::load();

		return ! empty( $settings['setup_complete'] ) || self::hasLanguagesTable( $wpdb );
	}

	public static function hasLanguagesTable( $wpdb ): bool {
		$table = $wpdb->prefix . 'icl_languages';
		if ( isset( self::$tablesSeen[ $table ] ) ) {
			return true;
		}

		$pattern = str_replace( [ '\\', '_', '%' ], [ '\\\\', '\\_', '\\%' ], $table );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
		if ( $found === $table ) {
			self::$tablesSeen[ $table ] = true;

			return true;
		}

		return false;
	}

	public static function forget(): void {
		self::$byBlog     = [];
		self::$tablesSeen = [];
	}
}
