<?php

namespace ACFML\Helper;

class AcfPostId {

	const TYPE_POST   = 'post';
	const TYPE_TERM   = 'term';
	const TYPE_OPTION = 'option';

	public static function getType( $acfPostId ) {
		return $acfPostId ? acf_decode_post_id( $acfPostId )['type'] : null;
	}

	public static function getTermTaxonomy( $acfPostId ) {
		if ( ! $acfPostId ) {
			return null;
		}

		$decodedPostId = acf_decode_post_id( $acfPostId );

		if ( self::TYPE_TERM !== $decodedPostId['type'] ) {
			return null;
		}

		$term = get_term( (int) $decodedPostId['id'] );

		return $term instanceof \WP_Term ? $term->taxonomy : null;
	}
}
