<?php

namespace WPML\PB\Elementor\Config\DynamicElements;

use WPML\FP\Functor\IdentityFunctor;
use WPML\FP\Obj;
use WPML\PB\Elementor\Helper\Path;
use function WPML\FP\compose;

class LoopGrid {

	public static function get() {
		$loopIdPath = [ 'settings', 'template_id' ];

		$hasLoop = function ( $item ) use ( $loopIdPath ) {
			return Path::prop( 'widgetType', $item ) === 'loop-grid'
				&& Path::get( $loopIdPath, $item );
		};

		$loopIdLens = Obj::lensPath( $loopIdPath );

		return [ $hasLoop, $loopIdLens ];
	}

	public static function getAlternateTemplates() {
		$alternatesPath = [ 'settings', 'alternate_templates' ];

		$hasAlternateLoop = function ( $item ) use ( $alternatesPath ) {
			if ( Path::prop( 'widgetType', $item ) !== 'loop-grid' ) {
				return false;
			}

			$rows = Path::get( $alternatesPath, $item );

			return is_array( $rows ) && (bool) array_filter( $rows, [ __CLASS__, 'hasNumericTemplateId' ] );
		};

		$alternateIdsLens = compose(
			Obj::lensPath( $alternatesPath ),
			self::lensNumericTemplateIds()
		);

		return [ $hasAlternateLoop, $alternateIdsLens ];
	}

	private static function lensNumericTemplateIds() {
		return function ( $toFunctorFn ) {
			return function ( $rows ) use ( $toFunctorFn ) {
				if ( is_array( $rows ) ) {
					foreach ( $rows as $key => $row ) {
						if ( self::hasNumericTemplateId( $row ) ) {
							$rows[ $key ]['template_id'] = $toFunctorFn( $row['template_id'] )->get();
						}
					}
				}

				return IdentityFunctor::of( $rows );
			};
		};
	}

	public static function hasNumericTemplateId( $row ) {
		return is_array( $row ) && isset( $row['template_id'] ) && is_numeric( $row['template_id'] );
	}
}
