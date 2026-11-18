<?php

use WPML\PB\Elementor\Helper\Node;
use WPML\PB\Elementor\Helper\StringFormat;
use WPML\PB\Elementor\V4\Component\Cache;
use WPML\PB\Elementor\V4\Component\InstanceIds;
use WPML\PB\TranslationJob\Editor;

class WPML_Elementor_Update_Translation extends WPML_Page_Builders_Update_Translation {

	private $instance_ids;

	private $cache;

	protected $translatable_nodes;

	public function __construct(
		WPML_Elementor_Translatable_Nodes $translatable_nodes,
		IWPML_Page_Builders_Data_Settings $data_settings,
		InstanceIds $instance_ids,
		Cache $cache
	) {
		parent::__construct( $translatable_nodes, $data_settings );
		$this->instance_ids = $instance_ids;
		$this->cache        = $cache;
	}

	public function save( $post_id, $original_post_id, $converted_data ) {
		$lang = $this->get_lang();

		if ( $lang ) {
			$converted_data = $this->instance_ids->convert( $converted_data, $lang );
		}

		parent::save( $post_id, $original_post_id, $converted_data );

		$this->cache->flushUsedComponents( $post_id );
	}

	public function update( $translated_post_id, $original_post, $string_translations, $lang ) {
		Editor::withJobOf(
			$original_post->ID,
			$lang,
			function () use ( $translated_post_id, $original_post, $string_translations, $lang ) {
				parent::update( $translated_post_id, $original_post, $string_translations, $lang );
			}
		);
	}

	protected function update_strings_in_modules( array &$data_array ) {
		foreach ( $data_array as &$element ) {
			if ( Node::hasChildren( $element ) ) {
				$this->update_strings_in_modules( $element['elements'] );
			}

			if ( $this->translatable_nodes->is_translatable_element( $element ) ) {
				$element = $this->update_strings_in_node( $element[ $this->data_settings->get_node_id_field() ], $element );
			}
		}
	}

	protected function update_strings_in_node( $node_id, $settings ) {
		return WPML_Elementor_Translatable_Nodes::with_active_element_settings_cache(
			function () use ( $node_id, $settings ) {
				$strings = $this->translatable_nodes->get( $node_id, $settings );

				foreach ( $strings as $string ) {
					$translation = $this->get_translation( $string );

					if ( StringFormat::useWpAutoP( $settings, $string ) ) {
						$translation->set_value( wpautop( $translation->get_value() ) );
					}

					$settings = $this->translatable_nodes->update( $node_id, $settings, $translation );
				}

				return $settings;
			}
		);
	}
}
