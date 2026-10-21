<?php

use WPML\PB\BeaverBuilder\Component\Overrides;

class WPML_Beaver_Builder_Update_Translation extends WPML_Page_Builders_Update_Translation  {

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
		foreach ( $strings as $string ) {
			$translation = $this->get_translation( $string );
			$settings = $this->translatable_nodes->update( $node_id, $settings, $translation );
		}
		return $settings;
	}
}