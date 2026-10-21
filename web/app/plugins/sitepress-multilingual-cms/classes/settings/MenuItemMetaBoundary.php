<?php

namespace WPML\TM\Settings;

use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;

class MenuItemMetaBoundary {

	const SYNC_OWNED_META_PREFIX = '_menu_item_';

	const WP_STRUCTURE_KEYS = [
		'_menu_item_type',
		'_menu_item_menu_item_parent',
		'_menu_item_object_id',
		'_menu_item_object',
		'_menu_item_target',
		'_menu_item_classes',
		'_menu_item_xfn',
		'_menu_item_url',
	];

	public static function withoutSyncOwnedKeys( array $metaKeys ) {
		return array_values( array_filter( $metaKeys, [ self::class, 'mayCopy' ] ) );
	}

	public static function mayCopy( $metaKey ) {
		$metaKey = (string) $metaKey;

		if ( 0 !== strpos( $metaKey, self::SYNC_OWNED_META_PREFIX ) ) {
			return true;
		}

		if ( self::isWordPressStructureKey( $metaKey ) ) {
			return false;
		}

		return null !== PreferenceSource::getSource( $metaKey, ElementType::POST );
	}

	public static function isWordPressStructureKey( $metaKey ) {
		return in_array( (string) $metaKey, self::WP_STRUCTURE_KEYS, true );
	}
}
