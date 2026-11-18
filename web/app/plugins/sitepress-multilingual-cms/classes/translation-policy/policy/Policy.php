<?php

namespace WPML\TranslationPolicy\Policy;

use WPML\TranslationPolicy\FeatureSwitch;
use WPML\TranslationPolicy\TranslateEverything\GateInterface;

class Policy {

	private static $readers = [];

	public static function reader(): PolicyReaderInterface {
		$isOn = FeatureSwitch::isOn();
		$key  = get_current_blog_id() . ( $isOn ? ':on' : ':off' );

		if ( ! isset( self::$readers[ $key ] ) ) {
			global $wpdb;

			self::$readers[ $key ] = $isOn ? new StoredPolicyReader( new PolicySql( $wpdb ) ) : new LegacyPolicyReader();
		}

		return self::$readers[ $key ];
	}

	public static function editorSql(): EditorSqlInterface {
		return self::reader()->editorSql();
	}

	public static function listEditor(): ListEditorInterface {
		return self::reader()->listEditor();
	}

	public static function addHooks() {
		add_action( 'icl_save_settings', [ self::class, 'resetRequestCache' ], 10, 0 );
		add_action( 'update_option_icl_sitepress_settings', [ self::class, 'resetRequestCache' ], 10, 0 );
	}

	public static function editorFilters(): EditorFiltersInterface {
		return self::reader()->editorFilters();
	}

	public static function translateEverythingGate(): GateInterface {
		return self::reader()->translateEverythingGate();
	}

	public static function resetRequestCache() {
		self::$readers = [];
	}

	public static function editor( int $postId ): string {
		return self::reader()->forPost( $postId )->editor();
	}

	public static function isSiteEditorAte(): bool {
		return self::reader()->isSiteEditorAte();
	}

	public static function siteWantsAteAccount(): bool {
		return self::reader()->wantsAteAccount();
	}

	public static function automaticTranslationNeedsAccountOnly(): bool {
		return self::reader()->automaticTranslationNeedsAccountOnly();
	}

	public static function isAccountUsable(): bool {
		return self::reader()->isAccountUsable();
	}

	public static function editorSwitchKeepsTranslations(): bool {
		return self::reader()->editorSwitchKeepsTranslations();
	}

	public static function carriesEditsMadeOutsideWpml(): bool {
		return self::reader()->carriesEditsMadeOutsideWpml();
	}
}
