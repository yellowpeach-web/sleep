<?php

class WPML_Page_Builders_Media_Gutenberg {

	const TYPE_URL = 'media-url';
	const TYPE_IDS = 'media-ids';

	private $media_translate;

	private $target_lang;

	private $source_lang;

	private $config;

	private $block_name;

	public function __construct( IWPML_PB_Media_Find_And_Translate $media_translate, array $config ) {
		$this->media_translate = $media_translate;
		$this->config          = $config;
	}

	public function translate( array $block ) {
		if ( ! isset( $block['blockName'], $block['attrs'] ) ) {
			return $block;
		}

		$this->block_name = $block['blockName'];
		$block_config     = self::get_block_config( $this->config, $block['blockName'] );

		if ( $block_config && isset( $block_config['key'] ) ) {
			$block['attrs'] = $this->translate_attributes( $block['attrs'], $block_config['key'] );
		}

		return $block;
	}

	public static function find_media_ids( array $config, $block_name, array $attrs ) {
		$block_config = self::get_block_config( $config, (string) $block_name );
		$ids          = [];

		if ( ! isset( $block_config['key'] ) || ! is_array( $block_config['key'] ) ) {
			return $ids;
		}

		$collect = function ( $value ) use ( &$ids ) {
			if ( is_int( $value ) ) {
				$ids[] = $value;
			} elseif ( \WPML\PB\Media\MediaIds::isList( $value ) ) {
				foreach ( explode( ',', $value ) as $id ) {
					$ids[] = (int) trim( $id );
				}
			}

			return $value;
		};

		foreach ( $block_config['key'] as $path => $type ) {
			if ( self::TYPE_IDS === $type ) {
				self::map_by_path( $attrs, explode( '>', $path ), $collect );
			}
		}

		return $ids;
	}

	private static function get_block_config( array $config, $block_name ) {
		list( $namespace ) = explode( '/', $block_name, 2 );

		$namespace_config = isset( $config[ $namespace ] ) ? $config[ $namespace ] : [];
		$block_config     = isset( $config[ $block_name ] ) ? $config[ $block_name ] : [];
		$merged           = array_merge( $namespace_config, $block_config );

		if ( isset( $namespace_config['key'], $block_config['key'] ) && is_array( $namespace_config['key'] ) && is_array( $block_config['key'] ) ) {
			$merged['key'] = array_replace( $namespace_config['key'], $block_config['key'] );
		}

		return $merged;
	}

	private function translate_attributes( array $attrs, array $keys_config ) {
		foreach ( $keys_config as $path => $type ) {
			if ( self::TYPE_URL === $type || self::TYPE_IDS === $type ) {
				$attrs = self::map_by_path(
					$attrs,
					explode( '>', $path ),
					function ( $value ) use ( $type ) {
						return $this->translate_value( $value, $type );
					}
				);
			}
		}

		return $attrs;
	}

	private static function map_by_path( $attrs, $path, callable $map_value ) {
		$current_key = reset( $path );
		$next_path   = array_slice( $path, 1 );

		if ( ! $current_key || ! is_array( $attrs ) ) {
			return $attrs;
		}

		if ( '*' === $current_key ) {
			foreach ( $attrs as $key => $value ) {
				$attrs[ $key ] = self::map_at_path( $value, $next_path, $map_value );
			}
		} elseif ( isset( $attrs[ $current_key ] ) ) {
			$attrs[ $current_key ] = self::map_at_path( $attrs[ $current_key ], $next_path, $map_value );
		}

		return $attrs;
	}

	private static function map_at_path( $value, $next_path, callable $map_value ) {
		return $next_path
			? self::map_by_path( $value, $next_path, $map_value )
			: $map_value( $value );
	}

	private function translate_value( $value, $type ) {
		if ( self::TYPE_IDS === $type && is_int( $value ) ) {
			return $this->media_translate->translate_id( $value, $this->target_lang );
		}

		if ( ! is_string( $value ) || empty( $value ) ) {
			return $value;
		}

		if ( self::TYPE_URL === $type ) {
			return $this->media_translate->translate_image_url( $value, $this->target_lang, $this->source_lang, $this->block_name );
		}

		if ( self::TYPE_IDS === $type ) {
			return $this->translate_ids( $value );
		}

		return $value;
	}

	private function translate_ids( $value ) {
		if ( ! \WPML\PB\Media\MediaIds::isList( $value ) ) {
			return $value;
		}

		return \WPML\PB\Media\MediaIds::translate(
			$value,
			function ( $id ) {
				return $this->media_translate->translate_id( $id, $this->target_lang );
			}
		);
	}

	public function set_target_lang( $target_lang ) {
		$this->target_lang = $target_lang;

		return $this;
	}

	public function set_source_lang( $source_lang ) {
		$this->source_lang = $source_lang;

		return $this;
	}

	public function get_media() {
		return $this->media_translate->get_used_media_in_post();
	}
}
