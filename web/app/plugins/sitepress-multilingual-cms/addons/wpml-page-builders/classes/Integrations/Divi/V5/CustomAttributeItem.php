<?php

namespace WPML\Compatibility\Divi\V5;

use WPML\PB\Integrations\Divi\Helper;
use WPML\PB\Strings\VisibleHtmlAttributes;

final class CustomAttributeItem {

	const ITEM_KEYS = [ 'id', 'adminLabel', 'targetElement' ];

	public static function is( string $block_name, array $attrs ): bool {
		return Helper::isDiviBlockName( $block_name )
			&& isset( $attrs['name'] )
			&& is_string( $attrs['name'] )
			&& array_key_exists( 'value', $attrs )
			&& is_scalar( $attrs['value'] )
			&& self::hasItemKey( $attrs );
	}

	private static function hasItemKey( array $attrs ): bool {
		foreach ( self::ITEM_KEYS as $key ) {
			if ( array_key_exists( $key, $attrs ) ) {
				return true;
			}
		}

		return false;
	}

	public static function isTextKey( array $item, $key ): bool {
		return 'value' === $key && VisibleHtmlAttributes::matches( $item['name'] );
	}
}
