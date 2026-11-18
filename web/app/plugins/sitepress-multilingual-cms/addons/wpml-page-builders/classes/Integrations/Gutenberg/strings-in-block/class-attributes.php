<?php

namespace WPML\PB\Gutenberg\StringsInBlock;

use WPML\FP\Obj;
use WPML\Compatibility\Divi\V5\CustomAttributeItem;
use WPML\PB\Gutenberg\NeverTextKeys;

class Attributes extends Base {

	public function find( \WP_Block_Parser_Block $block ) {
		$strings = [];
		$attrs   = $this->getAttributes( $block );

		if ( $attrs ) {
			$keys    = $this->getKeyConfig( $block );
			$strings = $this->findStringsRecursively( $attrs, $keys, $block );
		}

		return $strings;
	}

	private function findStringsRecursively( array $attrs, array $config_keys, \WP_Block_Parser_Block $block ) {
		$strings = [];

		foreach ( $attrs as $attr_key => $attr_value ) {
			if ( $this->isCodeAttributeItemValue( $attrs, $attr_key, (string) $block->blockName ) ) {
				continue;
			}

			$matching_key = $this->getMatchingConfigKey( $attr_key, $config_keys );

			if ( ! $matching_key ) {
				continue;
			}

			if ( $this->shouldSkipMediaType( $config_keys, $matching_key ) ) {
				continue;
			}

			if ( is_string( $attr_value ) && $this->hasJsonEncoding( $matching_key, $config_keys ) ) {
				$attr_value = $this->decodeUrlEncodedValue( $attr_value, $this->decodeUrlEncodedJson( $attr_value ) );
			}

			if ( is_array( $attr_value ) ) {
				$children_config_keys = $this->getChildrenConfigKeys( $config_keys, $matching_key );

				$strings = array_merge(
					$strings,
					$this->findStringsRecursively( $attr_value, $children_config_keys, $block )
				);
			} elseif ( ! is_numeric( $attr_value ) ) {
				$type      = $this->get_attribute_string_type( $attr_value, $matching_key, $config_keys );
				$string_id = $this->get_string_id( $block->blockName, $attr_value );
				$label     = $config_keys[ $matching_key ]['label'] ?? $this->get_block_label( $block );
				$strings[] = $this->build_string( $string_id, $label, $attr_value, $type );
			}
		}

		return $strings;
	}

	private function isCodeAttributeItemValue( array $attrs, $attr_key, string $block_name ) {
		if (
			'value' !== $attr_key
			|| ! isset( $attrs['name'] )
			|| ! is_string( $attrs['name'] )
			|| ! is_scalar( $attrs['value'] )
		) {
			return false;
		}

		if ( CustomAttributeItem::is( $block_name, $attrs ) ) {
			return ! CustomAttributeItem::isTextKey( $attrs, $attr_key );
		}

		return NeverTextKeys::matches( $attrs['name'], false );
	}

	private function get_attribute_string_type( $attr_value, $matching_key, $config_keys ) {
		$config_type = $config_keys[ $matching_key ]['type'] ?? null;

		if ( 'link' === $config_type ) {
			return \WPML_TM_Page_Builders::FIELD_STYLE_LINK;
		}

		return self::get_string_type( $attr_value );
	}

	private function getMatchingConfigKey( $attr_key, array $config_keys ) {
		if ( isset( $config_keys[ $attr_key ] ) ) {
			return $attr_key;
		}

		foreach ( $config_keys as $config_key => $key_attrs ) {

			if ( preg_match( $this->getRegex( $config_key, $key_attrs ), $attr_key ) ) {
				return $config_key;
			}
		}

		return null;
	}

	private function getChildrenConfigKeys( array $config_keys, $matching_key ) {
		return isset( $config_keys[ $matching_key ]['children'] )
			? $config_keys[ $matching_key ]['children']
			: $this->getMatchAllKey();
	}

	private function getRegex( $config_key, array $key_attrs ) {
		if ( $this->isRegex( $key_attrs ) ) {
			return $config_key;
		}

		return self::getWildcardRegex( $config_key );
	}

	public static function getWildcardRegex( $config_key ) {
		return '/^' . str_replace( '*', 'S+', preg_quote( $config_key, '/' ) ) . '$/';
	}

	private function isRegex( array $key_attrs ) {
		return isset( $key_attrs['search-method'] )
			&& \WPML_Gutenberg_Config_Option::SEARCH_METHOD_REGEX === $key_attrs['search-method'];
	}

	public function update( \WP_Block_Parser_Block $block, array $string_translations, $lang ) {
		$attrs = $this->getAttributes( $block );

		if ( $attrs ) {
			$keys         = $this->getKeyConfig( $block );
			$block->attrs = $this->updateStringsRecursively( $attrs, $keys, $string_translations, $lang, $block->blockName );
		}

		return $block;
	}

	public function updateStringsRecursively( array $attrs, array $config_keys, array $translations, $lang, $block_name ) {
		foreach ( $attrs as $attr_key => $attr_value ) {
			if ( $this->isCodeAttributeItemValue( $attrs, $attr_key, (string) $block_name ) ) {
				continue;
			}

			$matching_key = $this->getMatchingConfigKey( $attr_key, $config_keys );

			if ( ! $matching_key ) {
				continue;
			}

			$is_url_encoded = is_string( $attr_value ) && $this->hasJsonEncoding( $matching_key, $config_keys );
			$decoded_json   = null;

			if ( $is_url_encoded ) {
				$decoded_json = $this->decodeUrlEncodedJson( $attr_value );
				$attr_value   = $this->decodeUrlEncodedValue( $attr_value, $decoded_json );
			}

			if ( is_array( $attr_value ) ) {
				$children_config_keys = $this->getChildrenConfigKeys( $config_keys, $matching_key );
				$translated_value     = $this->updateStringsRecursively( $attr_value, $children_config_keys, $translations, $lang, $block_name );
				$attrs[ $attr_key ]   = $is_url_encoded ? rawurlencode( wp_json_encode( $translated_value ) ) : $translated_value;

				continue;
			}

			$translated_value = $this->translateValue( $attr_value, $translations, $lang, $block_name );

			if ( ! $is_url_encoded ) {
				$attrs[ $attr_key ] = $translated_value;
			} elseif ( $translated_value !== $attr_value ) {
				$attrs[ $attr_key ] = rawurlencode( null === $decoded_json ? $translated_value : wp_json_encode( $translated_value ) );
			}
		}

		return $attrs;
	}

	private function translateValue( $value, array $translations, $lang, $block_name ) {
		$string_id = $this->get_string_id( $block_name, $value );

		if (
			isset( $translations[ $string_id ][ $lang ] ) &&
			ICL_TM_COMPLETE === (int) $translations[ $string_id ][ $lang ]['status']
		) {
			return $translations[ $string_id ][ $lang ]['value'];
		}

		return $this->updateShortcodeStrings( $value, $lang );
	}

	private function decodeUrlEncodedValue( string $encoded_value, $decoded_json ) {
		return null === $decoded_json ? rawurldecode( $encoded_value ) : $decoded_json;
	}

	private function decodeUrlEncodedJson( string $encoded_value ) {
		return json_decode( urldecode( $encoded_value ), true );
	}

	private function updateShortcodeStrings( $attr_value, $lang ) {
		if ( ! is_string( $attr_value ) || false === strpos( $attr_value, '[' ) ) {
			return $attr_value;
		}

		return apply_filters( 'wpml_pb_update_translations_in_content', $attr_value, $lang );
	}

	private function hasJsonEncoding( $matching_key, $config_keys ) {
		return 'json' === Obj::path( [ $matching_key, 'encoding' ], $config_keys );
	}

	private function getAttributes( \WP_Block_Parser_Block $block ) {
		return is_array( $block->attrs ) && $block->blockName ? $block->attrs : [];
	}

	private function getKeyConfig( \WP_Block_Parser_Block $block ) {
		$config = $this->get_block_config( $block, 'key' );

		return $config ? $config : [];
	}

	private function getMatchAllKey() {
		return [
			'*' => [
				'search-method' => \WPML_Gutenberg_Config_Option::SEARCH_METHOD_WILDCARD,
			],
		];
	}

	private function shouldSkipMediaType( array $configKeys, $matchingKey ) {
		$configType = $configKeys[ $matchingKey ]['type'] ?? null;

		return in_array(
			$configType,
			[
				\WPML_Page_Builders_Media_Gutenberg::TYPE_URL,
				\WPML_Page_Builders_Media_Gutenberg::TYPE_IDS,
			],
			true
		);
	}
}
