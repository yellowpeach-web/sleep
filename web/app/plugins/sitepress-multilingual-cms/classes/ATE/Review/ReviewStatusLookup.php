<?php

namespace WPML\TM\ATE\Review;

class ReviewStatusLookup {

	const CACHE_GROUP = 'wpml_review_status_for_post';

	private $query;

	private $sitepress;

	public function __construct( ReviewStatusQuery $query, \SitePress $sitepress ) {
		$this->query     = $query;
		$this->sitepress = $sitepress;
	}

	public function getJobAwaitingReview( $postId, $postType, $language ) {
		$key   = $postId . '|' . $postType . '|' . $language;
		$found = false;
		$row   = \WPML_Non_Persistent_Cache::get( $key, self::CACHE_GROUP, $found );

		if ( $found ) {
			return $row;
		}

		$row = $this->resolve( $postId, $postType, $language );

		\WPML_Non_Persistent_Cache::set( $key, $row, self::CACHE_GROUP );

		return $row;
	}

	public static function invalidate() {
		\WPML_Non_Persistent_Cache::flush_group( self::CACHE_GROUP );

		$elementTranslations = self::elementTranslations();

		if ( $elementTranslations ) {
			$elementTranslations->reload();
		}
	}

	private function resolve( $postId, $postType, $language ) {
		if ( ! $this->mayBeAwaitingReview( $postId, $postType, $language ) ) {
			return null;
		}

		$row = $this->query->getForPost( $postId, $postType, $language );

		return ReviewStatus::doesJobNeedReview( $row ) ? $row : null;
	}

	private function mayBeAwaitingReview( $postId, $postType, $language ) {
		$trid = $this->sitepress->get_element_trid( $postId, 'post_' . $postType );

		if ( ! $trid ) {
			return false;
		}

		$elementTranslations = self::elementTranslations();

		if ( ! $elementTranslations ) {
			return true;
		}

		$reviewStatus = $elementTranslations->get_translation_review_status( $trid, $language );

		return ReviewStatus::doesJobNeedReview( (object) [ 'review_status' => $reviewStatus ] );
	}

	private static function elementTranslations() {
		return function_exists( 'wpml_tm_load_element_translations' )
			? wpml_tm_load_element_translations()
			: null;
	}
}
