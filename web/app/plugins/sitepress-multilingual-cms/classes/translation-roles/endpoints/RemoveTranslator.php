<?php

namespace WPML\TranslationRoles;

use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LIB\WP\User;

class RemoveTranslator extends Remove {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_translations' ) && ! current_user_can( 'manage_options' ) ) {
			return Either::left( __( 'You are not allowed to remove translators.', 'sitepress' ) );
		}

		return parent::run( $data );
	}

	protected static function getCap() {
		return User::CAP_TRANSLATE;
	}
}
