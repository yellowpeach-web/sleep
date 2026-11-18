<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\LIB\WP\Hooks;
use WPML\PB\Elementor\DataConvert;

use function WPML\FP\spreadArgs;

class FormEmailPlaceholders implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	public const STRING_NAME_PATTERN = '/^(?:email_(?:subject|content|from_name)(?:_2)?|redirect_to)-form-/';

	public const PLACEHOLDER_PATTERNS = [
		'/\[all-fields\]/',
		'/\[field[^\]]*id="\w+"[^\]]*\]/',
	];

	public const V4_STRING_NAME_PATTERN = '/^email(?:_2)?>value>(?:message|subject)>value-e-form-/';

	public const V4_SUBJECT_STRING_NAME_PATTERN = '/^email(?:_2)?>value>subject>value-e-form-/';

	public const V4_FIELD_ID_PATTERN = '/\[field[^\]]*id=["\'][^"\']+["\'][^\]]*\]/';

	public const V4_PLACEHOLDER_PATTERNS = [
		'/\[all-fields\]/',
		self::V4_FIELD_ID_PATTERN,
	];

	public const V4_REDIRECT_STRING_NAME_PATTERN = '/^redirect_url>value-e-form-/';

	public const V4_REDIRECT_PLACEHOLDER_PATTERNS = [ self::V4_FIELD_ID_PATTERN ];

	public const V4_FIELD_WIDGET_TYPES = [
		'e-form-input',
		'e-form-textarea',
		'e-form-checkbox',
		'e-form-radio-button',
		'e-form-date-picker',
		'e-form-time-picker',
		'e-form-select',
		'e-form-file-upload',
		'e-form-hidden',
	];

	public const PATTERNS_BY_STRING_NAME = [
		self::STRING_NAME_PATTERN             => self::PLACEHOLDER_PATTERNS,
		self::V4_STRING_NAME_PATTERN          => self::V4_PLACEHOLDER_PATTERNS,
		self::V4_REDIRECT_STRING_NAME_PATTERN => self::V4_REDIRECT_PLACEHOLDER_PATTERNS,
	];

	private $cssIdsByPost = [];

	public function add_hooks() {
		Hooks::onFilter( 'wpml_pb_string_placeholder_patterns', 10, 3 )
			->then(
				spreadArgs(
					function ( $patterns, $stringName = '', $postId = 0 ) {
						$patterns    = array_merge( $patterns, self::PATTERNS_BY_STRING_NAME );
						$cssIdTokens = $this->getV4CssIdPatterns( (string) $stringName, (int) $postId );

						if ( $cssIdTokens ) {
							$patterns[ '/^' . preg_quote( (string) $stringName, '/' ) . '$/' ] = $cssIdTokens;
						}

						return $patterns;
					}
				)
			);
	}

	public function getV4CssIdPatterns( string $stringName, int $postId ): array {
		if ( ! $postId || ! preg_match( self::V4_STRING_NAME_PATTERN, $stringName ) ) {
			return [];
		}

		$formId = substr( $stringName, (int) strrpos( $stringName, '-' ) + 1 );
		$cssIds = array_values( $this->getCssIdsByForm( $postId )[ $formId ] ?? [] );

		if ( ! $cssIds ) {
			return [];
		}

		return [ '/\[(?:' . implode( '|', array_map( fn( $cssId ) => preg_quote( $cssId, '/' ), $cssIds ) ) . ')\]/' ];
	}

	private function getCssIdsByForm( int $postId ): array {
		if ( ! isset( $this->cssIdsByPost[ $postId ] ) ) {
			$data = DataConvert::unserialize( get_post_meta( $postId, \WPML_Elementor_Data_Settings::META_KEY_DATA, true ) );

			$this->cssIdsByPost[ $postId ] = [];
			$this->collectCssIds( is_array( $data ) ? $data : [], null, $this->cssIdsByPost[ $postId ] );
		}

		return $this->cssIdsByPost[ $postId ];
	}

	private function collectCssIds( array $elements, ?string $formId, array &$cssIds ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$isForm  = 'e-form' === ( $element['elType'] ?? '' ) && isset( $element['id'] );
			$isField = 'widget' === ( $element['elType'] ?? '' ) && in_array( $element['widgetType'] ?? '', self::V4_FIELD_WIDGET_TYPES, true );

			if ( $formId && $isField ) {
				$cssId = $element['settings']['_cssid'] ?? '';
				$cssId = is_array( $cssId ) ? ( $cssId['value'] ?? '' ) : $cssId;

				if ( is_string( $cssId ) && '' !== $cssId && 'all-fields' !== $cssId ) {
					$cssIds[ $formId ][ $cssId ] = $cssId;
				}
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->collectCssIds( $element['elements'], $isForm ? (string) $element['id'] : $formId, $cssIds );
			}
		}
	}
}
