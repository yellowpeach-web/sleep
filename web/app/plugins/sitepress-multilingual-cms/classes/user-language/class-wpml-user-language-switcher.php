<?php

use WPML\Element\API\Languages;
use WPML\FP\Maybe;

class WPML_User_Language_Switcher {
	private $WPML_Language_Code;

	public function __construct( &$WPML_Language_Code ) {
		$this->WPML_Language_Code = &$WPML_Language_Code;
	}

	private function to_be_selected( $email ) {
		$language = $this->WPML_Language_Code->get_from_user_meta( $email );
		if ( ! $language ) {
			$language = isset( $_POST['language'] ) ? $_POST['language'] : null;
		}

		return $language;
	}

	public function save_language_user_meta( $email, $language ) {
		$user    = get_user_by( 'email', $email );
		$updated = false;
		if ( $user && isset( $user->ID ) ) {
			$language = $this->WPML_Language_Code->sanitize( $language );
			$updated  = update_user_meta( $user->ID, 'icl_admin_language', $language );

			$wp_locale = Maybe::of( $language )
			                  ->map( Languages::getLanguageDetails() )
			                  ->map( Languages::getWPLocale() )
			                  ->getOrElse( null );

			if ( $wp_locale ) {
				update_user_meta( $user->ID, 'locale', $wp_locale );
			}
		}

		return $updated;
	}

	public function sanitize( $language ) {
		return $this->WPML_Language_Code->sanitize( $language );
	}

	public function get_model( $email ) {

		$active_languages = apply_filters( 'wpml_active_languages', null, null );

		$to_be_selected = $this->to_be_selected( $email );

		$options = array();

		$options[] = array(
			/* translators: Label in front of the dropdown that picks a language; the dropdown follows the colon. */
			'label'    => __( 'Choose language:', 'sitepress' ),
			'value'    => 0,
			'selected' => false,

		);

		foreach ( $active_languages as $code => $lang ) {
			$selected = ( $to_be_selected === $code );

			if ( array_key_exists( 'translated_name', $lang ) ) {
				$name = $lang['translated_name'];
			} elseif ( array_key_exists( 'native_name', $lang ) ) {
				$name = $lang['native_name'];
			} else {
				$name = $lang['display_name'];
			}

			$options[] = array(
				'label'    => $name,
				'value'    => $code,
				'selected' => $selected,

			);
		}

		return array(
			'options' => $options,
		);
	}
}
