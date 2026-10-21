<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\FieldGroup;
use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;

use function WPML\FP\spreadArgs;

class CptLockHooks implements \IWPML_Backend_Action {

	const SETTINGS_OPTION = 'icl_sitepress_settings';

	const INJECTION_PRIORITY = 20;

	const LOCKED_RADIO_NAME = 'icl_sync_custom_posts[' . FieldGroup::CPT . ']';

	private $hasInjectedLock = false;

	public function add_hooks() {
		Hooks::onFilter( 'option_' . self::SETTINGS_OPTION, self::INJECTION_PRIORITY )
			->then( spreadArgs( [ $this, 'injectFieldGroupCptLock' ] ) );

		Hooks::onFilter( 'wpml_unlock_button_title', 10, 2 )
			->then( spreadArgs( [ $this, 'sayWhatLockedTheFieldGroupsRow' ] ) );

		Hooks::onAction( 'current_screen' )
			->then( [ $this, 'mirrorLockIntoTranslationManagementCopyOnSettingsScreen' ] );
	}

	public function mirrorLockIntoTranslationManagementCopyOnSettingsScreen() {
		if ( ! $this->isWpmlSettingsScreen() ) {
			return;
		}

		$settings = get_option( self::SETTINGS_OPTION );
		$lock     = Obj::path(
			[ 'translation-management', 'custom-types_readonly_config', FieldGroup::CPT ],
			is_array( $settings ) ? $settings : []
		);
		if ( null === $lock ) {
			return;
		}

		wpml_load_core_tm()->settings['custom-types_readonly_config'][ FieldGroup::CPT ] = $lock;
	}

	private function isWpmlSettingsScreen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return (bool) $screen && false !== strpos( (string) $screen->id, 'tm/menu/settings' );
	}

	public function injectFieldGroupCptLock( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		$isUnlocked        = (bool) Obj::path( [ 'custom_posts_unlocked_option', FieldGroup::CPT ], $settings );
		$isNotTranslatable = WPML_CONTENT_TYPE_DONT_TRANSLATE === (int) Obj::pathOr(
			WPML_CONTENT_TYPE_DONT_TRANSLATE,
			[ 'custom_posts_sync_option', FieldGroup::CPT ],
			$settings
		);

		$alreadyLocked = null !== Obj::path(
			[ 'translation-management', 'custom-types_readonly_config', FieldGroup::CPT ],
			$settings
		);

		if ( ! $isUnlocked && $isNotTranslatable ) {
			$settings['translation-management']['custom-types_readonly_config'][ FieldGroup::CPT ] = WPML_CONTENT_TYPE_DONT_TRANSLATE;

			$this->hasInjectedLock = $this->hasInjectedLock || ! $alreadyLocked;
		}

		return $settings;
	}

	public function sayWhatLockedTheFieldGroupsRow( $title, $radioName ) {
		if ( ! $this->hasInjectedLock || self::LOCKED_RADIO_NAME !== $radioName ) {
			return $title;
		}

		return self::getLockTitle();
	}

	public static function getLockTitle() {
		/* translators: Tooltip of the padlock next to a post type's translation setting that WPML holds; "it" is that setting. */
		return __(
			'Locked by WPML so that ACF fields stay translatable. Click here to unlock and change it anyway.',
			'acfml'
		);
	}
}
