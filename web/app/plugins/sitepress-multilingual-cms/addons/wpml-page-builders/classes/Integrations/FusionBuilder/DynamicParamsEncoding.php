<?php

namespace WPML\Compatibility\FusionBuilder;

class DynamicParamsEncoding {

	const ATTRIBUTE = 'dynamic_params';
	const ENCODING  = 'base64';

	public static function adjustStringSettings( array $stringSettings ) {
		foreach ( $stringSettings as $settingKey => $setting ) {
			if ( ! self::isAvadaTag( (string) ( $setting['tag']['value'] ?? '' ) ) ) {
				continue;
			}

			foreach ( (array) ( $setting['attributes'] ?? [] ) as $attributeKey => $attribute ) {
				if (
					self::ATTRIBUTE === ( $attribute['value'] ?? '' )
					&& self::ENCODING === ( $attribute['encoding'] ?? '' )
				) {
					$stringSettings[ $settingKey ]['attributes'][ $attributeKey ]['encoding'] = '';
				}
			}
		}

		return $stringSettings;
	}

	private static function isAvadaTag( string $tag ): bool {
		return 0 === strpos( $tag, 'fusion_' ) || 0 === strpos( $tag, 'awb_' );
	}
}
