<?php

namespace WPML\Comments\Counts;

class Exclusions {

	const HIDDEN_COMMENT_TYPES_FILTER = 'wpml_comment_counts_hidden_comment_types';

	const HIDDEN_POST_TYPES_FILTER = 'wpml_comment_counts_hidden_post_types';

	const ALWAYS_HIDDEN_COMMENT_TYPE = 'note';

	const DEFAULT_HIDDEN_COMMENT_TYPES = array( 'note', 'order_note', 'webhook_delivery', 'action_log' );

	const BUCKETS = array(
		'approved'     => '1',
		'moderated'    => '0',
		'spam'         => 'spam',
		'trash'        => 'trash',
		'post-trashed' => 'post-trashed',
	);

	private static $hidden_comment_types = null;

	private static $hidden_post_types = null;

	public static function resetRequestCache() {
		self::$hidden_comment_types = null;
		self::$hidden_post_types    = null;
	}

	public function hiddenCommentTypes() {
		if ( null === self::$hidden_comment_types ) {
			$types = apply_filters( self::HIDDEN_COMMENT_TYPES_FILTER, self::DEFAULT_HIDDEN_COMMENT_TYPES );

			$types = self::onlyStrings( is_array( $types ) ? $types : self::DEFAULT_HIDDEN_COMMENT_TYPES );
			if ( ! in_array( self::ALWAYS_HIDDEN_COMMENT_TYPE, $types, true ) ) {
				array_unshift( $types, self::ALWAYS_HIDDEN_COMMENT_TYPE );
			}

			self::$hidden_comment_types = $types;
		}

		return self::$hidden_comment_types;
	}

	public function hiddenPostTypes() {
		if ( null === self::$hidden_post_types ) {
			$default = $this->isWooCommerceActive() ? array( 'product' ) : array();

			$types = apply_filters( self::HIDDEN_POST_TYPES_FILTER, $default );

			self::$hidden_post_types = self::onlyStrings( is_array( $types ) ? $types : $default );
		}

		return self::$hidden_post_types;
	}

	public static function emptyBuckets() {
		return array_fill_keys( array_keys( self::BUCKETS ), 0 );
	}

	public static function bucketForCommentApproved( $comment_approved ) {
		$bucket = array_search( (string) $comment_approved, self::BUCKETS, true );

		return false === $bucket ? null : $bucket;
	}

	protected function isWooCommerceActive() {
		return class_exists( 'WooCommerce' );
	}

	private static function onlyStrings( array $values ) {
		return array_values( array_unique( array_filter( $values, 'is_string' ) ) );
	}
}
