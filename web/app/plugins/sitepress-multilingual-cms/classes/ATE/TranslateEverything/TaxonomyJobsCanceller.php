<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\BatchResult;
use WPML\TM\ATE\Cancel\CancelSource;
use WPML\TM\ATE\Cancel\PendingCancels;
use WPML\TM\ATE\Hooks\JobActions;
use WPML\Translation\PreviousStateServiceFactory;

class TaxonomyJobsCanceller implements \IWPML_Backend_Action, \IWPML_REST_Action, \IWPML_AJAX_Action {
	const CANCEL_BATCH_SIZE = 1000;

	public function add_hooks() {
		add_action( 'wpml_cancel_all_automatic_jobs', [ $this, 'cancelInProgressTaxonomyJobs' ], 10 );
		add_action( 'wpml_tm_jobs_cancelled', [ $this, 'returnMembersOfCancelledBundles' ], 10 );
	}

	public function returnMembersOfCancelledBundles( $jobs ) {
		if ( is_object( $jobs ) ) {
			$jobs = [ $jobs ];
		}
		if ( ! is_array( $jobs ) ) {
			return;
		}

		global $wpdb;
		$members    = new TermBundleMembers( $wpdb );
		$memberRids = [];

		foreach ( $jobs as $job ) {
			if ( $job instanceof \WPML_TM_Post_Job_Entity ) {
				$jobId = (int) $job->get_translate_job_id();
			} else {
				$jobId = (int) ( $job->job_id ?? $job->translate_job_id ?? 0 );
			}
			if ( $jobId <= 0 ) {
				continue;
			}

			$rids = [];
			foreach ( $members->membersOf( $jobId ) as $member ) {
				if ( TermBundleMembers::STATE_IN_BUNDLE === $member['state'] ) {
					$rids[] = $member['term_rid'];
				}
			}
			if ( $rids ) {
				$members->setState( $jobId, $rids, TermBundleMembers::STATE_DROPPED );
				$memberRids = array_merge( $memberRids, $rids );
			}
		}

		if ( $memberRids ) {
			PreviousStateServiceFactory::create()->revertInFlight( $memberRids, [ ICL_TM_IN_PROGRESS ] );
		}
	}

	public function cancelInProgressTaxonomyJobs( $batchResult = null ) {
		$batchResult = $batchResult instanceof BatchResult ? $batchResult : new BatchResult();

		global $wpdb;
		$members = new TermBundleMembers( $wpdb );

		$this->retireParkedBundles( $wpdb, $members, $batchResult );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ts.rid, j.editor_job_id AS ate_job, j.job_id, t.element_type
					FROM {$wpdb->prefix}icl_translation_status ts
					INNER JOIN {$wpdb->prefix}icl_translations t
						ON t.translation_id = ts.translation_id
					INNER JOIN {$wpdb->prefix}icl_translate_job j
						ON j.rid = ts.rid
						AND j.job_id = (
							SELECT MAX( jj.job_id )
							FROM {$wpdb->prefix}icl_translate_job jj
							WHERE jj.rid = ts.rid
						)
					WHERE ( t.element_type LIKE %s OR t.element_type = %s )
						AND ts.status = %d
						AND NOT EXISTS (
							SELECT 1 FROM {$wpdb->prefix}icl_term_batch_members m
							WHERE m.term_rid = ts.rid AND m.state = %s
						)
						AND j.automatic = 1
						AND j.editor = %s
						AND j.editor_job_id IS NOT NULL
					ORDER BY j.job_id
					LIMIT %d",
				$wpdb->esc_like( 'tax_' ) . '%',
				TermBundle::ELEMENT_TYPE,
				ICL_TM_IN_PROGRESS,
				TermBundleMembers::STATE_IN_BUNDLE,
				'ate',
				self::CANCEL_BATCH_SIZE
			)
		);

		if ( empty( $rows ) ) {
			return;
		}
		if ( self::CANCEL_BATCH_SIZE === count( $rows ) ) {
			$batchResult->markHasMore();
		}

		$owed = [];
		foreach ( $rows as $row ) {
			if ( (int) $row->ate_job > 0 ) {
				$owed[ (int) $row->ate_job ] = [ 'wpml_job_id' => (int) $row->job_id, 'source' => CancelSource::TRANSLATE_EVERYTHING_OFF ];
			}
		}
		if ( $owed ) {
			PendingCancels::rememberEach( $owed, PendingCancels::ENDPOINT_HIDE, \WPML_TM_ATE_API::CANCEL_REASON_CANCELLED, true );
			do_action( PendingCancels::OWED_FOR_RUN_ACTION );
		}
		$hiddenRids = [];
		$localRids  = [];

		foreach ( $rows as $row ) {
			$ateJobId = (int) $row->ate_job;

			if ( $ateJobId > 0 ) {
				$hiddenRids[] = (int) $row->rid;
			} else {
				$localRids[] = (int) $row->rid;
			}

			if ( $this->isBundle( $row ) ) {
				$memberRids = [];
				foreach ( $members->membersOf( (int) $row->job_id ) as $member ) {
					if ( TermBundleMembers::STATE_IN_BUNDLE === $member['state'] ) {
						$memberRids[] = $member['term_rid'];
					}
				}
				if ( $ateJobId > 0 ) {
					$hiddenRids = array_merge( $hiddenRids, $memberRids );
				} else {
					$localRids = array_merge( $localRids, $memberRids );
				}
				$members->setState( (int) $row->job_id, $memberRids, TermBundleMembers::STATE_DROPPED );
			}
		}

		$previousState = PreviousStateServiceFactory::create();
		$previousState->revertInFlight( $hiddenRids, [ ICL_TM_IN_PROGRESS ], ICL_TM_ATE_CANCELLED );
		$previousState->revertInFlight( $localRids, [ ICL_TM_IN_PROGRESS ] );

		$batchResult->addProcessed( count( $hiddenRids ) + count( $localRids ) );
	}

	private function retireParkedBundles( $wpdb, TermBundleMembers $members, BatchResult $batchResult ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ts.rid, j.job_id
					FROM {$wpdb->prefix}icl_translation_status ts
					INNER JOIN {$wpdb->prefix}icl_translations t
						ON t.translation_id = ts.translation_id
					INNER JOIN {$wpdb->prefix}icl_translate_job j
						ON j.rid = ts.rid
						AND j.job_id = (
							SELECT MAX( jj.job_id )
							FROM {$wpdb->prefix}icl_translate_job jj
							WHERE jj.rid = ts.rid
						)
					WHERE t.element_type = %s
						AND ts.status = %d
						AND j.automatic = 1
						AND ( j.editor_job_id IS NULL OR j.editor_job_id = 0 )
					ORDER BY j.job_id
					LIMIT %d",
				TermBundle::ELEMENT_TYPE,
				ICL_TM_ATE_NEEDS_RETRY,
				self::CANCEL_BATCH_SIZE
			)
		);
		if ( empty( $rows ) ) {
			return;
		}
		if ( self::CANCEL_BATCH_SIZE === count( $rows ) ) {
			$batchResult->markHasMore();
		}

		$bundleRids = [];
		$memberRids = [];
		foreach ( $rows as $row ) {
			$bundleRids[] = (int) $row->rid;
			$rids         = [];
			foreach ( $members->membersOf( (int) $row->job_id ) as $member ) {
				if ( TermBundleMembers::STATE_IN_BUNDLE === $member['state'] ) {
					$rids[] = $member['term_rid'];
				}
			}
			$members->setState( (int) $row->job_id, $rids, TermBundleMembers::STATE_DROPPED );
			$memberRids = array_merge( $memberRids, $rids );
			delete_option( TaxonomyTermJobDispatcher::UNCERTAIN_OPTION_PREFIX . (int) $row->job_id );
		}

		$previousState = PreviousStateServiceFactory::create();
		$previousState->revertInFlight( $bundleRids, [ ICL_TM_ATE_NEEDS_RETRY ] );
		$previousState->revertInFlight( $memberRids, [ ICL_TM_IN_PROGRESS ] );

		$batchResult->addProcessed( count( $bundleRids ) + count( $memberRids ) );
	}

	private function isBundle( $row ) {
		return TermBundle::ELEMENT_TYPE === ( $row->element_type ?? '' );
	}
}
