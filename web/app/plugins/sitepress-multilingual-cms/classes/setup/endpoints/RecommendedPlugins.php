<?php


namespace WPML\Setup\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;

class RecommendedPlugins implements IHandler {

	public function run( Collection $data ) {
		return Either::of( self::normalize( OTGS_Installer()->get_recommendations( 'wpml' ) ) );
	}

	public static function normalize( $recommendations ) {
		if ( ! is_array( $recommendations ) ) {
			return [ 'sections' => [], 'plugins' => [] ];
		}

		$sections = isset( $recommendations['sections'] ) && is_array( $recommendations['sections'] )
			? $recommendations['sections'] : [];
		$plugins  = isset( $recommendations['plugins'] ) && is_array( $recommendations['plugins'] )
			? $recommendations['plugins'] : [];

		foreach ( $sections as $key => $section ) {
			if ( ! is_array( $section ) || ! isset( $section['plugins'] ) || ! is_array( $section['plugins'] ) ) {
				unset( $sections[ $key ] );
				continue;
			}

			$section['plugins'] = array_intersect_key( $section['plugins'], $plugins );

			if ( ! $section['plugins'] ) {
				unset( $sections[ $key ] );
				continue;
			}

			$sections[ $key ] = $section;
		}

		return [ 'sections' => $sections, 'plugins' => $plugins ];
	}
}
