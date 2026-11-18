<?php

namespace WPML\TM\ATE\Review;

use WPML\FP\Obj;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\JobRecords;
use WPML\TM\Jobs\Authorization\JobAuthorization;
use WPML\TM\Jobs\TranslationRecord;
use WPML\TM\Taxonomy\Job\TermJobRowFactory;

class TermReviewOpener {

	private $api;

	private $records;

	private $bundleReview;

	public function __construct( \WPML_TM_ATE_API $api, JobRecords $records, ?TermBundleReview $bundleReview = null ) {
		$this->api          = $api;
		$this->records      = $records;
		$this->bundleReview = $bundleReview ?: new TermBundleReview();
	}

	public function openUrl( $jobId, $returnUrl ) {
		if ( ! JobAuthorization::currentUserCanTranslateJob( (int) $jobId, \WPML_TM_Job_Entity::TAXONOMY_TYPE ) ) {
			return false;
		}

		$jobId = TranslationRecord::rowToOpen( (int) $jobId );
		$job   = TermJob::get( $jobId );
		if ( ! $job ) {
			return false;
		}

		$ateJobId = (int) Obj::prop( 'editor_job_id', $job );
		if ( ! $ateJobId ) {
			return false;
		}

		$isCompleted = ICL_TM_COMPLETE === (int) Obj::prop( 'status', $job ) || TranslationRecord::isCompleted( $job );
		if ( $isCompleted ) {
			$recordJobId = $jobId;
			$rid         = (int) Obj::prop( 'rid', $job );
			$clone = $this->api->clone_term_job( $ateJobId, $jobId, $rid, Jobs::SENT_FROM_REVIEW );
			if ( ! is_array( $clone ) || empty( $clone['id'] ) ) {
				return false;
			}

			( new TranslationRecord() )->savePreviousStateForRid( $rid );

			$requestJobId = ( new TermJobRowFactory( $GLOBALS['wpdb'], get_current_user_id() ) )
				->withTranslator( Obj::prop( 'automatic', $job ) ? null : (int) Obj::prop( 'translator_id', $job ) )
				->resolveJobRow( $rid, (string) Obj::propOr( '', 'title', $job ), false );
			if ( ! $requestJobId ) {
				return false;
			}
			$jobId = $requestJobId;

			$ateJobId = (int) $clone['id'];
			$wpdb     = $GLOBALS['wpdb'];
			$wpdb->update(
				$wpdb->prefix . 'icl_translate_job',
				[
					'editor'        => \WPML_TM_Editors::ATE,
					'editor_job_id' => $ateJobId,
					'sent_from'     => Jobs::SENT_FROM_REVIEW,
				],
				[ 'job_id' => $jobId ],
				[ '%s', '%d', '%d' ],
				[ '%d' ]
			);
			$this->records->store( $jobId, [ JobRecords::FIELD_ATE_JOB_ID => $ateJobId ] );

			if ( TermJob::isTermBundle( $job ) ) {
				$this->bundleReview->reopen( $recordJobId, $jobId );
			}
		}

		$url = $this->api->get_editor_url( $ateJobId, (string) $returnUrl );
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		Jobs::setStatus( $jobId, ICL_TM_IN_PROGRESS );
		Jobs::setReviewStatus( $jobId, ReviewStatus::EDITING );

		return $url;
	}
}
