<?php

namespace WPML\TM\ATE\Review;

class StringEditAcceptsReview {

	public static function afterEditOfPostString( callable $originalPostId, string $lang ) {
		$rules = ReviewRulesFactory::create();

		if ( $rules instanceof TodaysReviewRules ) {
			return;
		}

		$postId           = (int) $originalPostId();
		$translatedPostId = $postId ? (int) apply_filters( 'wpml_object_id', $postId, get_post_type( $postId ), false, $lang ) : 0;

		if ( ! $translatedPostId || $translatedPostId === $postId ) {
			return;
		}

		$statusRecord = self::statusRecordOf( $translatedPostId );

		if ( ! $statusRecord ) {
			return;
		}

		$rid = $rules->reviewAcceptedBySave( $statusRecord, true, (string) get_post_status( $translatedPostId ) );

		if ( $rid ) {
			$rules->acceptOnSave( $rid );
		}
	}

	private static function statusRecordOf( int $postId ) {
		$records = wpml_tm_get_records();

		try {
			$translationId = $records
				->icl_translations_by_element_id_and_type_prefix( $postId, 'post_' . get_post_type( $postId ) )
				->translation_id();
		} catch ( \InvalidArgumentException $e ) {
			return null;
		}

		return $translationId ? $records->icl_translation_status_by_translation_id( $translationId ) : null;
	}
}
