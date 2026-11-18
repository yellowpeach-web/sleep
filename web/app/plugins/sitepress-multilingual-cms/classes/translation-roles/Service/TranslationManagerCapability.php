<?php

namespace WPML\TranslationRoles\Service;

use WPML\LIB\WP\User;

class TranslationManagerCapability {

	public function grant( $userId ) {
		$user = User::get( $userId );
		if ( ! $user->exists() ) {
			return;
		}

		$user->add_cap( User::CAP_MANAGE_TRANSLATIONS );

		do_action( 'wpml_tm_add_translation_role', $user, User::CAP_MANAGE_TRANSLATIONS );
		\WPML_Translation_Manager_Records::forget_user_capability_memo( $user->ID );
		do_action( 'wpml_tm_ate_synchronize_managers', $user->ID );
	}

	public function revoke( $userId ) {
		$user = User::get( $userId );
		if ( ! $user->exists() ) {
			return;
		}

		$user->remove_cap( User::CAP_MANAGE_TRANSLATIONS );

		do_action( 'wpml_tm_remove_translation_role', $user, User::CAP_MANAGE_TRANSLATIONS );
		\WPML_Translation_Manager_Records::forget_user_capability_memo( $user->ID );
		do_action( 'wpml_tm_ate_synchronize_managers' );
	}
}
