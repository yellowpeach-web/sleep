<?php

namespace WPML\TM\Settings;

class CarrierPosts {

	const FILTER = 'wpml_preference_reapply_carrier_posts';

	public static function of( array $postIds ) {
		$postIds = array_values( array_unique( array_filter( array_map( 'intval', $postIds ) ) ) );
		if ( ! $postIds ) {
			return [];
		}

		$identity = array_combine( $postIds, $postIds );

		$answer = apply_filters( self::FILTER, $identity, $postIds );

		$carriers = [];
		foreach ( $postIds as $postId ) {
			$carrier              = is_array( $answer ) && isset( $answer[ $postId ] ) ? (int) $answer[ $postId ] : 0;
			$carriers[ $postId ] = $carrier > 0 ? $carrier : $postId;
		}

		return $carriers;
	}

	public static function shared( array $carriers ) {
		$shared = [];
		foreach ( $carriers as $postId => $carrierId ) {
			if ( (int) $carrierId !== (int) $postId ) {
				$shared[ (int) $carrierId ] = true;
			}
		}

		return array_keys( $shared );
	}

	public static function isRegistered() {
		return function_exists( 'has_filter' ) && false !== has_filter( self::FILTER );
	}

	public static function countUnits( array $carriers, array &$seen ) {
		$units = 0;
		foreach ( $carriers as $postId => $carrierId ) {
			if ( (int) $carrierId === (int) $postId ) {
				$units++;
				continue;
			}
			if ( ! in_array( (int) $carrierId, $seen, true ) ) {
				$seen[] = (int) $carrierId;
				$units++;
			}
		}

		return $units;
	}
}
