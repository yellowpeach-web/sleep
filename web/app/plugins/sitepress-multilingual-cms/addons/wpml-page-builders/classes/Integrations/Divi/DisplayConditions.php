<?php

namespace WPML\Compatibility\Divi;

use WPML\Convert\Ids;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;
use WPML\FP\Obj;

class DisplayConditions implements \IWPML_Frontend_Action {

	const BASE64_EMPTY_ARRAY = 'W10=';

	const SETTINGS_ELEMENT_TYPES = [
		'categories'   => Ids::ANY_TERM,
		'tags'         => Ids::ANY_TERM,
		'dynamicPosts' => Ids::ANY_POST,
	];

	public function add_hooks() {
		Hooks::onFilter( 'et_pb_module_shortcode_attributes' )
			->then( spreadArgs( [ $this, 'translateAttributes' ] ) );
	}

	public function translateAttributes( $atts ) {
		$displayConditions = Obj::prop( 'display_conditions', $atts );

		if ( $displayConditions && self::BASE64_EMPTY_ARRAY !== $displayConditions ) {
			$conditions = json_decode( base64_decode( $displayConditions ), true );

			foreach ( $conditions as &$condition ) {
				$condition = self::translateConditionIds( $condition );
			}

			$atts['display_conditions'] = base64_encode( wp_json_encode( $conditions ) );
		}

		return $atts;
	}

	public static function translateConditionIds( $condition, $fallbackToOriginal = false ) {
		foreach ( self::SETTINGS_ELEMENT_TYPES as $setting => $elementType ) {
			if ( ! isset( $condition['conditionSettings'][ $setting ] ) || ! is_array( $condition['conditionSettings'][ $setting ] ) ) {
				continue;
			}

			foreach ( $condition['conditionSettings'][ $setting ] as &$item ) {
				if ( isset( $item['value'] ) ) {
					$item['value'] = Ids::convert( $item['value'], $elementType, $fallbackToOriginal );
				}
			}
			unset( $item );
		}

		return $condition;
	}
}
