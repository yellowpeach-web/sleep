<?php

class WPML_ST_Slug_Translation_Strings_Sync implements IWPML_Action {

	private $slug_records_factory;

	private $slug_settings_factory;

	private $sitepress;

	public function __construct(
		WPML_Slug_Translation_Records_Factory $slug_records_factory,
		WPML_ST_Slug_Translation_Settings_Factory $slug_settings_factory,
		?SitePress $sitepress = null
	) {
		$this->slug_records_factory  = $slug_records_factory;
		$this->slug_settings_factory = $slug_settings_factory;
		$this->sitepress             = $sitepress;
	}

	public function add_hooks() {
		add_action( 'registered_taxonomy', array( $this, 'run_taxonomy_sync' ), 10, 3 );
		add_action( 'registered_post_type', array( $this, 'run_post_type_sync' ), 10, 2 );
	}

	public function run_taxonomy_sync( $taxonomy, $object_type, $taxonomy_array ) {
		if ( isset( $taxonomy_array['rewrite']['slug'] ) && $taxonomy_array['rewrite']['slug'] ) {
			$this->sync_element_slug( $taxonomy_array['rewrite']['slug'], $taxonomy, WPML_Slug_Translation_Factory::TAX );
		}
	}

	public function run_post_type_sync( $post_type, $post_type_object ) {
		if ( isset( $post_type_object->rewrite['slug'] ) && $post_type_object->rewrite['slug'] ) {
			$this->sync_element_slug( trim( $post_type_object->rewrite['slug'], '/' ), $post_type, WPML_Slug_Translation_Factory::POST );
		}
	}

	public function sync_element_slug( $rewrite_slug, $type, $element_type ) {
		$settings = $this->slug_settings_factory->create( $element_type );

		if ( ! $settings->is_translated( $type ) ) {
			return;
		}

		$records = $this->slug_records_factory->create( $element_type );
		$slug    = $records->get_slug( $type );

		if (
			$slug->get_original_value() !== $rewrite_slug
			&& ! in_array( $rewrite_slug, $slug->get_translation_values(), true )
			&& $this->is_read_in_language( (string) $slug->get_original_lang() )
		) {
			$records->update_original_slug( $type, $rewrite_slug );
		}
	}

	private function is_read_in_language( $string_lang ) {
		if ( '' === $string_lang || ! $this->sitepress ) {
			return true;
		}

		$string_locale = $this->sitepress->get_locale( $string_lang );
		if ( ! $string_locale ) {
			return true;
		}

		$request_locale = determine_locale();

		return $request_locale === $string_locale
			|| $request_locale === $this->sitepress->get_locale( (string) $this->sitepress->get_default_language() );
	}
}
