<?php

use WPML\ST\SlugTranslation\PostArchive\Records;

class WPML_ST_Element_Slug_Translation_UI_Model {

	private $sitepress;

	private $settings;

	private $slug_records;

	private $sync_settings;

	private $language_selector;

	private $archive_records;

	public function __construct(
		SitePress $sitepress,
		WPML_ST_Slug_Translation_Settings $settings,
		WPML_Slug_Translation_Records $slug_records,
		WPML_Element_Sync_Settings $sync_settings,
		WPML_Simple_Language_Selector $lang_selector,
		?WPML_Slug_Translation_Records $archive_records = null
	) {
		$this->sitepress         = $sitepress;
		$this->settings          = $settings;
		$this->slug_records      = $slug_records;
		$this->sync_settings     = $sync_settings;
		$this->language_selector = $lang_selector;
		$this->archive_records   = $archive_records;
	}

	public function get( $type_name, $custom_type ) {
		$has_rewrite_slug   = isset( $custom_type->rewrite['slug'] ) && $custom_type->rewrite['slug'];
		$is_translated_mode = $this->sync_settings->is_sync( $type_name );
		$is_slug_translated = $this->settings->is_translated( $type_name );

		if ( ! $has_rewrite_slug || ! $this->settings->is_enabled() ) {
			return null;
		}

		$original_slug_and_language = $this->get_original_slug_and_lang( $type_name, $custom_type );
		$slug_translations          = $this->get_translations( $type_name );

		$model = array(
			'strings' => array(
				/* translators: Text above the table of slugs on the WPML settings page. %s: the name of the content type, for example "Posts" or "Products". */
				'toggle_slugs_table' => sprintf( __( 'Set different slugs in different languages for %s.', 'wpml-string-translation' ), $custom_type->labels->name ),
				'slug_status_incomplete' => __( "Not marked as 'complete'. Press 'Save' to enable.", 'wpml-string-translation' ),
				/* translators: Marks the row of the original language in the table of slugs on the WPML settings page. */
				'original_label' => __( '(original)', 'wpml-string-translation' ),
			),
			'css_class_wrapper' => 	$is_translated_mode ? '' : 'hidden',
			'type_name' => $type_name,
			'slugs' => array(),
			'has_missing_translations_message' => '',
		);

		if ( $is_slug_translated && ! $original_slug_and_language->is_registered ) {
			$model['has_missing_translations_message'] = sprintf(
				/* translators: Warning above the table of slugs on the WPML settings page. %s: the name of the content type, for example "Posts" or "Products". */
				esc_html__(
					'%s slugs are set to be translated, but they are missing their translation',
					'wpml-string-translation'
				),
				$custom_type->labels->name
			);
		}

		$languages = $this->get_languages( $original_slug_and_language->language );

		foreach ( $languages as $language_code => $language ) {
			$slug = $this->get_slug_row(
				sprintf( 'translate_slugs[%s][langs][%s]', $type_name, $language_code ),
				$language_code,
				$language,
				$slug_translations,
				$original_slug_and_language->value,
				$original_slug_and_language->language
			);

			if ( $slug->is_original ) {
				$slug->language_selector = $this->language_selector->render(
					array(
						'name'               => 'translate_slugs[' . $type_name . '][original]',
						'selected'           => $language_code,
						'show_please_select' => false,
						'echo'               => false,
						'class'              => 'js-translate-slug-original',
						'data'               => array( 'slug' => $slug->value ),
						'languages'          => $languages,
					)
				);
			}

			$model['slugs'][ $slug->language_code ] = $slug;
		}

		$model['archive'] = $this->get_archive( $type_name, $custom_type, $languages, $original_slug_and_language->language );

		return $model;
	}

	private function get_archive( string $type_name, $custom_type, array $languages, string $post_slug_original_language ) {
		if ( ! $this->archive_records ) {
			return null;
		}

		$registered_slug = Records::get_registered_archive_slug( $custom_type );
		if ( '' === $registered_slug ) {
			return null;
		}

		$original          = $this->archive_records->get_original_slug_and_lang( $type_name );
		$original_value    = $original ? $original->value : $registered_slug;
		$slug_translations = $this->get_translations( $type_name, $this->archive_records );

		$archive = array(
			'strings' => array(
				/* translators: Heading above the slugs of the single items of a content type, in the table of slugs on the WPML settings page. */
				'slug_heading'    => __( 'Slug', 'wpml-string-translation' ),
				/* translators: Heading above the slugs of the archive page of a content type, in the table of slugs on the WPML settings page. The archive page lists all the items of the type. */
				'archive_heading' => __( 'Archive slug', 'wpml-string-translation' ),
			),
			'slugs'   => array(),
		);

		foreach ( $languages as $language_code => $language ) {
			$archive['slugs'][ $language_code ] = $this->get_slug_row(
				sprintf( 'translate_slugs[%s][' . WPML_ST_Slug_Translation_UI_Save::POST_KEY_ARCHIVE . '][langs][%s]', $type_name, $language_code ),
				$language_code,
				$language,
				$slug_translations,
				$original_value,
				$post_slug_original_language
			);
		}

		return $archive;
	}

	private function get_slug_row( string $input_id, string $language_code, array $language, array $slug_translations, string $original_value, string $original_language ): stdClass {
		$slug                       = new stdClass();
		$slug->value                = ! empty( $slug_translations[ $language_code ]['value'] )
			? $slug_translations[ $language_code ]['value'] : '';
		$slug->placeholder          = $original_value . ' @' . $language_code;
		$slug->input_id             = $input_id;
		$slug->language_flag        = $this->sitepress->get_flag_img( $language_code );
		$slug->language_name        = $language['display_name'];
		$slug->language_code        = $language_code;
		$slug->is_original          = (string) $language_code === (string) $original_language;
		$slug->status_is_incomplete = isset( $slug_translations[ $language_code ] )
			&& ICL_TM_COMPLETE !== (int) $slug_translations[ $language_code ]['status'];

		if ( $slug->is_original ) {
			$slug->value = $original_value;
		}

		return $slug;
	}

	private function get_original_slug_and_lang( $type_name, $custom_type ) {
		$original_slug_and_lang = $this->slug_records->get_original_slug_and_lang( $type_name );

		if ( $original_slug_and_lang ) {
			$original_slug_and_lang->is_registered = true;
		} else {
			$original_slug_and_lang                = new stdClass();
			$original_slug_and_lang->is_registered = false;
			$original_slug_and_lang->value         = isset( $custom_type->slug )
				? $custom_type->slug : $custom_type->rewrite['slug'];
			$original_slug_and_lang->language      = $this->sitepress->get_default_language();
		}

		return $original_slug_and_lang;
	}

	private function get_translations( $type_name, ?WPML_Slug_Translation_Records $records = null ) {
		$translations = array();
		$records      = $records ?: $this->slug_records;
		$rows         = $records->get_element_slug_translations( $type_name, false );

		foreach( $rows as $row ) {
			$translations[ $row->language ] = array(
				'value' => $row->value,
				'status' => $row->status
			);
		}

		return $translations;
	}

	private function get_languages( $string_lang ) {
		$languages = $this->sitepress->get_active_languages();

		if ( ! in_array( $string_lang, array_keys( $languages ) ) ) {
			$all_languages = $this->sitepress->get_languages();

			$languages[ $string_lang ] = isset( $all_languages[ $string_lang ] )
				? $all_languages[ $string_lang ]
				: array(
					'code'         => $string_lang,
					'display_name' => $string_lang,
				);
		}

		return $languages;
	}
}
