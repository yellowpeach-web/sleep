<?php

use WPML\PB\Elementor\Helper\Node;
use WPML\PB\TranslationJob\Groups;

class WPML_Elementor_Register_Strings extends WPML_Page_Builders_Register_Strings {

	protected $translatable_nodes;

	public function __construct(
		WPML_Elementor_Translatable_Nodes $translatable_nodes,
		IWPML_Page_Builders_Data_Settings $data_settings,
		WPML_PB_String_Registration $string_registration,
		?WPML_PB_Reuse_Translations_By_Strategy $reuse_translations = null
	) {
		parent::__construct( $translatable_nodes, $data_settings, $string_registration, $reuse_translations );
	}

	protected function filter_string_to_register( WPML_PB_String $string, $node_id, $element, $package ) {
		if ( ! empty( $element['settings']['image']['id'] ) && Groups::isGroupLabel( $string->get_title() ) ) {
			$string->set_title( Groups::appendImageIdToGroupLabel( $string->get_title(), $element['settings']['image']['id'] ) );
		}

		return $string;
	}

	protected function register_strings_for_node( $node_id, $element, array $package ) {
		WPML_Elementor_Translatable_Nodes::with_active_element_settings_cache(
			function () use ( $node_id, $element, $package ) {
				parent::register_strings_for_node( $node_id, $element, $package );
			}
		);
	}

	protected function register_strings_for_modules( array $data_array, array $package ) {
		foreach ( $data_array as $data ) {
			if ( $this->translatable_nodes->is_translatable_element( $data ) ) {
				$this->register_strings_for_node( $data[ $this->data_settings->get_node_id_field() ], $data, $package );
			}
			if ( Node::hasChildren( $data ) ) {
				$this->register_strings_for_modules( $data['elements'], $package );
			}
		}
	}
}
