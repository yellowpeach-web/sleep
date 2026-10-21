<?php

namespace WPML\TM\Settings;

class PageUrlSlugPlan {

	const OPTION = 'wpml_page_url_slug_plan';

	const MAX_AGE_SECONDS = 21600;

	public static function save( $transition, array $translationIds ) {
		if ( ! PageUrlSlugRewrite::isRewrite( $transition ) ) {
			return;
		}

		update_option(
			self::OPTION,
			[
				'transition'     => (string) $transition,
				'translationIds' => self::ids( $translationIds ),
				'plannedAt'      => time(),
			],
			false
		);
	}

	public static function take( $transition ) {
		$plan = get_option( self::OPTION, null );
		if ( null === $plan || false === $plan ) {
			return null;
		}

		self::clear();

		if ( ! is_array( $plan ) || (string) $transition !== (string) ( isset( $plan['transition'] ) ? $plan['transition'] : '' ) ) {
			return null;
		}
		if ( time() - (int) ( isset( $plan['plannedAt'] ) ? $plan['plannedAt'] : 0 ) > self::MAX_AGE_SECONDS ) {
			return null;
		}

		return self::ids( isset( $plan['translationIds'] ) ? (array) $plan['translationIds'] : [] );
	}

	public static function clear() {
		delete_option( self::OPTION );
	}

	private static function ids( array $ids ) {
		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}
}
