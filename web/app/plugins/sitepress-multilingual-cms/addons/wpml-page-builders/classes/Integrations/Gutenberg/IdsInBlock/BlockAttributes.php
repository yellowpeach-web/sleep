<?php

namespace WPML\PB\Gutenberg\ConvertIdsInBlock;

use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Str;
use WPML\PB\Gutenberg\StringsInBlock\Attributes;

class BlockAttributes extends Base {

	private $attributesToConvert;

	public function __construct( array $attributesToConvert ) {
		$this->attributesToConvert = $attributesToConvert;
	}

	public function convert( array $block ) {
		if ( ! isset( $block['attrs'] ) ) {
			return $block;
		}

		foreach ( $this->attributesToConvert as $attributeConfig ) {
			$getConfig = Obj::prop( Fns::__, $attributeConfig );
			$name      = $getConfig( 'name' );

			if ( $name ) {
				$block['attrs'] = self::convertByName( $block['attrs'], $name, $getConfig );
			} elseif ( $getConfig( 'path' ) ) {
				$path           = explode( '>', $getConfig( 'path' ) );
				$block['attrs'] = self::convertByPath( $block['attrs'], $path, $getConfig );
			}
		}

		return $block;
	}

	public function collectIds( array $block ) {
		$ids = [];
		if ( ! isset( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) {
			return $ids;
		}

		foreach ( $this->attributesToConvert as $attributeConfig ) {
			$getConfig = Obj::prop( Fns::__, $attributeConfig );
			$slug      = (string) $getConfig( 'slug' );
			$collect   = function ( $value ) use ( &$ids, $slug ) {
				$ids[ $slug ] = array_merge( isset( $ids[ $slug ] ) ? $ids[ $slug ] : [], Prefetch::idsIn( $value ) );

				return $value;
			};

			self::mapAttribute( $block['attrs'], $getConfig, $collect );
		}

		return array_filter( $ids );
	}

	private static function mapAttribute( $attrs, $getConfig, $atValue ) {
		$name = $getConfig( 'name' );

		if ( $name ) {
			if ( isset( $attrs[ $name ] ) && ( is_scalar( $attrs[ $name ] ) || self::isListOfIds( $attrs[ $name ] ) ) ) {
				$attrs[ $name ] = $atValue( $attrs[ $name ] );
			}
		} elseif ( $getConfig( 'path' ) ) {
			$attrs = self::mapByPath( $attrs, explode( '>', $getConfig( 'path' ) ), $atValue );
		}

		return $attrs;
	}

	private function convertByName( $attrs, $name, $getConfig ) {
		return self::mapAttribute(
			$attrs,
			$getConfig,
			function ( $value ) use ( $getConfig ) {
				return self::convertValue( $value, $getConfig );
			}
		);
	}

	private function convertByPath( $attrs, $path, $getConfig ) {
		return self::mapByPath(
			$attrs,
			$path,
			function ( $value ) use ( $getConfig ) {
				return self::convertValue( $value, $getConfig );
			}
		);
	}

	private static function mapByPath( $attrs, $path, $atValue ) {
		$currentKey  = reset( $path );
		$nextPath    = Lst::drop( 1, $path );
		$hasWildCard = false !== strpos( $currentKey, '*' );

		if ( $hasWildCard && is_array( $attrs ) ) {
			$regex = Attributes::getWildcardRegex( $currentKey );

			foreach ( $attrs as $key => $attr ) {
				if ( Str::match( $regex, $key ) ) {
					$attrs[ $key ] = self::mapByPath( $attr, $nextPath, $atValue );
				}
			}
		} elseif ( $currentKey && isset( $attrs[ $currentKey ] ) ) {
			$attrs[ $currentKey ] = self::mapByPath( $attrs[ $currentKey ], $nextPath, $atValue );
		} elseif ( ! $nextPath && ( is_scalar( $attrs ) || ( false === $currentKey && self::isListOfIds( $attrs ) ) ) ) {
			$attrs = $atValue( $attrs );
		}

		return $attrs;
	}

	private static function isListOfIds( $value ) {
		if ( ! is_array( $value ) || ! $value || array_values( $value ) !== $value ) {
			return false;
		}

		$hasId = false;
		foreach ( $value as $item ) {
			if ( ! is_scalar( $item ) ) {
				return false;
			}
			$hasId = $hasId || ( is_numeric( $item ) && ! is_float( $item ) );
		}

		return $hasId;
	}

	private static function convertValue( $value, $getConfig ) {
		$converted = self::convertIds( $value, $getConfig( 'slug' ), $getConfig( 'type' ) );

		if ( is_array( $value ) && is_array( $converted ) ) {
			return array_values( $converted );
		}

		return $converted;
	}
}
