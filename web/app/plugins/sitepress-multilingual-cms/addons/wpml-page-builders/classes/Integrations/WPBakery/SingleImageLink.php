<?php

namespace WPML\Compatibility\WPBakery;

use WPML_PB_Shortcode_Encoding;

class SingleImageLink {

	const TAG       = 'vc_single_image';
	const ATTRIBUTE = 'link';

	public static function addStringSettings( array $stringSettings ) {
		$isDeclared = false;

		foreach ( $stringSettings as $setting ) {
			if ( self::TAG !== ( $setting['tag']['value'] ?? '' ) ) {
				continue;
			}

			foreach ( (array) ( $setting['attributes'] ?? [] ) as $attribute ) {
				if ( self::ATTRIBUTE !== ( $attribute['value'] ?? '' ) ) {
					continue;
				}

				if ( ! empty( $attribute['encoding'] ) ) {
					return $stringSettings;
				}

				$isDeclared = true;
			}
		}

		if ( $isDeclared ) {
			$stringSettings[] = [
				'tag'        => [
					'value'          => self::TAG,
					'encoding'       => '',
					'ignore-content' => false,
				],
				'attributes' => [
					[
						'value'    => self::ATTRIBUTE,
						'encoding' => WPML_PB_Shortcode_Encoding::ENCODE_TYPES_VISUAL_COMPOSER_LINK,
						'type'     => \WPML_PB_Config_Import_Shortcode::TYPE_LINK,
						'label'    => '',
					],
				],
			];
		}

		return $stringSettings;
	}
}
