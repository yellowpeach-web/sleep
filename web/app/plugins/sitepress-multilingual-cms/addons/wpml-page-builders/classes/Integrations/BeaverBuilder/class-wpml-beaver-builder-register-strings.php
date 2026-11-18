<?php

use WPML\PB\BeaverBuilder\Component\Overrides;
use WPML\PB\BeaverBuilder\NodesWithKeptStrings;
use WPML\PB\TranslationJob\Groups;

class WPML_Beaver_Builder_Register_Strings extends WPML_Page_Builders_Register_Strings {

	const MODULE_TYPES_WITH_PHOTO = [ 'photo', 'callout' ];

	private $nodes_with_kept_strings;

	public function __construct(
		IWPML_Page_Builders_Translatable_Nodes $translatable_nodes,
		IWPML_Page_Builders_Data_Settings $data_settings,
		WPML_PB_String_Registration $string_registration,
		?WPML_PB_Reuse_Translations_By_Strategy $reuse_translations = null
	) {
		if ( $translatable_nodes instanceof NodesWithKeptStrings ) {
			$this->nodes_with_kept_strings = $translatable_nodes;
		}

		parent::__construct( $translatable_nodes, $data_settings, $string_registration, $reuse_translations );
	}

	protected function register_strings_for_modules( array $data_array, array $package ) {
		if ( $this->nodes_with_kept_strings ) {
			$this->nodes_with_kept_strings->setPackage( isset( $package['post_id'] ) ? self::find_string_package( $package ) : null );
		}

		$this->register_module_strings( $data_array, $package );
		$this->register_component_override_strings( $data_array, $package );
	}

	private static function find_string_package( array $package ) {
		foreach ( (array) apply_filters( 'wpml_st_get_post_string_packages', false, $package['post_id'] ) as $string_package ) {
			if (
				$string_package instanceof WPML_Package
				&& ( $package['kind'] ?? null ) === $string_package->kind
				&& (string) ( $package['name'] ?? '' ) === (string) $string_package->name
			) {
				return $string_package;
			}
		}

		return null;
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

	protected function filter_string_to_register( WPML_PB_String $string, $node_id, $element, $package ) {
		$photo_id = self::get_photo_id( $element );

		if ( $photo_id && Groups::isGroupLabel( $string->get_title() ) ) {
			$string->set_title( Groups::appendImageIdToGroupLabel( $string->get_title(), $photo_id ) );
		}

		return $string;
	}

	private static function get_photo_id( $settings ) {
		if ( ! self::shows_library_photo( $settings ) ) {
			return 0;
		}

		return isset( $settings->photo ) && is_numeric( $settings->photo ) ? (int) $settings->photo : 0;
	}

	private static function shows_library_photo( $settings ) {
		if ( ! is_object( $settings ) || ! isset( $settings->type ) ) {
			return false;
		}

		if ( 'callout' === $settings->type && 'photo' !== ( $settings->image_type ?? '' ) ) {
			return false;
		}

		return in_array( $settings->type, self::MODULE_TYPES_WITH_PHOTO, true )
			&& 'library' === ( $settings->photo_source ?? 'library' );
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
