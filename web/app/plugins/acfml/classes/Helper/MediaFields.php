<?php

namespace ACFML\Helper;

class MediaFields {

	private const FIELD_TYPES = [ 'image', 'gallery', 'file' ];

	private const ICON_PICKER = 'icon_picker';

	private const ICON_PICKER_MEDIA_LIBRARY = 'media_library';

	private const ACF_BLOCK_PREFIX = 'acf/';

	private const FIELD_KEY_PREFIX = 'field_';

	public static function getValues( $post_id ) : array {
		$values = [];
		foreach ( self::eachMetaValue( $post_id ) as $metaKey => $mediaValue ) {
			$values[ $metaKey ] = $mediaValue['raw'];
		}

		return $values;
	}

	public static function hasValues( $post_id ) : bool {
		foreach ( self::eachMetaValue( $post_id ) as $mediaValue ) {
			return true;
		}
		foreach ( self::eachBlockValue( $post_id ) as $mediaValue ) {
			return true;
		}

		return false;
	}

	public static function getAttachmentIds( $post_id ) : array {
		$ids = [];
		foreach ( self::eachMetaValue( $post_id ) as $mediaValue ) {
			$ids[] = self::attachmentIdsIn( $mediaValue['type'], maybe_unserialize( $mediaValue['raw'] ) );
		}
		foreach ( self::eachBlockValue( $post_id ) as $mediaValue ) {
			$ids[] = self::attachmentIdsIn( $mediaValue['type'], $mediaValue['raw'] );
		}

		return array_values( array_unique( array_merge( [], ...$ids ) ) );
	}

	private static function eachMetaValue( $post_id ) : \Generator {
		$postMeta = get_post_meta( $post_id );
		if ( ! is_array( $postMeta ) ) {
			return;
		}

		$fieldTypes = [];

		foreach ( $postMeta as $metaKey => $metaValue ) {
			if ( 0 === strpos( $metaKey, '_' ) ) {
				continue;
			}

			$fieldKey = (string) ( $postMeta[ '_' . $metaKey ][0] ?? '' );
			if ( ! array_key_exists( $fieldKey, $fieldTypes ) ) {
				$fieldTypes[ $fieldKey ] = self::getMediaFieldType( $fieldKey );
			}
			$fieldType = $fieldTypes[ $fieldKey ];

			if ( $fieldType && self::holdsMedia( $fieldType, $metaValue[0] ) ) {
				yield $metaKey => [ 'type' => $fieldType, 'raw' => $metaValue[0] ];
			}
		}
	}

	private static function eachBlockValue( $post_id ) : \Generator {
		$content = get_post_field( 'post_content', $post_id );
		if ( ! is_string( $content ) || ! has_blocks( $content ) ) {
			return;
		}

		foreach ( self::eachAcfBlockData( parse_blocks( $content ) ) as $blockData ) {
			foreach ( $blockData as $dataKey => $value ) {
				$fieldType = self::getMediaFieldType( self::getBlockFieldKey( $blockData, (string) $dataKey ) );
				if ( $fieldType && self::holdsMedia( $fieldType, $value ) ) {
					yield [ 'type' => $fieldType, 'raw' => $value ];
				}
			}
		}
	}

	private static function eachAcfBlockData( array $blocks ) : \Generator {
		foreach ( $blocks as $block ) {
			$blockName = $block['blockName'] ?? '';
			if ( is_string( $blockName ) && 0 === strpos( $blockName, self::ACF_BLOCK_PREFIX ) && is_array( $block['attrs']['data'] ?? null ) ) {
				yield $block['attrs']['data'];
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				yield from self::eachAcfBlockData( $block['innerBlocks'] );
			}
		}
	}

	private static function getBlockFieldKey( array $blockData, string $dataKey ) : string {
		if ( 0 === strpos( $dataKey, self::FIELD_KEY_PREFIX ) ) {
			return $dataKey;
		}
		if ( 0 === strpos( $dataKey, '_' ) ) {
			return '';
		}

		$fieldKey = $blockData[ '_' . $dataKey ] ?? '';

		return is_string( $fieldKey ) ? $fieldKey : '';
	}

	private static function getMediaFieldType( string $fieldKey ) : ?string {
		if ( 0 !== strpos( $fieldKey, self::FIELD_KEY_PREFIX ) ) {
			return null;
		}

		$field = acf_get_field( $fieldKey );
		$type  = is_array( $field ) ? ( $field['type'] ?? null ) : null;

		return in_array( $type, self::FIELD_TYPES, true ) || self::ICON_PICKER === $type ? $type : null;
	}

	private static function holdsMedia( string $fieldType, $value ) : bool {
		if ( self::ICON_PICKER !== $fieldType ) {
			return true;
		}

		$iconPicker = maybe_unserialize( $value );

		return is_array( $iconPicker ) && self::ICON_PICKER_MEDIA_LIBRARY === ( $iconPicker['type'] ?? null );
	}

	private static function attachmentIdsIn( string $fieldType, $value ) : array {
		if ( self::ICON_PICKER === $fieldType ) {
			$value = $value['value'] ?? null;
		}
		if ( ! $value ) {
			return [];
		}

		$ids = is_array( $value ) ? $value : [ $value ];

		return array_values( array_filter( array_map( 'intval', $ids ) ) );
	}
}
