<?php

namespace WPML\Compatibility\Divi\V5;

use WPML\FP\Obj;
use WPML\PB\Gutenberg\StringsInBlock\Base;
use WPML\LIB\WP\Hooks;
use WPML\FP\Str;
use WPML\PB\Integrations\Divi\Helper;

use function WPML\FP\spreadArgs;

class DynamicContent implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const WRAPPER_KEYS = [ 'before', 'after', 'custom_text' ];

	const VARIABLE = '$variable';

	const VARIABLE_PATTERN = '/\\' . self::VARIABLE . '\((.*?)\)\$/s';

	const TOKEN_PATTERN = '/(\\' . self::VARIABLE . '\(.*?\)\$)/s';

	const TEXT_KEY = 'text';

	const PRIORITY_FOUND_STRINGS = 10;

	const TEXT_PATTERN = '/^(\s*(?:<[^>]*>\s*)*)(.*?)(\s*(?:<[^>]*>\s*)*)$/s';

	public function add_hooks() {
		Hooks::onFilter( 'wpml_found_strings_in_block', self::PRIORITY_FOUND_STRINGS, 2 )
			->then( spreadArgs( [ $this, 'replaceDynamicStringWithWrappers' ] ) );

		Hooks::onFilter( 'wpml_update_strings_in_block', 10, 3 )
			->then( spreadArgs( [ $this, 'updateStringsInBlock' ] ) );
	}

	private function parseDynamicPayload( $content ) {
		$matches = Str::match( self::VARIABLE_PATTERN, $content );
		if ( ! $matches || ! isset( $matches[1] ) ) {
			return null;
		}

		$jsonData = $matches[1];
		return json_decode( $jsonData, true );
	}

	private function getStringId( $key, $value ) {
		return md5( $key . $value );
	}

	public function replaceDynamicStringWithWrappers( array $strings, \WP_Block_Parser_Block $block ) {
		if ( ! $this->isDiviBlock( $block ) ) {
			return $strings;
		}

		$foundStrings = [];

		foreach ( $strings as $string ) {
			$segments = $this->splitDynamicString( Obj::prop( 'value', $string ) );
			if ( ! $segments ) {
				$foundStrings[] = $string;
				continue;
			}

			$name = (string) Obj::prop( 'name', $string );
			foreach ( $segments as $segment ) {
				$foundStrings = array_merge(
					$foundStrings,
					$this->isToken( $segment )
						? $this->getWrapperStrings( $segment, $name )
						: $this->getTextStrings( $segment, $name )
				);
			}
		}

		return $foundStrings;
	}

	private function splitDynamicString( $value ) {
		if ( ! is_string( $value ) || ! $this->isToken( $value ) ) {
			return null;
		}

		return preg_split( self::TOKEN_PATTERN, $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
	}

	private function isToken( string $segment ): bool {
		return (bool) preg_match( self::TOKEN_PATTERN, $segment );
	}

	private function splitText( string $segment ): array {
		preg_match( self::TEXT_PATTERN, $segment, $matches );
		if ( false === strpos( $matches[2], '<' ) ) {
			return [ $matches[1], $matches[2], $matches[3] ];
		}

		preg_match( '/^(\s*)(.*?)(\s*)$/s', $segment, $matches );

		return [ $matches[1], $matches[2], $matches[3] ];
	}

	private function getWrapperStrings( string $token, string $name ): array {
		$payload = $this->parseDynamicPayload( $token );
		if ( ! is_array( $payload ) ) {
			return [];
		}

		$strings  = [];
		$settings = $payload['value']['settings'] ?? [];
		foreach ( self::WRAPPER_KEYS as $key ) {
			$value = Obj::prop( $key, $settings );
			if ( $value && trim( $value ) ) {
				$strings[] = (object) [
					'id'    => $this->getStringId( $key, $value ),
					'name'  => $name . ': ' . $key,
					'value' => $value,
					'type'  => 'LINE',
				];
			}
		}

		return $strings;
	}

	private function getTextStrings( string $segment, string $name ): array {
		list( , $text ) = $this->splitText( $segment );
		if ( '' === $text ) {
			return [];
		}

		return [
			(object) [
				'id'    => $this->getStringId( self::TEXT_KEY, $text ),
				'name'  => $name . ': ' . self::TEXT_KEY,
				'value' => $text,
				'type'  => Base::get_string_type( $text ),
			],
		];
	}

	public function updateStringsInBlock( \WP_Block_Parser_Block $block, array $stringTranslations, $lang ) {
		if ( ! $this->isDiviBlock( $block ) ) {
			return $block;
		}

		if ( ! empty( $block->attrs ) ) {
			$block->attrs = $this->updateBlockAttrs( $block->attrs, $stringTranslations, $lang );
		}

		return $block;
	}

	private function updateBlockAttrs( array $attrs, array $stringTranslations, $lang ) {
		foreach ( $attrs as $key => $value ) {
			if ( is_array( $value ) ) {
				$attrs[ $key ] = $this->updateBlockAttrs( $value, $stringTranslations, $lang );
			} elseif ( is_string( $value ) ) {
				$attrs[ $key ] = $this->updateDynamicVariableInString( $value, $stringTranslations, $lang );
			}
		}

		return $attrs;
	}

	private function updateDynamicVariableInString( $value, array $stringTranslations, $lang ) {
		$segments = $this->splitDynamicString( $value );
		if ( ! $segments ) {
			return $value;
		}

		$updateSegment = fn( $segment ) => $this->isToken( $segment )
			? $this->updateToken( $segment, $stringTranslations, $lang )
			: $this->updateText( $segment, $stringTranslations, $lang );

		return implode( '', array_map( $updateSegment, $segments ) );
	}

	private function updateToken( string $token, array $stringTranslations, string $lang ): string {
		$payload = $this->parseDynamicPayload( $token );
		if ( ! is_array( $payload ) ) {
			return $token;
		}

		$settings = $payload['value']['settings'] ?? [];
		if ( empty( $settings ) ) {
			return $token;
		}

		foreach ( self::WRAPPER_KEYS as $key ) {
			$originalValue = $settings[ $key ] ?? '';
			if ( '' === $originalValue ) {
				continue;
			}

			$stringName = $this->getStringId( $key, $originalValue );
			if ( isset( $stringTranslations[ $stringName ][ $lang ]['value'] ) ) {
				$settings[ $key ] = $stringTranslations[ $stringName ][ $lang ]['value'];
			}
		}

		$payload['value']['settings'] = $settings;

		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS;

		return self::VARIABLE . '(' . wp_json_encode( $payload, $flags ) . ')$';
	}

	private function updateText( string $segment, array $stringTranslations, string $lang ): string {
		list( $before, $text, $after ) = $this->splitText( $segment );

		$stringId = $this->getStringId( self::TEXT_KEY, $text );
		if ( '' === $text || ! isset( $stringTranslations[ $stringId ][ $lang ]['value'] ) ) {
			return $segment;
		}

		return $before . $stringTranslations[ $stringId ][ $lang ]['value'] . $after;
	}

	private function isDiviBlock( \WP_Block_Parser_Block $block ) {
		return Helper::isDiviBlockName( (string) $block->blockName );
	}
}
