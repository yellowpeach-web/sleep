<?php

namespace WPML\PB\Gutenberg\StringsInBlock;

use WPML\FP\Str;
use WPML\PB\Gutenberg\Hooks\TextDefaultRules;

class AttributesFallback extends Base {

	const PATH_SEPARATOR    = '>';
	const CORE_NAMESPACE    = 'core';
	const ACF_NAMESPACE     = 'acf';
	const SYSTEM_KEY_PREFIX = '_';

	const REFERENCE_KEYS = [
		'divi/global-layout' => [ 'blockName', 'globalModule' ],
	];

	private $textRules;

	private $schemas = [];

	public function __construct( \WPML_Gutenberg_Config_Option $config_option, ?TextDefaultRules $textRules = null ) {
		parent::__construct( $config_option );
		$this->textRules = $textRules ?: new TextDefaultRules();
	}

	public function find( \WP_Block_Parser_Block $block ) {
		if ( ! $this->isEligible( $block ) ) {
			return [];
		}

		return $this->findStringsRecursively( $block->blockName, $block->attrs, [] );
	}

	private function findStringsRecursively( $block_name, array $attrs, array $path ) {
		$strings = [];

		foreach ( $attrs as $key => $value ) {
			if ( $this->isSystemKey( $key ) || $this->isReferenceKey( $block_name, $key, $path ) ) {
				continue;
			}

			$key_path = array_merge( $path, [ $key ] );

			if ( is_array( $value ) ) {
				$strings = array_merge( $strings, $this->findStringsRecursively( $block_name, $value, $key_path ) );
			} elseif ( $this->isTranslatableValue( $block_name, $key, $value, $path ) ) {
				$strings[] = $this->build_string(
					$this->get_string_id( $block_name, $value ),
					implode( self::PATH_SEPARATOR, $key_path ),
					$value,
					$this->getValueType( $value )
				);
			}
		}

		return $strings;
	}

	public function update( \WP_Block_Parser_Block $block, array $string_translations, $lang ) {
		if ( ! $this->isEligible( $block ) ) {
			return $block;
		}

		$block->attrs = $this->updateStringsRecursively( $block->blockName, $block->attrs, $string_translations, $lang );

		return $block;
	}

	private function updateStringsRecursively( $block_name, array $attrs, array $translations, $lang, array $path = [] ) {
		foreach ( $attrs as $key => $value ) {
			if ( $this->isSystemKey( $key ) || $this->isReferenceKey( $block_name, $key, $path ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$attrs[ $key ] = $this->updateStringsRecursively( $block_name, $value, $translations, $lang, array_merge( $path, [ $key ] ) );
			} elseif ( $this->isTranslatableValue( $block_name, $key, $value, $path ) ) {
				$attrs[ $key ] = $this->getTranslation( $translations, $this->get_string_id( $block_name, $value ), $lang, $value );
			}
		}

		return $attrs;
	}

	private function isEligible( \WP_Block_Parser_Block $block ) {
		return isset( $block->blockName )
			&& ! $this->isCoreBlock( $block )
			&& ! $this->isAcfBlock( $block )
			&& is_array( $block->attrs )
			&& $this->isInnerHtmlEmpty( $block )
			&& null === $this->get_block_config( $block, 'key' )
			&& null === $this->get_block_config( $block, 'xpath' );
	}

	private function isCoreBlock( \WP_Block_Parser_Block $block ) {
		return (bool) Str::startsWith( self::CORE_NAMESPACE . '/', $block->blockName );
	}

	private function isAcfBlock( \WP_Block_Parser_Block $block ) {
		return (bool) Str::startsWith( self::ACF_NAMESPACE . '/', $block->blockName )
			|| ( function_exists( 'acf_has_block_type' ) && acf_has_block_type( $block->blockName ) );
	}

	private function isInnerHtmlEmpty( \WP_Block_Parser_Block $block ) {
		return ! isset( $block->innerHTML ) || '' === trim( $block->innerHTML );
	}

	private function isSystemKey( $key ) {
		return is_string( $key )
			&& ( Str::startsWith( self::SYSTEM_KEY_PREFIX, $key ) || \WPML\PB\Gutenberg\BlockUid\Hooks::ATTRIBUTE === $key );
	}

	private function isReferenceKey( $block_name, $key, array $path ) {
		return [] === $path && in_array( $key, self::REFERENCE_KEYS[ $block_name ] ?? [], true );
	}

	private function isTranslatableValue( $block_name, $key, $value, array $path ) {
		$schemas = [] === $path ? $this->getSchemas( $block_name ) : [];
		$schema  = $schemas[ $key ] ?? [];

		return $this->textRules->isText( (string) $key, $value, $schema )
			&& ! $this->isVersionValue( $value );
	}

	private function getSchemas( $block_name ) {
		if ( ! isset( $this->schemas[ $block_name ] ) ) {
			$this->schemas[ $block_name ] = $this->getAttributeSchemas( $block_name );
		}

		return $this->schemas[ $block_name ];
	}

	protected function getAttributeSchemas( $block_name ) {
		if ( ! class_exists( '\WP_Block_Type_Registry' ) ) {
			return [];
		}

		$blockType = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

		return $blockType && is_array( $blockType->attributes ) ? $blockType->attributes : [];
	}

	private function isVersionValue( $value ) {
		return (bool) preg_match( '/^\d+(\.\d+)+$/', $value );
	}

	private function getValueType( $value ) {
		if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return \WPML_TM_Page_Builders::FIELD_STYLE_LINK;
		}

		return self::get_string_type( $value );
	}

	private function getTranslation( array $translations, $string_id, $lang, $original ) {
		if (
			isset( $translations[ $string_id ][ $lang ]['status'], $translations[ $string_id ][ $lang ]['value'] )
			&& ICL_TM_COMPLETE === (int) $translations[ $string_id ][ $lang ]['status']
		) {
			return $translations[ $string_id ][ $lang ]['value'];
		}

		return $original;
	}
}
