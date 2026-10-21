<?php

namespace WPML\TM\ATE\Review;

use WPML\Element\API\Post as WPMLPost;
use WPML\FP\Fns;
use WPML\FP\Str;
use WPML\FP\Logic;
use WPML\FP\Lst;
use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use WPML\LIB\WP\Post;
use WPML\Setup\Option;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\JobCreation\AteCreateReconciler;
use function WPML\FP\pipe;
use function WPML\FP\spreadArgs;

class ApplyJob implements \IWPML_Backend_Action, \IWPML_REST_Action, \IWPML_AJAX_Action {

	private static $excluded_from_review = [ 'st-batch', 'package' ];

	public function add_hooks() {
		if ( \WPML_TM_ATE_Status::is_enabled_and_activated() ) {
			self::addJobStatusHook();
			self::addTranslationCompleteHook();
			self::addTranslationPreSaveHook();
		}
	}

	public static function reviewModeOf( $job ) {
		$own = is_object( $job ) ? Obj::prop( 'review_mode', $job ) : null;

		return is_string( $own ) && '' !== $own ? $own : (string) Option::getReviewMode();
	}

	private static function isReviewedUnderOwnMode( $job ) {
		return Option::NO_REVIEW !== self::reviewModeOf( $job );
	}

	private static function isHeldForReviewUnderOwnMode( $job ) {
		return Option::HOLD_FOR_REVIEW === self::reviewModeOf( $job );
	}

	private static function addJobStatusHook() {
		$applyReviewStatus = function ( $status, $job = null ) {
			if (
				$status === ICL_TM_COMPLETE
				&& ( self::shouldBeReviewed( $job ) || self::isReviewersEditOfAPost( $job ) )
			) {
				Jobs::setReviewStatus(
					(int) $job->job_id,
                    ReviewStatus::NEEDS_REVIEW
                );
			}

			return $status;
		};

		Hooks::onFilter( 'wpml_tm_applied_job_status', 10, 2 )
		     ->then( spreadArgs( $applyReviewStatus ) );
	}

	private static function addTranslationCompleteHook() {
		$isHoldToReviewMode = function ( $translatedPostId ) {
			$job = Jobs::getPostJob( $translatedPostId, Post::getType( $translatedPostId ), WPMLPost::getLang( $translatedPostId ) );

			return $job && self::isHeldForReviewUnderOwnMode( $job );
		};

		$shouldTranslationBeReviewed = function ( $translatedPostId ) {
			$job = Jobs::getPostJob( $translatedPostId, Post::getType( $translatedPostId ), WPMLPost::getLang( $translatedPostId ) );

			return $job && self::shouldBeReviewed( $job );
		};

		$isPostNewlyCreated = function ( $post ) {
			return (bool) get_post_meta(
				(int) Obj::prop( 'ID', $post ),
				\WPML_Save_Translation_Data_Action::JUST_CREATED_META,
				true
			);
		};

		$isNotNull = Logic::isNotNull();

		$setPostStatus = pipe(
			Maybe::of(),
			Fns::filter( $isHoldToReviewMode ),
			Fns::filter( $shouldTranslationBeReviewed ),
			Fns::map( Post::get() ),
			Fns::filter( $isNotNull ),
			Fns::filter( $isPostNewlyCreated ),
			Fns::map( Obj::prop( 'ID' ) ),
			Fns::map( Post::setStatus( Fns::__, 'draft' ) )
		);

		Hooks::onAction( 'wpml_pro_translation_completed' )
		     ->then( spreadArgs( $setPostStatus ) );
	}

	private static function addTranslationPreSaveHook() {
		$keepDraftPostsDraftIfNeedsReview = function ( $postArr, $job ) {
			if (
				self::shouldBeReviewed( $job )
				&& isset( $postArr['ID'] )
				&& get_post_status( $postArr['ID'] ) === 'draft'
			) {
				$postArr['post_status'] = 'draft';
			}

			return $postArr;
		};
		Hooks::onFilter( 'wpml_pre_save_pro_translation', 10, 2 )
		     ->then( spreadArgs( $keepDraftPostsDraftIfNeedsReview ) );
	}

	private static function shouldBeReviewed( $job ) {
		if ( ! $job ) {
			return false;
		}

		$isAutomatic = Obj::prop( 'automatic', $job ) || self::isBoundManualByAnAutomaticSend( $job );
		if ( ! $isAutomatic ) {
			return false;
		}

		if ( ! self::isReviewedUnderOwnMode( $job ) ) {
			return false;
		}

		$originalElementId = (int) Obj::prop( 'original_doc_id', $job );
		$isHomePage        = $originalElementId && $originalElementId == (int) get_option( 'page_for_posts' );

		if ( $isHomePage ) {
			return false;
		}

		$excluded = apply_filters(
			'wpml_tm_skip_element_type_from_review',
			self::excludeElementTypes( $job ),
			$job->original_post_type
		);

		if ( $excluded ) {
			return false;
		}

		if ( ! property_exists( $job, 'completed_date' ) || ! $job->completed_date ) {
			return true;
		}

		return time() - strtotime( $job->completed_date ) < 60;

	}

	private static function isBoundManualByAnAutomaticSend( $job ) {
		$jobId = (int) Obj::prop( 'job_id', $job );

		return $jobId > 0
			&& \WPML_TM_Editors::ATE === Obj::prop( 'editor', $job )
			&& 'auto' === AteCreateReconciler::markedMode( $jobId )
			&& AteCreateReconciler::markedByClone( $jobId );
	}

	private static function isReviewersEditOfAPost( $job ): bool {
		return is_object( $job )
			&& ReviewStatus::EDITING === Obj::prop( 'review_status', $job )
			&& Str::startsWith( 'post_', (string) Obj::prop( 'original_post_type', $job ) );
	}

	private static function excludeElementTypes( $job ): bool {
		$elementType =Obj::prop( 'original_post_type', $job );

		if ( Str::startsWith( 'st-batch', $elementType ) || Str::startsWith( 'package', $elementType ) ) {
			return true;
		}

		return false;
	}
}
