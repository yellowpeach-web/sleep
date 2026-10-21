<?php

class WPML_Translate_Link_Targets_In_Custom_Fields extends WPML_Translate_Link_Targets {

	const PATH_SEPARATOR = '>';
	const WILDCARD       = '*';
	const MAX_DEPTH = 50;

	private $tm_instance;
	private $wp_api;
	private $meta_keys = array();

	public function __construct( &$tm_instance, &$wp_api, $absolute_links, $permalinks_converter ) {
		parent::__construct( $absolute_links, $permalinks_converter );
		$this->tm_instance = &$tm_instance;
		$this->wp_api      = &$wp_api;

		$link_targets = wpml_get_tm_sub_setting( 'custom_fields_translate_link_target' );
		if ( ! empty( $link_targets ) && is_array( $link_targets ) ) {
			$this->meta_keys = $link_targets;
		}
	}

	public function has_meta_keys() {
		return (bool) $this->meta_keys;
	}

	public function maybe_translate_link_targets( $metadata, $object_id, $meta_key, $single ) {
		if ( ! array_key_exists( $meta_key, $this->meta_keys ) ) {
			return $metadata;
		}

		$custom_field_setting = new WPML_Post_Custom_Field_Setting( $this->tm_instance, $meta_key );
		if ( ! $custom_field_setting->is_translate_link_target() ) {
			return $metadata;
		}

		$this->wp_api->remove_filter( 'get_post_metadata', array( $this, 'maybe_translate_link_targets' ), 10 );
		$metadata_raw = $this->wp_api->get_post_meta( $object_id, $meta_key, $single );
		$this->wp_api->add_filter( 'get_post_metadata', array( $this, 'maybe_translate_link_targets' ), 10, 4 );

		if ( ! $metadata_raw ) {
			return $single ? array( $metadata_raw ) : $metadata_raw;
		}
		if ( $single ) {
			$metadata_raw = array( $metadata_raw );
		}

		$rules = $this->get_rules( $custom_field_setting );

		foreach ( $metadata_raw as $index => $value ) {
			$metadata_raw[ $index ] = $this->convert_value( $value, $rules );
		}

		if ( $single && ! is_array( $metadata_raw[0] ) ) {
			$metadata_raw = $metadata_raw[0];
		}

		return $metadata_raw;
	}

	private function get_rules( WPML_Custom_Field_Setting $setting ) {
		$sub_keys = $setting->get_translate_link_target_sub_keys();

		return array(
			'state'      => $setting->get_translate_link_target_state(),
			'sub_fields' => $setting->get_translate_link_target_sub_fields(),
			'tree'       => $setting->get_attributes_whitelist(),
			'sub_keys'   => $sub_keys,
			'link_keys'  => $setting->get_translate_link_target_link_keys(),
			'narrow'     => in_array( true, $sub_keys, true ),
		);
	}

	private function convert_value( $value, array $rules ) {
		if ( ! empty( $rules['sub_fields'] ) ) {
			return $this->convert_sub_fields( $rules['sub_fields'], $value );
		}

		if ( is_array( $value ) ) {
			if ( $rules['tree'] ) {
				return $this->convert_by_tree( $value, $rules['tree'], '', null, $rules, 0 );
			}

			return $rules['state'] ? $this->convert_blanket( $value, 0 ) : $value;
		}

		return $rules['state'] ? $this->convert_text( $value ) : $value;
	}

	private function convert_by_tree( $value, array $tree, $path, $inherited, array $rules, $depth ) {
		if ( ! is_array( $value ) || $depth > self::MAX_DEPTH ) {
			return $value;
		}

		$named = $this->named_keys( $tree );

		foreach ( $tree as $tree_key => $node ) {
			$tree_key  = (string) $tree_key;
			$node_path = '' === $path ? $tree_key : $path . self::PATH_SEPARATOR . $tree_key;
			$flag      = array_key_exists( $node_path, $rules['sub_keys'] ) ? (bool) $rules['sub_keys'][ $node_path ] : $inherited;
			$wildcard  = false !== strpos( $tree_key, self::WILDCARD );

			foreach ( $this->matching_keys( $tree_key, $value ) as $value_key ) {
				if ( $wildcard && isset( $named[ (string) $value_key ] ) ) {
					continue;
				}

				if ( is_array( $node ) ) {
					$value[ $value_key ] = $this->convert_by_tree( $value[ $value_key ], $node, $node_path, $flag, $rules, $depth + 1 );
				} elseif ( $this->is_leaf_enabled( $flag, $rules ) ) {
					$mode                = isset( $rules['link_keys'][ $node_path ] ) ? 'link' : 'text';
					$value[ $value_key ] = $this->convert_leaf( $value[ $value_key ], $mode );
				}
			}
		}

		return $value;
	}

	private function matching_keys( $tree_key, array $value ) {
		if ( self::WILDCARD === $tree_key ) {
			return array_keys( $value );
		}
		if ( false !== strpos( $tree_key, self::WILDCARD ) ) {
			$regex = '/^' . str_replace( '\*', '\S+', preg_quote( $tree_key, '/' ) ) . '$/';

			return array_values( array_filter( array_keys( $value ), function ( $key ) use ( $regex ) {
				return (bool) preg_match( $regex, (string) $key );
			} ) );
		}

		return array_key_exists( $tree_key, $value ) ? array( $tree_key ) : array();
	}

	private function named_keys( array $tree ) {
		$named = array();
		foreach ( array_keys( $tree ) as $tree_key ) {
			$tree_key = (string) $tree_key;
			if ( false === strpos( $tree_key, self::WILDCARD ) ) {
				$named[ $tree_key ] = true;
			}
		}

		return $named;
	}

	private function is_leaf_enabled( $flag, array $rules ) {
		if ( null !== $flag ) {
			return $flag;
		}

		return $rules['state'] && ! $rules['narrow'];
	}

	private function convert_blanket( $value, $depth ) {
		if ( $depth > self::MAX_DEPTH ) {
			return $value;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->convert_blanket( $item, $depth + 1 );
			}

			return $value;
		}

		return is_string( $value ) ? $this->convert_leaf( $value, 'auto' ) : $value;
	}

	private function convert_leaf( $leaf, $mode ) {
		if ( ! is_string( $leaf ) || '' === $leaf ) {
			return $leaf;
		}

		if ( 'text' !== $mode ) {
			$url = trim( $leaf );
			if ( $this->is_bare_url( $url ) && ( 'link' === $mode || $this->is_same_site_url( $url ) ) ) {
				return str_replace( $url, $this->convert_url( $url ), $leaf );
			}
		}

		if ( ! AbsoluteLinks::has_href_attribute( $leaf )
			&& ! AbsoluteLinks::has_embed_block( $leaf )
			&& ! AbsoluteLinks::has_block_attribute_url( $leaf )
		) {
			return $leaf;
		}

		return $this->convert_text( $leaf );
	}

	private function is_bare_url( $url ) {
		return '' !== $url && ! preg_match( '/[\s<>"\'\\\\]/', $url );
	}

	private function is_same_site_url( $url ) {
		if ( '/' === substr( $url, 0, 1 ) ) {
			return '//' !== substr( $url, 0, 2 );
		}

		return WPML_Same_Site_Url_Normalizer::is_same_site( $url );
	}

	private function convert_sub_fields( $sub_fields, $metadata ) {
		foreach ( $sub_fields as $sub_field ) {
			if ( isset( $sub_field['value'], $sub_field['attr']['translate_link_target'] ) && $sub_field['attr']['translate_link_target'] ) {
				$key = trim( $sub_field['value'] );
				if ( isset( $metadata[ $key ] ) ) {
					$metadata[ $key ] = $this->convert_text( $metadata[ $key ] );
				}
			}
		}

		return $metadata;
	}
}
