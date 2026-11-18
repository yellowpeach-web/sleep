<?php

namespace WPML\Compatibility\Divi;

class Builder implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_AJAX_Action {

	public function add_hooks() {
		if ( self::isLoadingAssetsForDiviBuilder() || self::isLoadingDiviBuilder() ) {
			add_action( 'init', [ $this, 'switch_to_user_language' ] );
		}
	}

	public function switch_to_user_language() {
		$userId = get_current_user_id();

		if ( ! $userId ) {
			return;
		}

		switch_to_locale( get_user_meta( $userId, 'locale', true ) ?: self::getSiteLocale() );
	}

	private static function getSiteLocale(): string {
		$locale = get_option( 'WPLANG' );

		if ( ! $locale && is_multisite() ) {
			$locale = get_site_option( 'WPLANG' );
		}

		if ( ! $locale && defined( 'WPLANG' ) ) {
			$locale = WPLANG;
		}

		return $locale ?: 'en_US';
	}

	private static function isLoadingAssetsForDiviBuilder(): bool {
		return isset( $_POST['action'] )
			&& 'et_fb_update_builder_assets' === $_POST['action'];
	}

	private static function isLoadingDiviBuilder(): bool {
		return ! empty( $_GET['et_fb'] );
	}
}
