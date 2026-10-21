<?php

class WPML_Languages_Notices {
	const NOTICE_ID_MISSING_MENU_ITEMS           = 'wpml-missing-menu-items';
	const NOTICE_GROUP                           = 'wpml-core';
	const NOTICE_ID_MISSING_DOWNLOADED_LANGUAGES = 'wpml-missing-downloaded-languages';
	private $admin_notices;
	private $translations = array();

	public function __construct( WPML_Notices $admin_notices ) {
		$this->admin_notices = $admin_notices;
	}

	function maybe_create_notice_missing_menu_items( $languages_count ) {
		if ( 1 === $languages_count ) {
			$text   = __( 'You need to configure at least one more language in order to access "Theme and plugins localization" and "Media Translation" screens.', 'sitepress' );
			$notice = new WPML_Notice( self::NOTICE_ID_MISSING_MENU_ITEMS, $text, self::NOTICE_GROUP );
			$notice->set_css_class_types( 'info' );
			$notice->set_dismissible( true );
			$this->admin_notices->add_notice( $notice );
		} else {
			$this->admin_notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID_MISSING_MENU_ITEMS );
		}
	}

	public function missing_languages( $not_found_languages ) {
		$list_items = array();
		if ( $not_found_languages ) {
			/* translators: One line of a notice listing languages whose WordPress language file may be wrong. %1$s: the name of the language, %2$s: the code it uses now, %3$s: the codes WPML suggests instead. */
			$list_item_pattern = __( '%1$s (current locale: %2$s) - suggested locale(s): %3$s', 'sitepress' );

			foreach ( (array) $not_found_languages as $not_found_language ) {
				$suggested_codes = $this->get_suggestions( $not_found_language );
				if ( $suggested_codes ) {
					$suggestions = '<strong>' . implode( '</strong>, <strong>', $suggested_codes ) . '</strong>';
					$current     = $not_found_language['code'];
					if ( $not_found_language['default_locale'] ) {
						$current = $not_found_language['default_locale'];
					}
					$list_items[] = sprintf( $list_item_pattern, $not_found_language['display_name'], $current, $suggestions );
				}
			}
		}
		if ( $list_items ) {
			$text = '';

			$text .= '<p>';
			$text .= __( 'WordPress cannot automatically download translations for the following languages:', 'sitepress' );
			$text .= '</p>';

			$text .= '<ul>';
			$text .= '<li>';
			$text .= implode( '</li><li>', $list_items );
			$text .= '</li>';
			$text .= '</ul>';

			$languages_edit_url   = admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/settings&section=languages' );
			$languages_edit_link  = '<a href="' . $languages_edit_url . '">';
			/* translators: Link text that opens the Languages settings screen. It is the path through the menu, so keep the arrow and translate the two names as they appear in the menu. */
			$languages_edit_link .= __( 'Settings → Languages', 'sitepress' );
			$languages_edit_link .= '</a>';

			$text .= '<p>';
			/* translators: %s is a link to the WPML Settings → Languages screen */
			$text .= sprintf( __( 'To fix, open %s, edit each language above and set its Locale as shown.', 'sitepress' ), $languages_edit_link );
			$text .= '</p>';

			$notice = new WPML_Notice( self::NOTICE_ID_MISSING_DOWNLOADED_LANGUAGES, $text, self::NOTICE_GROUP );
			$notice->set_css_class_types( 'warning' );
			$notice->add_display_callback( array( __CLASS__, 'is_not_languages_edit_page' ) );
			$notice->set_dismissible( true );
			$this->admin_notices->add_notice( $notice );
		} else {
			$this->admin_notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID_MISSING_DOWNLOADED_LANGUAGES );
		}
	}

	public static function is_not_languages_edit_page() {
		$result = isset( $_GET['page'], $_GET['section'] )
			&& WPML_TM_FOLDER . '/menu/settings' === $_GET['page']
			&& 'languages' === $_GET['section'];

		return ! $result;
	}

	private function get_suggestions( array $language ) {
		$suggestions = array();
		if ( function_exists( 'translations_api' ) ) {
			if ( ! $this->translations ) {
				$api = translations_api( 'core', array( 'version' => $GLOBALS['wp_version'] ) );

				if ( ! is_wp_error( $api ) && is_array( $api ) && isset( $api['translations'] ) ) {
					$this->translations = $api['translations'];
				}
			}
		}

		if ( $this->translations ) {
			foreach ( $this->translations as $translation ) {
				$default_locale = $this->get_matching_language( $language, $translation );
				if ( $default_locale ) {
					$suggestions[] = $default_locale;
				}
			}
		}

		return $suggestions;
	}

	private function find_matching_attribute( $language_attribute, array $language, array $translation ) {
		if ( $translation && ! empty( $language[ $language_attribute ] ) ) {
			return $this->matches_translation_iso( $language[ $language_attribute ], $translation )
				? $translation['language']
				: null;
		}

		return null;
	}

	private function find_matching_catalogue( array $language, array $translation ) {
		if ( ! $translation
			|| empty( $language['code'] )
			|| ! class_exists( '\WPML\LanguageEditor\LanguageCodeResolution' )
		) {
			return null;
		}

		$resolved = \WPML\LanguageEditor\LanguageCodeResolution::resolve( $language['code'] );
		if ( null === $resolved ) {
			return null;
		}

		$candidates = array();
		if ( ! empty( $resolved['wp_code'] ) ) {
			$candidates[] = (string) $resolved['wp_code'];
		}
		if ( '' !== (string) $resolved['language'] ) {
			$candidates[] = (string) $resolved['language'];
			if ( null !== $resolved['country'] ) {
				$candidates[] = $resolved['language'] . '_' . $resolved['country'];
			}
		}

		foreach ( $candidates as $candidate ) {
			if ( $this->matches_translation_iso( $candidate, $translation ) ) {
				return $translation['language'];
			}
		}

		return null;
	}

	private function matches_translation_iso( $value, array $translation ) {
		$value = strtolower( str_replace( '-', '_', (string) $value ) );
		if ( '' === $value || ! isset( $translation['iso'] ) || ! is_array( $translation['iso'] ) ) {
			return false;
		}

		$iso_1 = array_key_exists( 1, $translation['iso'] ) ? strtolower( (string) $translation['iso'][1] ) : '';
		$iso_2 = array_key_exists( 2, $translation['iso'] ) ? strtolower( (string) $translation['iso'][2] ) : '';

		return $iso_1 === $value
			|| $iso_2 === $value
			|| $iso_1 . '_' . $iso_2 === $value
			|| $iso_2 . '_' . $iso_1 === $value;
	}

	private function get_matching_language( array $language, array $translation ) {
		$default_locale = $this->find_matching_attribute( 'default_locale', $language, $translation );
		if ( ! $default_locale ) {
			$default_locale = $this->find_matching_attribute( 'tag', $language, $translation );
			if ( ! $default_locale ) {
				$default_locale = $this->find_matching_catalogue( $language, $translation );
			}
		}

		return $default_locale;
	}
}
