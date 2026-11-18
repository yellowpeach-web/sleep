<?php

use WPML\PB\BeaverBuilder\BeaverThemer\LocationHooks;
use WPML\PB\BeaverBuilder\Component\Overrides;

class WPML_Beaver_Builder_Update_Translation extends WPML_Page_Builders_Update_Translation  {

	use \WPML\PB\BeaverBuilder\KeepsUnpublishedDraft {
		save as private saveKeepingUnpublishedDraft;
	}

	private $location_hooks;

	public function __construct(
		IWPML_Page_Builders_Translatable_Nodes $translatable_nodes,
		IWPML_Page_Builders_Data_Settings $data_settings,
		?LocationHooks $location_hooks = null
	) {
		parent::__construct( $translatable_nodes, $data_settings );
		$this->location_hooks = $location_hooks ?: new LocationHooks();
	}

	public function save( $post_id, $original_post_id, $converted_data ) {
		$converted_data = $this->location_hooks->translateRebuiltConditionalLogic( $converted_data, $post_id );

		$this->saveKeepingUnpublishedDraft( $post_id, $original_post_id, $converted_data );
	}

	public function update_strings_in_modules( array &$data_array ) {
		$this->update_module_strings( $data_array );
		$this->update_component_override_strings( $data_array );
	}

	private function update_module_strings( array &$data_array ) {
		foreach ( $data_array as &$data ) {
			if ( is_array( $data ) ) {
				$this->update_module_strings( $data );
			} elseif ( is_object( $data ) ) {
				if ( isset( $data->type, $data->node, $data->settings ) && 'module' === $data->type ) {
					$data->settings = $this->update_strings_in_node( $data->node, $data->settings );
				}
			}
		}
		unset( $data );
	}

	private function update_component_override_strings( array &$data_array ) {
		$nodes = Overrides::collectNodes( $data_array );

		foreach ( $nodes as $node ) {
			foreach ( Overrides::getOverrideTargets( $node, $nodes ) as $target ) {
				$translated_settings = $this->update_strings_in_node( $target['node_id'], $target['settings'] );
				Overrides::applyTranslatedSettings( $node, $target, $translated_settings );
			}
		}
	}

	public function update_strings_in_node( $node_id, $settings ) {
		$strings = $this->translatable_nodes->get( $node_id, $settings );
		if ( $this->translatable_nodes instanceof WPML_Beaver_Builder_Translatable_Nodes ) {
			$strings = array_merge( $strings, $this->translatable_nodes->get_inactive( $node_id, $settings, $strings ) );
		}
		foreach ( $strings as $string ) {
			$translation = $this->get_translation( $string );
			$settings = $this->translatable_nodes->update( $node_id, $settings, $translation );
		}
		return $settings;
	}
}