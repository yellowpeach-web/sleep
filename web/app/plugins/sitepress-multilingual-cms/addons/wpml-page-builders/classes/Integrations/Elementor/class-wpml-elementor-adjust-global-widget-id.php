<?php

class WPML_Elementor_Adjust_Global_Widget_ID implements IWPML_Action {

	private $elementor_settings;

	private $translation_element_factory;

	private $sitepress;

	private $has_open_language_scope = false;

	private $scope_language;

	private $latch_before_scope;

	public function __construct(
		IWPML_Page_Builders_Data_Settings $elementor_settings,
		WPML_Translation_Element_Factory $translation_element_factory,
		SitePress $sitepress
	) {
		$this->elementor_settings          = $elementor_settings;
		$this->translation_element_factory = $translation_element_factory;
		$this->sitepress                   = $sitepress;
	}

	public function add_hooks() {
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'adjust_ids' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'restore_current_language' ) );
		add_action( 'shutdown', array( $this, 'restore_current_language' ), 0 );
		add_action( 'wpml_language_has_switched', array( $this, 'keep_admin_language_through_switch' ), PHP_INT_MIN, 0 );
		add_filter( 'elementor/editor/localize_settings', array( $this, 'localize_content_locale' ) );
		add_filter( 'elementor/frontend/the_content', array( $this, 'duplicate_css_class_with_original_id' ) );

		if ( is_admin() ) {
			add_filter(
				'wpml_should_use_display_as_translated_snippet',
				array( $this, 'should_use_display_as_translated_snippet' ),
				PHP_INT_MAX,
				2
			);
		}
	}

	public function adjust_ids() {
		$post_id = absint( $_REQUEST['post'] );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post     = $this->translation_element_factory->create_post( $post_id );
		$language = $post->get_language_code();

		$this->latch_before_scope = $this->sitepress->is_wpml_switch_language_triggered();
		try {
			$this->sitepress->switch_lang( $language );
		} finally {
			$this->latch_before_scope = null;
		}
		$this->has_open_language_scope = true;
		$this->scope_language          = $language;

		$custom_field_data = get_post_meta(
			$post_id,
			$this->elementor_settings->get_meta_field()
		);

		if ( ! $custom_field_data ) {
			return;
		}

		$custom_field_data = $this->elementor_settings->convert_data_to_array( $custom_field_data );

		$custom_field_data_adjusted = $this->set_global_widget_id_for_language( $custom_field_data, $language );

		if ( $custom_field_data_adjusted !== $custom_field_data ) {
			update_post_meta(
				$post_id,
				$this->elementor_settings->get_meta_field(),
				$this->elementor_settings->prepare_data_for_saving( $custom_field_data_adjusted )
			);

			$post_data                  = get_post( $post_id, ARRAY_A );
			$post_data['post_date']     = current_time( 'mysql' );
			$post_data['post_date_gmt'] = '';

			wp_update_post( $post_data );
		}

	}

	private function set_global_widget_id_for_language( $data_array, $language ) {
		foreach ( $data_array as &$data ) {
			if ( isset( $data['elType'] ) && 'widget' === $data['elType'] && 'global' === $data['widgetType'] ) {
				try {
					$widget_post = $this->translation_element_factory->create_post( $data['templateID'] );
					if ( $widget_post->get_language_code() !== $language ) {
						$translation = $widget_post->get_translation( $language );
						if ( $translation ) {
							$data['templateID'] = $translation->get_element_id();
						}
					}
				} catch ( Exception $e ) {
				}
			}
			$data['elements'] = $this->set_global_widget_id_for_language( $data['elements'], $language );
		}

		return $data_array;
	}

	public function restore_current_language() {
		if ( ! $this->has_open_language_scope ) {
			return;
		}

		$this->has_open_language_scope = false;
		$this->scope_language          = null;
		$this->sitepress->switch_lang();
	}

	public function keep_admin_language_through_switch() {
		if ( null === $this->latch_before_scope ) {
			return;
		}

		$this->sitepress->restore_wpml_switch_language_triggered( $this->latch_before_scope );
	}

	public function localize_content_locale( $settings ) {
		if ( ! $this->has_open_language_scope || ! is_array( $settings ) ) {
			return $settings;
		}

		$locale = $this->sitepress->get_locale( $this->scope_language );
		if ( $locale ) {
			$settings['locale'] = $locale;
		}

		return $settings;
	}

	public function should_use_display_as_translated_snippet( $display_as_translated, $post_types ) {
		if ( isset( $_GET['action'] ) && 'elementor' === $_GET['action']
			&& in_array( 'elementor_library', array_keys( $post_types ), true ) ) {
			return true;
		}

		return $display_as_translated;
	}

	public function duplicate_css_class_with_original_id( $content ) {
		$classPrefixes = implode( '|', [ 'elementor-', 'elementor-global-' ] );
		$pattern       = '/(class="[^"]*?((?:' . $classPrefixes . ')))(\d+)/';
		$result        = preg_replace_callback( $pattern, [ $this, 'convert_id_to_original' ], $content );

		if ( null === $result ) {
			return $content;
		}

		return $result;
	}

	private function convert_id_to_original( array $matches ) {
		$class_prefix = $matches[2];
		$id           = (int) $matches[3];
		$element      = $this->translation_element_factory->create_post( $id );
		$source       = $element->get_source_element();

		if ( $source ) {
			$new_class  = $class_prefix . $source->get_id();
			$search     = $matches[0];
			$replace    = $search . ' ' . $new_class;
			$matches[0] = str_replace( $search, $replace, $matches[0] );
		}

		return $matches[0];
	}
}
