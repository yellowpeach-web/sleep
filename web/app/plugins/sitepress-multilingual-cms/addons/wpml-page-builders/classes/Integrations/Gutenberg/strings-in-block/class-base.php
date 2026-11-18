<?php

namespace WPML\PB\Gutenberg\StringsInBlock;

abstract class Base implements StringsInBlock {

	const LONG_STRING_LENGTH = 80;

	private $block_types;

	private $config_option;

	public function __construct( \WPML_Gutenberg_Config_Option $config_option ) {
		$this->config_option = $config_option;
	}

	protected function get_block_config( \WP_Block_Parser_Block $block, $type ) {
		if ( null === $this->block_types ) {
			$this->block_types = $this->config_option->get();
		}

		$block_config = isset( $block->blockName, $this->block_types[ $block->blockName ][ $type ] )
			? $this->block_types[ $block->blockName ][ $type ]
			: null;

		$namespace_config = $this->get_namespace_config( $block, $type );

		if ( is_array( $block_config ) && is_array( $namespace_config ) ) {
			return $this->mergeConfigs( $namespace_config, $block_config );
		}

		if ( null !== $block_config ) {
			return $block_config;
		}

		if ( $namespace_config ) {
			return $namespace_config;
		}

		if ( $this->has_empty_config( $block ) ) {
			return [];
		}

		return null;
	}

	private function mergeConfigs( array $namespace_config, array $block_config ): array {
		if ( $this->isList( $namespace_config ) && $this->isList( $block_config ) ) {
			return array_values( array_unique( array_merge( $namespace_config, $block_config ), SORT_REGULAR ) );
		}

		return $this->mergeKeyTrees( $namespace_config, $block_config );
	}

	private function mergeKeyTrees( array $namespace_keys, array $block_keys ): array {
		$merged = [];

		foreach ( array_keys( $namespace_keys + $block_keys ) as $name ) {
			$in_namespace = array_key_exists( $name, $namespace_keys );
			$in_block     = array_key_exists( $name, $block_keys );

			if ( $in_namespace && $in_block ) {
				$merged[ $name ] = $this->mergeKey( $namespace_keys[ $name ], $block_keys[ $name ] );
			} elseif ( $in_namespace ) {
				$merged[ $name ] = $this->mergeWildcardInto( $namespace_keys[ $name ], $name, $block_keys );
			} else {
				$merged[ $name ] = $this->mergeWildcardInto( $block_keys[ $name ], $name, $namespace_keys );
			}
		}

		return $merged;
	}

	private function mergeKey( $lower, $winner ) {
		if ( ! is_array( $lower ) || ! is_array( $winner ) ) {
			return $winner;
		}

		$merged_key      = array_merge( $lower, $winner );
		$lower_children  = $this->getChildren( $lower );
		$winner_children = $this->getChildren( $winner );

		if ( null !== $lower_children || null !== $winner_children ) {
			$merged_key['children'] = $this->mergeKeyTrees(
				null !== $lower_children ? $lower_children : $this->getMatchAllChildren( $lower ),
				null !== $winner_children ? $winner_children : $this->getMatchAllChildren( $winner )
			);
		}

		return $merged_key;
	}

	private function mergeWildcardInto( $key, $name, array $other_keys ) {
		if ( '*' === (string) $name || ! array_key_exists( '*', $other_keys ) ) {
			return $key;
		}

		return $this->mergeKey( $other_keys['*'], $key );
	}

	private function getChildren( array $key ) {
		return isset( $key['children'] ) && is_array( $key['children'] ) ? $key['children'] : null;
	}

	private function getMatchAllChildren( array $leaf ): array {
		unset( $leaf['children'] );
		$leaf['search-method'] = \WPML_Gutenberg_Config_Option::SEARCH_METHOD_WILDCARD;

		return [ '*' => $leaf ];
	}

	private function isList( array $config ): bool {
		return array_values( $config ) === $config;
	}


	protected function get_block_label( \WP_Block_Parser_Block $block ) {
		$label = $this->get_block_config( $block, 'label' );
		if ( ! is_string( $label ) ) {
			$label = $block->blockName;
		}

		return $label;
	}

	public function get_namespace_config( \WP_Block_Parser_Block $block, $type ) {
		if ( isset( $block->blockName ) ) {
			$block_name_arr  = explode( '/', $block->blockName );
			$block_namespace = reset( $block_name_arr );

			if ( isset( $this->block_types[ $block_namespace ][ $type ] ) ) {
				return $this->block_types[ $block_namespace ][ $type ];
			}
		}

		return null;
	}

	private function has_empty_config( \WP_Block_Parser_Block $block ) {
		return isset( $block->blockName, $this->block_types[ $block->blockName ] );
	}

	public static function get_string_type( $string ) {
		$type = 'LINE';
		$string = is_null( $string ) ? '' : $string;

		if ( strpos( $string, "\n" ) !== false || mb_strlen( $string ) > self::LONG_STRING_LENGTH ) {
			$type = 'AREA';
		}

		if ( strpos( $string, '<' ) !== false ) {
			$type = 'VISUAL';
		}

		return $type;
	}

	protected function build_string( $id, $name, $text, $type ) {
		return (object) array(
			'id'    => $id,
			'name'  => $name,
			'value' => $text,
			'type'  => $type,
		);
	}

	protected function get_string_id( $name, $text ) {
		return md5( $name . $text );
	}
}
