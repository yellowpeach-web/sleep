<?php

namespace WPML\TM\ATE\Review;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\Core\PostTranslation\StatusSyncRule;
use WPML\Core\PostTranslation\SyncTranslationDocumentStatus;
use WPML\FP\Either;
use WPML\FP\Obj;
use WPML\LIB\WP\Post;
use WPML\LIB\WP\User;
use WPML\TM\API\Jobs;

class AcceptTranslation implements IHandler {

	public function run( Collection $data ) {
		$jobId                     = (int) $data->get( 'jobId' );
		$suppliedPostId            = $data->get( 'postId' );
		$suppliedElementTypePrefix = $data->get( 'element_type_prefix' );
		$job = Jobs::get( $jobId ) ?: TermJob::get( $jobId );

		if (
			! $job
			|| ICL_TM_COMPLETE !== (int) Obj::prop( 'status', $job )
			|| ReviewStatus::NEEDS_REVIEW !== Obj::prop( 'review_status', $job )
		) {
			return Either::left( $jobId );
		}

		$elementTypePrefix = (string) Obj::prop( 'element_type_prefix', $job );
		if (
			null !== $suppliedElementTypePrefix
			&& (string) $suppliedElementTypePrefix !== $elementTypePrefix
		) {
			return Either::left( $jobId );
		}

		if ( ! $this->isAuthorizedForJob( $job ) ) {
			return Either::left( $jobId );
		}

		if ( in_array( $elementTypePrefix, [ PackageJob::ELEMENT_TYPE_PREFIX, TermJob::ELEMENT_TYPE_PREFIX ], true ) ) {
			if ( null !== $suppliedPostId || ! $this->canTranslatePackage() ) {
				return Either::left( $jobId );
			}
		} elseif ( 'post' === $elementTypePrefix ) {
			$postId = (int) Obj::prop( 'element_id', $job );
			if (
				! $postId
				|| null === $suppliedPostId
				|| (int) $suppliedPostId !== $postId
				|| ! $this->canEditPost( $postId )
			) {
				return Either::left( $jobId );
			}

			self::setAcceptedStatus( $postId, $job );
		} else {
			return Either::left( $jobId );
		}

		Jobs::setStatus( $jobId, ICL_TM_COMPLETE );
		Jobs::setReviewStatus( $jobId, ReviewStatus::ACCEPTED );
		self::completeJobRecord( $jobId );

		return Either::of( $jobId );
	}

	private function isAuthorizedForJob( $job ) {
		if ( User::canManageTranslations() ) {
			return true;
		}

		$translatorId = (int) Obj::prop( 'translator_id', $job );
		return ! $translatorId || get_current_user_id() === $translatorId;
	}

	private function canTranslatePackage() {
		return User::canManageTranslations() || current_user_can( User::CAP_TRANSLATE ) || current_user_can( User::CAP_MANAGE_OPTIONS );
	}

	private function canEditPost( $postId ) {
		return User::canManageTranslations() || current_user_can( 'edit_post', $postId );
	}

	private static function setAcceptedStatus( $postId, $job ) {
		$originalId     = (int) Obj::prop( 'original_doc_id', $job );
		$original       = $originalId ? get_post( $originalId ) : null;
		$originalStatus = $original ? $original->post_status : null;

		if ( 'future' === $originalStatus ) {
			$status = 'future';
		} elseif ( SyncTranslationDocumentStatus::isOn() && in_array( $originalStatus, StatusSyncRule::SYNCED, true ) ) {
			$status = $originalStatus;
		} else {
			$status = 'publish';
		}

		if ( 'future' === $status ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->posts,
				[ 'post_date' => $original->post_date, 'post_date_gmt' => $original->post_date_gmt ],
				[ 'ID' => $postId ]
			);
		}

		Post::setStatusWithoutFilters( $postId, $status );

		if ( 'future' === $status ) {
			wp_clear_scheduled_hook( 'publish_future_post', [ $postId ] );
			$time = strtotime( $original->post_date_gmt . ' GMT' );
			if ( $time > time() ) {
				wp_schedule_single_event( $time, 'publish_future_post', [ $postId ] );
			}
		}
	}

	public static function completeJobRecord( $jobId ) {
		wpml_tm_get_records()
			->icl_translate_job_by_job_id( $jobId )
			->complete();

		return $jobId;
	}
}
