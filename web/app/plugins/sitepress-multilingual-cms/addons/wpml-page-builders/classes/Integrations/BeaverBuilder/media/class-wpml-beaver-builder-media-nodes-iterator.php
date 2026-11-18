<?php

class WPML_Beaver_Builder_Media_Nodes_Iterator implements IWPML_PB_Media_Nodes_Iterator {

	private $node_provider;

	public function __construct( WPML_Beaver_Builder_Media_Node_Provider $node_provider ) {
		$this->node_provider = $node_provider;
	}

	public function translate( $data_array, $lang, $source_lang ) {
		foreach ( $data_array as &$data ) {
			if ( is_array( $data ) ) {
				$data = $this->translate( $data, $lang, $source_lang );
			} elseif ( is_object( $data ) && isset( $data->settings ) && is_object( $data->settings ) ) {
				$media_node_type = $this->get_media_node_type( $data );

				if ( $media_node_type ) {
					$data->settings = $this->translate_node( $media_node_type, $data->settings, $lang, $source_lang );
				}
			}
		}

		return $data_array;
	}

	private function get_media_node_type( $data ) {
		if ( ! isset( $data->type ) ) {
			return null;
		}

		if ( 'module' === $data->type ) {
			return isset( $data->settings->type ) ? $data->settings->type : null;
		}

		return in_array( $data->type, [ 'row', 'column' ], true ) ? $data->type : null;
	}

	private function translate_node( $media_node_type, $settings, $lang, $source_lang ) {
		$node = $this->node_provider->get( $media_node_type );

		if ( $node ) {
			$settings = $node->translate( $settings, $lang, $source_lang );
		}

		return $settings;
	}

	public function get_media() {
		return $this->node_provider->get_media();
	}
}
