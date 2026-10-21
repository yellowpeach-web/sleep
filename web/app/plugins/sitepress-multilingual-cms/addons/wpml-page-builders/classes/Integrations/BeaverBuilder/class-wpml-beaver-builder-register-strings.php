<?php

use WPML\PB\BeaverBuilder\Component\Overrides;

class WPML_Beaver_Builder_Register_Strings extends WPML_Page_Builders_Register_Strings {

	protected function register_strings_for_modules( array $data_array, array $package ) {
		$this->register_module_strings( $data_array, $package );
		$this->register_component_override_strings( $data_array, $package );
	}

	private function register_module_strings( array $data_array, array $package ) {
		foreach ( $data_array as $data ) {
			if ( is_array( $data ) ) {
				$data = self::sort_modules( $data );
				$this->register_module_strings( $data, $package );
			} elseif ( is_object( $data ) ) {
				if ( isset( $data->type, $data->node, $data->settings ) && 'module' === $data->type && ! $this->is_embedded_global_module( $data ) ) {
					$this->register_strings_for_node( $data->node, $data->settings, $package );
				}
			}
		}
	}

	private function register_component_override_strings( array $data_array, array $package ) {
		$nodes = Overrides::collectNodes( $data_array );

		foreach ( $nodes as $node ) {
			foreach ( Overrides::getOverrideTargets( $node, $nodes ) as $target ) {
				$this->register_strings_for_node( $target['node_id'], $target['settings'], $package );
			}
		}
	}

	public static function sort_modules( array $modules ) {
		if ( count( $modules ) > 1 ) {
			uasort( $modules, array( self::class, 'sort_modules_by_position_only' ) );
			return self::sort_modules_by_parent_and_child( $modules );
		}

		return $modules;
	}

	private static function sort_modules_by_parent_and_child( array $all_modules, $parent_hash = null, array $sorted_modules = array() ) {
		foreach ( $all_modules as $hash => $module ) {

			if ( $module->parent === $parent_hash ) {
				$sorted_modules[ $hash ] = $module;
				unset( $all_modules[ $hash ] );
				$sorted_modules = self::sort_modules_by_parent_and_child( $all_modules, $module->node, $sorted_modules );
			}
		}

		return $sorted_modules;
	}

	private static function sort_modules_by_position_only( stdClass $a, stdClass $b ) {
		return ( (int) $a->position < (int) $b->position ) ? -1 : 1;
	}

	private function is_embedded_global_module( $data ) {
		return ! empty( $data->template_node_id ) && isset( $data->node ) && $data->template_node_id !== $data->node;
	}
}
