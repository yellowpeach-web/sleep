<?php

namespace WPML\LanguageEditor\Adapter;

use WPML\Core\Component\LanguageEditor\Domain\Settings\SettingsInterface;

class Settings implements SettingsInterface {

	public function defaultLanguage(): ?string {
		global $sitepress;

		$code = $sitepress ? $sitepress->get_default_language() : false;

		return $code ? (string) $code : null;
	}

	public function setDefaultLanguage( string $code ): void {
		global $sitepress;

		if ( ! $sitepress ) {
			return;
		}

		if ( ! wpml_is_setup_complete() ) {
			$user_id           = get_current_user_id();
			$uses_site_default = $user_id && '' === get_user_meta( $user_id, 'locale', true );
			$user_locale       = $uses_site_default ? get_user_locale( $user_id ) : null;
			$user_language     = $uses_site_default ? $sitepress->get_admin_language() : null;

			$sitepress->set_default_language( $code );

			if ( $uses_site_default && $user_locale && $user_language ) {
				update_user_meta( $user_id, 'locale', $user_locale );
				update_user_meta( $user_id, 'icl_admin_language', $user_language );
				wp_cache_delete( $user_id, \WPML_User_Admin_Language::CACHE_GROUP );
			}

			return;
		}

		$sitepress->get_wpml_locale()->reset_cached_data();

		$sitepress->set_default_language( $code );
	}

	public function hiddenLanguages(): array {
		global $sitepress;

		return $sitepress ? array_map( 'strval', (array) $sitepress->get_setting( 'hidden_languages', [] ) ) : [];
	}

	public function setHiddenLanguages( array $codes ): void {
		global $sitepress;

		if ( $sitepress ) {
			$sitepress->set_setting( 'hidden_languages', array_values( $codes ), true );
		}
	}
}
