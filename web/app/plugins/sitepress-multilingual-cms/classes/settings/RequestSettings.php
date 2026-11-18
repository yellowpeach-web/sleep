<?php

namespace WPML\TM\Settings;

use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\EarlyReadBoundary;

class RequestSettings {

	private static $switchHydratedBlogId = null;

	public static function load(): array {
		global $sitepress_settings;
		if ( ! is_array( $sitepress_settings ) ) {
			self::$switchHydratedBlogId = null;
			$sitepress_settings = EarlyReadBoundary::suspendDuring(
				function () {
					$settings = \WPML_Settings_Failsafe_Loader::load();

					return is_array( $settings ) ? $settings : [];
				}
			);
		}

		return $sitepress_settings;
	}

	public static function reload(): array {
		global $sitepress_settings;
		\WPML_Settings_Failsafe_Loader::forgetKeptSettings();
		$sitepress_settings = null;

		return self::load();
	}

	public static function reloadAfterBlogSwitch( $newBlogId = null, $prevBlogId = null ) {
		global $sitepress_settings;

		if (
			null !== $newBlogId
			&& null !== $prevBlogId
			&& (int) $newBlogId === (int) $prevBlogId
			&& is_array( $sitepress_settings )
		) {
			self::$switchHydratedBlogId = (int) $newBlogId;

			return;
		}

		$sitepress_settings = null;
		self::load();
		self::$switchHydratedBlogId = null === $newBlogId ? null : (int) $newBlogId;
	}

	public static function loadForBlog( int $blogId ): array {
		global $sitepress_settings;
		if ( is_array( $sitepress_settings ) && self::$switchHydratedBlogId === $blogId ) {
			return $sitepress_settings;
		}

		return self::reload();
	}

	public static function get( string $key, $fallback = null ) {
		if ( 'translation-management' === $key ) {
			return self::tmSettings() ?? $fallback;
		}

		return self::load()[ $key ] ?? $fallback;
	}

	public static function tmSettings(): ?array {
		$raw = self::load()['translation-management'] ?? null;

		return is_array( $raw ) ? PreferenceResolver::composeTmSettings( $raw ) : null;
	}

	public static function tmSubSetting( string $key, $fallback = [] ) {
		$settings = self::load()['translation-management'] ?? null;

		return is_array( $settings ) ? ( $settings[ $key ] ?? $fallback ) : $fallback;
	}

	public static function tmNotificationSettings(): array {
		return \WPML_TM_Default_Settings::apply_notification_defaults(
			[ 'notification' => self::tmSubSetting( 'notification' ) ]
		)['notification'];
	}
}
