<?php

namespace ACFML\PageBuilders\BeaverBuilder;

use ACFML\Field\CompositeValue;
use ACFML\Field\LinkJobAttributes;
use ACFML\Helper\ClonedFields;
use ACFML\Helper\Fields;

class AcfBlockModule implements \IWPML_Page_Builders_Module {

	const EDITOR_TYPES = [
		'text'     => 'LINE',
		'textarea' => 'AREA',
		'wysiwyg'  => 'VISUAL',
		'url'      => 'LINK',
		'email'    => 'LINE',
	];

	const LINK_URL_KEY = 'url';

	const GROUP_TYPE = 'group';

	const LINK_TYPE = LinkJobAttributes::FIELD_TYPE;

	public function get( $nodeId, $settings, $strings ) {
		foreach ( $this->findTexts( $this->getFieldValues( $settings ) ) as $text ) {
			$strings[] = new \WPML_PB_String(
				$text['value'],
				$this->getStringName( $nodeId, $text['path'] ),
				$this->getTitle( $settings, $text['field'] ),
				$text['editorType']
			);
		}

		return $strings;
	}

	public function update( $nodeId, $settings, \WPML_PB_String $pbString ) {
		foreach ( $this->findTexts( $this->getFieldValues( $settings ) ) as $text ) {
			if ( $this->getStringName( $nodeId, $text['path'] ) === $pbString->get_name() ) {
				$settings->acf = $this->setValue( $settings->acf, $text['path'], $pbString->get_value() );
			}
		}

		return null;
	}

	private function getFieldValues( object $settings ) {
		return isset( $settings->acf ) && ( is_array( $settings->acf ) || is_object( $settings->acf ) )
			? $settings->acf
			: [];
	}

	private function findTexts( $values, array $path = [], bool $isInherited = false, ?array $cloneField = null ): array {
		$texts = [];

		foreach ( $values as $key => $value ) {
			$key = (string) $key;
			if ( Fields::LAYOUT_KEY === $key ) {
				continue;
			}

			$valuePath = array_merge( $path, [ $key ] );

			if ( $this->isFieldKey( $key ) ) {
				$texts = array_merge( $texts, $this->getTextsOfField( $key, $value, $valuePath, $isInherited, $cloneField ) );
			} elseif ( $this->isContainer( $value ) ) {
				$texts = array_merge( $texts, $this->findTexts( $value, $valuePath, $isInherited, $cloneField ) );
			}
		}

		return $texts;
	}

	private function getTextsOfField( string $fieldKey, $value, array $valuePath, bool $isInherited, ?array $cloneField = null ): array {
		$field = $this->getField( $fieldKey, $cloneField );
		if ( ! $field ) {
			return [];
		}

		$isTranslated = ! $isInherited && $this->isSetToTranslate( $field );

		if ( $this->isContainer( $value ) && ClonedFields::CLONE_TYPE === $field['type'] ) {
			return $this->findTexts( $value, $valuePath, ! $isTranslated, $field );
		}

		if ( $this->isContainer( $value ) && Fields::isWrapperOrGroup( $field ) ) {
			return $this->findTexts( $value, $valuePath, self::GROUP_TYPE === $field['type'] ? ! $isTranslated : $isInherited );
		}

		return $isTranslated ? $this->getFieldTexts( $field, $value, $valuePath ) : [];
	}

	private function getField( string $fieldKey, ?array $cloneField = null ): ?array {
		$field = $cloneField ? ClonedFields::findSubField( $cloneField, $fieldKey ) : null;
		$field = $field ?: acf_get_field( $fieldKey );

		return is_array( $field ) && isset( $field['type'] ) ? $field : null;
	}

	private function getFieldTexts( array $field, $value, array $path ): array {
		$texts = [];

		if ( self::LINK_TYPE === $field['type'] && $this->isContainer( $value ) ) {
			$link = (array) $value;
			foreach ( CompositeValue::getSubKeysForType( self::LINK_TYPE ) as $linkPart ) {
				if ( $this->isText( $link[ $linkPart ] ?? null ) ) {
					$texts[] = $this->buildText( array_merge( $path, [ $linkPart ] ), $link[ $linkPart ], $field, self::LINK_URL_KEY === $linkPart ? 'LINK' : 'LINE' );
				}
			}
		} elseif ( isset( self::EDITOR_TYPES[ $field['type'] ] ) && $this->isText( $value ) ) {
			$texts[] = $this->buildText( $path, $value, $field, self::EDITOR_TYPES[ $field['type'] ] );
		}

		return $texts;
	}

	private function buildText( array $path, string $value, array $field, string $editorType ): array {
		return [
			'path'       => $path,
			'value'      => $value,
			'field'      => $field,
			'editorType' => $editorType,
		];
	}

	private function isSetToTranslate( array $field ): bool {
		return ! isset( $field['wpml_cf_preferences'] ) || WPML_TRANSLATE_CUSTOM_FIELD === (int) $field['wpml_cf_preferences'];
	}

	private function isFieldKey( string $key ): bool {
		return 0 === strpos( $key, 'field_' );
	}

	private function isContainer( $value ): bool {
		return is_array( $value ) || is_object( $value );
	}

	private function isText( $value ): bool {
		return is_string( $value ) && '' !== trim( $value );
	}

	private function setValue( $values, array $path, string $value ) {
		$key      = array_shift( $path );
		$current  = is_object( $values ) ? ( $values->$key ?? null ) : ( $values[ $key ] ?? null );
		$newValue = $path ? $this->setValue( $current, $path, $value ) : $value;

		if ( is_object( $values ) ) {
			$values->$key = $newValue;
		} else {
			$values[ $key ] = $newValue;
		}

		return $values;
	}

	private function getStringName( $nodeId, array $path ): string {
		return 'acf-' . md5( implode( self::FIELD_SEPARATOR, $path ) ) . '-' . AcfBlockHooks::MODULE_TYPE . '-' . $nodeId;
	}

	private function getTitle( object $settings, array $field ): string {
		$blockType  = isset( $settings->acf_block_type ) && function_exists( 'acf_get_block_type' )
			? acf_get_block_type( $settings->acf_block_type )
			: null;
		$blockTitle = is_array( $blockType ) ? (string) ( $blockType['title'] ?? '' ) : '';
		$fieldLabel = (string) ( $field['label'] ?? $field['name'] ?? '' );

		return '' === $blockTitle ? $fieldLabel : $blockTitle . ': ' . $fieldLabel;
	}
}
