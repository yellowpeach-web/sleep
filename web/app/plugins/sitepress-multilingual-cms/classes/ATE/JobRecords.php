<?php

namespace WPML\TM\ATE;

use Exception;
use WPML\Collect\Support\Collection;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\ATE\JobCreation\AteCreateReconciler;
use WPML_TM_ATE_API_Error;
use WPML_TM_Editors;
use function WPML\Container\make;

class JobRecords {

	const FIELD_ATE_JOB_ID = 'ate_job_id';
	const FIELD_IS_EDITING = 'is_editing';

	private $wpdb;

	private $jobs;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
		$this->jobs = wpml_collect( [] );
	}

	public function get_data_from_ate_job_id( $ateJobId ) {
		$ateJobId = (int) $ateJobId;

		$this->warmCache( [], [ $ateJobId ] );

		$job = $this->jobs->filter(
			function( JobRecord $job ) use ( $ateJobId ) {
				return $job->ateJobId === $ateJobId;
			}
		)->sortByDesc( 'wpmlJobId' )->first();

		if ( $job ) {
			return [
				'wpml_job_id'  => $job->wpmlJobId,
				'ate_job_data' => [
					'ate_job_id' => $job->ateJobId,
				],
			];
		}

		return null;
	}

	public function store( $wpmlJobId, array $ateJobData ) {
		$ateJobData['job_id'] = (int) $wpmlJobId;

		$this->warmCache( [ $wpmlJobId ] );
		$job = $this->jobs->get( $wpmlJobId );

		if ( ! $job ) {
			$job            = new JobRecord();
			$job->wpmlJobId = (int) $wpmlJobId;
		}

		if ( isset( $ateJobData[ self::FIELD_ATE_JOB_ID ] ) ) {
			$job->ateJobId = $ateJobData[ self::FIELD_ATE_JOB_ID ];
		}

		$this->persist( $job );
	}

	public function persist( JobRecord $job ) {
		$this->jobs->put( $job->wpmlJobId, $job );

		$this->wpdb->update(
			$this->wpdb->prefix . 'icl_translate_job',
			[ 'editor_job_id' => $job->ateJobId ],
			[ 'job_id' => $job->wpmlJobId ],
			[ '%d' ],
			[ '%d' ]
		);
	}

	public function warmCache( array $wpmlJobIds, array $ateJobIds = [] ) {
		$wpdb = $this->wpdb;

		$wpmlJobIds = wpml_collect( $wpmlJobIds )->reject( $this->isAlreadyLoaded( 'wpmlJobId' ) )->toArray();
		$ateJobIds  = wpml_collect( $ateJobIds )->reject( $this->isAlreadyLoaded( 'ateJobId' ) )->toArray();

		if ( ! $wpmlJobIds && ! $ateJobIds ) {
			return;
		}

		if ( $wpmlJobIds && $ateJobIds ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT job_id, editor_job_id FROM {$wpdb->prefix}icl_translate_job
					WHERE editor = %s AND (job_id IN (" . implode( ', ', array_fill( 0, count( $wpmlJobIds ), '%d' ) ) . ')
						OR editor_job_id IN (' . implode( ', ', array_fill( 0, count( $ateJobIds ), '%d' ) ) . '))',
					array_merge( [ WPML_TM_Editors::ATE ], $wpmlJobIds, $ateJobIds )
				)
			);
		} elseif ( $wpmlJobIds ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT job_id, editor_job_id FROM {$wpdb->prefix}icl_translate_job
					WHERE editor = %s AND job_id IN (" . implode( ', ', array_fill( 0, count( $wpmlJobIds ), '%d' ) ) . ')',
					array_merge( [ WPML_TM_Editors::ATE ], $wpmlJobIds )
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT job_id, editor_job_id FROM {$wpdb->prefix}icl_translate_job
					WHERE editor = %s AND editor_job_id IN (" . implode( ', ', array_fill( 0, count( $ateJobIds ), '%d' ) ) . ')',
					array_merge( [ WPML_TM_Editors::ATE ], $ateJobIds )
				)
			);
		}

		foreach ( $rows as $row ) {
			$job = new JobRecord( $row );
			$this->jobs->put( $job->wpmlJobId, $job );
		}
	}

	private function isAlreadyLoaded( $idPropertyName ) {
		$loadedIds = $this->jobs->pluck( $idPropertyName )->values()->toArray();

		return Lst::includes( Fns::__, $loadedIds );
	}

	public function get_ate_job_id( $wpmlJobId ) {
		return $this->get( $wpmlJobId )->ateJobId;
	}

	public function is_editing_job( $wpmlJobId ) {
		return $this->get( $wpmlJobId )->isEditing();
	}

	public function get( $wpmlJobId ) {
		if ( ! $this->jobs->has( $wpmlJobId ) ) {
			$this->warmCache( [ (int) $wpmlJobId ] );
		}

		$job = $this->jobs->get( $wpmlJobId );

		if ( ! $job || ! $job->ateJobId ) {
			if ( ! $this->isCompletedRow( $wpmlJobId ) ) {
				$this->restoreJobDataFromATE( $wpmlJobId );
			}
			$job = $this->jobs->get( $wpmlJobId, new JobRecord() );
		}

		return $job;
	}

	private function isCompletedRow( $wpmlJobId ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT translated, completed_date FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d",
				(int) $wpmlJobId
			),
			ARRAY_A
		);

		return \WPML\TM\Jobs\TranslationRecord::isCompleted( $row );
	}

	private function restoreJobDataFromATE( $wpmlJobId ) {
		$data = apply_filters( 'wpml_tm_ate_job_data_fallback', [], $wpmlJobId );

		if ( $data ) {
			try {
				$this->store( $wpmlJobId, $data );
			} catch ( Exception $e ) {
				$error_log = new WPML_TM_ATE_API_Error();
				$error_log->log( $e->getMessage() );

				return;
			}

			$this->bindRestoredJob( (int) $wpmlJobId, (int) Obj::prop( self::FIELD_ATE_JOB_ID, $data ) );
		}
	}

	private function bindRestoredJob( $wpmlJobId, $ateJobId ) {
		if ( $ateJobId <= 0 ) {
			return;
		}

		$editor     = $this->editorOf( $wpmlJobId );
		$reconciler = $this->isLeftBehind( $editor ) ? $this->reconciler() : null;
		if ( $reconciler ) {
			$mode = AteCreateReconciler::markedMode( $wpmlJobId );
			if ( null === $mode ) {
				$mode = WPML_TM_Editors::ATE === $editor || Jobs::isAutomatic( $wpmlJobId ) ? 'auto' : 'manual';
			}
			$reconciler->bind( [ $wpmlJobId => $ateJobId ], [ $wpmlJobId => $mode ], null );
		}

		ApiCommunication::noteCreateConfirmed();
	}

	private function editorOf( $wpmlJobId ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT editor FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d",
				$wpmlJobId
			)
		);
	}

	private function isLeftBehind( $editor ) {
		return null === $editor || in_array( $editor, [ WPML_TM_Editors::NONE, WPML_TM_Editors::ATE ], true );
	}

	private function reconciler() {
		$ateApi  = make( \WPML_TM_ATE_API::class );
		$ateJobs = make( \WPML_TM_ATE_Jobs::class );

		return $ateApi && $ateJobs ? new AteCreateReconciler( $ateApi, $ateJobs ) : null;
	}
}
