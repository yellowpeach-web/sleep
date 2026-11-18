<?php

use WPML\API\Sanitize;
use WPML\ST\SlugTranslation\PostArchive\Records;

class WPML_ST_Slug_Translation_UI_Save implements IWPML_Action {

	const ACTION_HOOK_FOR_POST = 'wpml_save_cpt_sync_settings';
	const ACTION_HOOK_FOR_TAX  = 'wpml_save_taxonomy_sync_settings';

	const NOTICE_GROUP = 'wpml-st-slug-original-language';

	const POST_KEY_ARCHIVE = 'archive';

	private $settings;

	private $records;

	private $sitepress;

	private $wp_element_type;

	private $action_hook;

	private $archive_records;

	public function __construct(
		WPML_ST_Slug_Translation_Settings $settings,
		WPML_Slug_Translation_Records $records,
		SitePress $sitepress,
		IWPML_WP_Element_Type $wp_element_type,
		$action_hook,
		?WPML_Slug_Translation_Records $archive_records = null
	) {
		$this->settings        = $settings;
		$this->records         = $records;
		$this->sitepress       = $sitepress;
		$this->wp_element_type = $wp_element_type;
		$this->action_hook     = $action_hook;
		$this->archive_records = $archive_records;
	}

	public function add_hooks() {
		add_action( $this->action_hook, array( $this, 'save_element_type_slug_translation_options' ), 1 );
	}

	public function save_element_type_slug_translation_options() {
		if ( function_exists( 'wpml_get_admin_notices' ) ) {
			wpml_get_admin_notices()->remove_notice_group( self::NOTICE_GROUP );
		}
		if ( $this->settings->is_enabled() && ! empty( $_POST['translate_slugs'] ) ) {

			foreach ( $_POST['translate_slugs'] as $type => $data ) {
				$this->save_type( (string) Sanitize::string( $type ), $this->sanitize_translate_slug_data( $data ) );
			}

			$this->settings->save();
		}
	}

	private function save_type( string $type, array $data ) {
		$archive_data = $this->get_archive_data( $type, $data );

		$is_type_enabled = $this->has_translation( $data )
			|| ( $archive_data && $this->has_translation( $archive_data ) );
		$this->settings->set_type( $type, $is_type_enabled );
		$original_language = $this->update_slug_translations(
			$this->records,
			$type,
			$data,
			function ( $type ) {
				return $this->get_registered_slug( $type );
			}
		);

		if ( $archive_data && $original_language ) {
			$archive_data['original'] = $original_language;
			$this->update_slug_translations(
				$this->archive_records,
				$type,
				$archive_data,
				function ( $type ) {
					return $this->get_registered_archive_slug( $type );
				}
			);
		}
	}

	private function get_archive_data( string $type, array $data ) {
		if ( ! $this->archive_records || '' === $this->get_registered_archive_slug( $type ) ) {
			return null;
		}

		$posted = $data[ self::POST_KEY_ARCHIVE ] ?? null;
		if ( ! is_array( $posted ) ) {
			return null;
		}

		$posted             = wp_unslash( $posted );
		$posted['original'] = $data['original'] ?? null;

		return $this->sanitize_translate_slug_data( $posted );
	}

	private function sanitize_translate_slug_data( array $data ) {
		$data['original'] = Sanitize::stringProp( 'original', $data );
		$data['langs']    = isset( $data['langs'] ) && is_array( $data['langs'] ) ? $data['langs'] : array();

		foreach ( $data['langs'] as $language_code => $translated_slug ) {
			$data['langs'][ $language_code ] = Sanitize::string( $translated_slug );
		}

		return $data;
	}

	private function has_translation( array $data ) {
		$slug_translations = $this->get_slug_translations( $data );

		foreach ( $slug_translations as $slug ) {
			if ( trim( $slug ) ) {
				return true;
			}
		}

		return false;
	}

	private function get_slug_translations( array $data ) {
		$slugs              = $data['langs'];
		$original_slug_lang = $data['original'];
		unset( $slugs[ $original_slug_lang ] );
		return $slugs;
	}

	private function update_slug_translations( WPML_Slug_Translation_Records $records, string $type, array $data, callable $get_registered_slug ) {
		$string = $records->get_slug_string( $type );

		if ( ! $string ) {
			$string = $this->register_string_if_not_exit( $records, $type, call_user_func( $get_registered_slug, $type ) );
		}

		$original_language = null;

		if ( $string ) {

			$original_language = $this->sitepress->get_default_language();

			if ( isset( $data['original'] ) ) {
				$original_language = $data['original'];
			}

			if ( $string->get_language() !== $original_language ) {
				$valid = \WPML\Language\RequestedLanguage::validate( $original_language, \WPML\Language\RequestedLanguage::SCOPE_CONFIGURED );

				if ( null === $valid ) {
					$this->refuse_original_language( $type, $original_language );
					$original_language = $string->get_language();
				} else {
					$original_language = $valid;
					$string->set_language( $original_language );
				}
			}

			if ( isset( $data['langs'] ) ) {

				foreach ( $this->sitepress->get_active_languages() as $language_code => $language ) {

					if ( $language_code !== $original_language ) {
						$posted_slug       = isset( $data['langs'][ $language_code ] ) ? $data['langs'][ $language_code ] : '';
						$translation_value = $this->sanitize_slug( $posted_slug );
						$translation_value = urldecode( $translation_value );

						if ( '' === $translation_value ) {
							$string->set_translation( $language_code, '', ICL_TM_NOT_TRANSLATED );
						} else {
							$string->set_translation( $language_code, $translation_value, ICL_TM_COMPLETE );
						}
					}
				}
			}

			$string->update_status();
		}

		return $original_language;
	}

	private function refuse_original_language( $type, $lang ) {
		if ( ! function_exists( 'wpml_get_admin_notices' ) ) {
			return;
		}

		$text = sprintf(
			/* translators: Error shown on the WPML settings page after saving slug translations. %1$s: the content type, for example "product". %2$s: the language code that was refused, for example "hi". */
			__( 'The original language of the "%1$s" slug was not changed: "%2$s" is not one of this site\'s languages.', 'wpml-string-translation' ),
			esc_html( $type ),
			esc_html( $lang )
		);

		$notices = wpml_get_admin_notices();
		$notice  = $notices->create_notice( 'refused-' . $type, $text, self::NOTICE_GROUP );
		$notice->set_css_class_types( 'error' );
		$notice->set_dismissible( true );
		$notices->add_notice( $notice, true );
	}

	private function register_string_if_not_exit( WPML_Slug_Translation_Records $records, string $type, $slug ) {
		$records->register_slug( $type, $slug );
		return $records->get_slug_string( $type );
	}

	private function sanitize_slug( $slug ) {
		return implode( '/', array_map( array( 'WPML_Slug_Translation', 'sanitize' ), explode( '/', $slug ) ) );
	}

	private function get_registered_slug( $type_name ) {
		$wp_element = $this->wp_element_type->get_wp_element_type_object( $type_name );
		return $wp_element ? trim( $wp_element->rewrite['slug'], '/' ) : false;
	}

	private function get_registered_archive_slug( string $type_name ): string {
		return Records::get_registered_archive_slug(
			$this->wp_element_type->get_wp_element_type_object( $type_name )
		);
	}
}
