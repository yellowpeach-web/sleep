<?php

namespace ACFML\Strings\Helper;

use WPML\FP\Obj;

class ContentTypeLabels {

	public static function getLabelsInData( $data, $labelsInDataMap ) {
		return wpml_collect( $labelsInDataMap )
			->map( function( $key ) use ( $data ) {
				return Obj::propOr( '', $key, $data );
			} )
			->toArray();
	}

	public static function getLabelsInContext( $data, $context, $labelsInContextMap ) {
		return wpml_collect( $labelsInContextMap )
			->map( function( $key ) use ( $data, $context ) {
				return Obj::propOr( Obj::propOr( '', $key, $data ), $key, $context );
			} )
			->toArray();
	}

	public static function translateLabels( $objectArgs, $translatedLabels, $labelsToArgs ) {
		array_walk( $labelsToArgs, function( $label ) use ( &$objectArgs, $translatedLabels ) {
			if ( ! Obj::prop( $label, $objectArgs ) ) {
				return;
			}
			$objectArgs[ $label ] = Obj::propOr( $objectArgs[ $label ], $label, $translatedLabels );
		} );

		$labels = Obj::propOr( [], 'labels', $objectArgs );
		array_walk( $labels, function( $value, $key ) use ( &$objectArgs, $translatedLabels ) {
			$objectArgs['labels'][ $key ] = Obj::propOr( $value, $key, $translatedLabels );
		} );

		return $objectArgs;
	}

}
