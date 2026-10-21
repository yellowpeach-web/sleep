<?php

class WPML_Translator_Records extends WPML_Translation_Roles_Records {

	protected function prepare_hooks() {
		add_action( 'user_register', [ $this, 'on_user_register' ], 10, 2 );
		add_filter( 'update_user_metadata', [ $this, 'on_user_meta_update' ], 10, 4 );
	}

	protected function get_capability() {
		return \WPML\LIB\WP\User::CAP_TRANSLATE;
	}

	protected function get_required_wp_roles() {
		return array();
	}

	public function get_users_with_languages( $source_language, $target_languages, $require_all_languages = true ) {
		$translators = $this->get_users_with_capability();

		$language_records       = new WPML_Language_Records( $this->wpdb );
		$language_pairs_records = new WPML_Language_Pair_Records( $this->wpdb, $language_records );

		$translators_with_langs = array();
		foreach ( $translators as $translator ) {
			$language_pairs_for_user = $language_pairs_records->get( $translator->ID );

			if ( isset( $language_pairs_for_user[ $source_language ] ) ) {
				$lang_count = 0;
				foreach ( $target_languages as $target_language ) {
					$lang_count += in_array( $target_language, $language_pairs_for_user[ $source_language ], true ) ? 1 : 0;
				}
				if (
					$require_all_languages && $lang_count === count( $target_languages ) ||
					! $require_all_languages && $lang_count > 0
				) {
					$translators_with_langs[] = $translator;
				}
			}
		}

		return $translators_with_langs;
	}

	public function on_user_register( $id, $data = [] ) {
		$this->administratorRoleManager->verifyUserId( $id );
	}

	public function on_user_meta_update( $check, $user_id, $meta_key, $meta_value ) {
		if ( $this->wpdb->prefix . 'capabilities' !== $meta_key ) {
			return $check;
		}

		if (
			is_array( $meta_value ) &&
			! array_key_exists( $this->get_capability(), $meta_value ) &&
			array_key_exists( 'administrator', $meta_value ) &&
			! \WPML\Troubleshooting\ResetWindow::isOpen()
		) {
			$this->administratorRoleManager->verifyUserId( $user_id, true );
		}

		return $check;
	}


}
