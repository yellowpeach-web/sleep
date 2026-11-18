<?php

namespace WPML\Compatibility\Divi\Hooks;

class ThemeBuilderOneList implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const POST_TYPES = [
		'et_theme_builder',
		'et_template',
	];

	const MODE_NOT_TRANSLATABLE = 0;

	const SYNC_OPTION   = 'custom_posts_sync_option';
	const UNLOCK_OPTION = 'custom_posts_unlocked_option';

	private $onSettingsScreen = false;

	public function add_hooks() {
		add_filter( 'pre_wpml_is_translated_post_type', [ $this, 'isTranslatedPostType' ], 10, 2 );
		add_filter( 'get_translatable_documents_all', [ $this, 'removePostTypes' ] );
		add_filter( 'get_display_as_translated_documents', [ $this, 'removePostTypes' ] );
		add_filter( 'wpml_disable_translation_mode_radio', [ $this, 'lockSettingsRow' ], 10, 3 );
		add_filter( 'wpml_unlock_button_title', [ $this, 'lockTitle' ], 10, 2 );
		add_action( 'current_screen', [ $this, 'showRowsAsNotTranslatable' ] );
	}

	public function isTranslatedPostType( $translated, $postType ) {
		return self::isThemeBuilderType( $postType ) ? false : $translated;
	}

	public function removePostTypes( $postTypes ) {
		if ( ! is_array( $postTypes ) || $this->onSettingsScreen ) {
			return $postTypes;
		}

		foreach ( self::POST_TYPES as $postType ) {
			unset( $postTypes[ $postType ] );
		}

		return $postTypes;
	}

	public function lockSettingsRow( $state, $mode, $postType ) {
		if ( ! is_array( $state ) || ! self::isThemeBuilderType( $postType ) ) {
			return $state;
		}

		$state['state'] = true;
		if ( self::MODE_NOT_TRANSLATABLE === (int) $mode ) {
			$state['reason_message'] = self::lockLine();
		}

		return $state;
	}

	public function lockTitle( $title, $radioName ) {
		foreach ( self::POST_TYPES as $postType ) {
			if ( 'icl_sync_custom_posts[' . $postType . ']' === $radioName ) {
				return self::lockLine();
			}
		}

		return $title;
	}

	public function showRowsAsNotTranslatable( $screen ) {
		if ( ! is_object( $screen ) || ! isset( $screen->id ) || false === strpos( (string) $screen->id, 'tm/menu/settings' ) ) {
			return;
		}

		global $sitepress_settings;
		if ( ! is_array( $sitepress_settings ) ) {
			return;
		}

		$this->onSettingsScreen = true;

		foreach ( self::POST_TYPES as $postType ) {
			if ( isset( $sitepress_settings[ self::SYNC_OPTION ][ $postType ] ) ) {
				$sitepress_settings[ self::SYNC_OPTION ][ $postType ] = self::MODE_NOT_TRANSLATABLE;
			}
			if ( isset( $sitepress_settings[ self::UNLOCK_OPTION ][ $postType ] ) ) {
				$sitepress_settings[ self::UNLOCK_OPTION ][ $postType ] = 0;
			}
		}
	}

	public static function isThemeBuilderType( $postType ) {
		return in_array( $postType, self::POST_TYPES, true );
	}

	public static function lockLine() {
		/* translators: Shown on WPML → Settings → Post Types Translation next to the Divi Theme Builder and Divi Templates rows, which stay Not translatable. "Theme Builder" is Divi's feature name. */
		return __( 'Divi uses one Theme Builder for all languages. WPML shows the translated layouts in each language.', 'sitepress' );
	}
}
