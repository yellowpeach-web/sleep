<?php

namespace WPML\TM\Taxonomy\Job;

use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\AteResponse;
use WPML\TM\ATE\Cancel\CancelSource;
use WPML\TM\ATE\TranslateEverything\TermBundleMembers;
use WPML\TM\Jobs\JobLog;
use WPML\TM\Jobs\PreviousAteJobIds;
use WPML\TranslationPolicy\FeatureSwitch;
use function WPML\Container\make;

class TermJobRowFactory {

	const EVENT_SUPERSEDED_CANCELED              = 'term_job_superseded_canceled';
	const EVENT_SUPERSEDED_CANCEL_UNCONFIRMED    = 'term_job_superseded_cancel_unconfirmed';
	const EVENT_TAXONOMY_FLIP_CANCELED           = 'term_jobs_canceled_on_taxonomy_flip';
	const EVENT_TAXONOMY_FLIP_CANCEL_UNCONFIRMED = 'term_jobs_cancel_on_taxonomy_flip_unconfirmed';
	const EVENT_MANUAL_TRANSLATION_CANCELED       = 'term_job_canceled_by_manual_translation';

	private $wpdb;

	private $userId;

	private $historyRules;

	private $ptcEngine = null;

	public function __construct( \wpdb $wpdb, ?int $userId = null, ?bool $historyRules = null ) {
		$this->wpdb   = $wpdb;
		$this->userId = null !== $userId ? $userId : (int) get_current_user_id();
		TermBundleMembers::ensureTable( $wpdb );
		$this->historyRules = $historyRules ?? FeatureSwitch::isOn();
	}

	public function resolveJobRow( int $rid, string $title, bool $reuseUnmapped = true ): int {
		$wpdb = $this->wpdb;

		$top = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT job_id, editor_job_id, translated
				 FROM {$wpdb->prefix}icl_translate_job
				 WHERE rid = %d
				 ORDER BY job_id DESC
				 LIMIT 1",
				$rid
			),
			ARRAY_A
		);

		if ( ! $top ) {
			return $this->insertJobRow( $rid, $title );
		}

		if ( empty( $top['editor_job_id'] ) && empty( $top['translated'] ) ) {
			if ( ! $reuseUnmapped ) {
				$this->stampRevision( $rid, (int) $top['job_id'] );

				return $this->insertJobRow( $rid, $title );
			}

			$this->insertedJobId = 0;

			return (int) $top['job_id'];
		}

		if ( empty( $top['translated'] ) ) {
			$this->cancelSupersededAteJob(
				(int) $top['editor_job_id'],
				(int) $top['job_id'],
				$rid,
				$this->createdByTranslateEverything ? CancelSource::SWEEP : CancelSource::RESEND
			);
		}
		$this->stampRevision( $rid, (int) $top['job_id'] );

		return $this->insertJobRow( $rid, $title );
	}

	private function previousAteJobIdsOldestFirst( int $rid, int $jobId ): array {
		$wpdb = $this->wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT editor_job_id
					 FROM {$wpdb->prefix}icl_translate_job
					 WHERE rid = %d AND job_id < %d AND editor_job_id > 0
					 ORDER BY job_id",
					$rid,
					$jobId
				)
			)
		);
	}

	public function fillBillingColumns( $jobModel, int $rid, int $jobId, int $fullCount, int $billedWords ) {
		$wpdb = $this->wpdb;

		$billed = max( 0, $billedWords );

		$jobModel->wpml_words_to_translate_count    = $billed;
		$jobModel->wpml_automatic_translation_costs = $this->automaticTranslationCosts( $jobModel, $billed );

		$jobModel->ate_previous_job_ids = $this->historyRules ? ( new PreviousAteJobIds( $wpdb ) )->of( $rid ) : $this->previousAteJobIdsOldestFirst( $rid, $jobId );

		$wpdb->update(
			$wpdb->prefix . 'icl_translate_job',
			[
				'wpml_words_to_translate_count'              => $billed,
				'wpml_words_to_translate_count_lifetime_max' => $fullCount,
				'wpml_automatic_translation_costs'           => $jobModel->wpml_automatic_translation_costs,
			],
			[ 'job_id' => $jobId ],
			[ '%d', '%d', '%d' ],
			[ '%d' ]
		);

		return $jobModel;
	}

	public function fillBundleBillingColumns( $jobModel, int $bundleJobId, array $billedByRid, array $fullCountByRid ) {
		$billed = max( 0, (int) array_sum( $billedByRid ) );
		$full   = (int) array_sum( $fullCountByRid );

		$jobModel->wpml_words_to_translate_count    = $billed;
		$jobModel->wpml_automatic_translation_costs = $this->automaticTranslationCosts( $jobModel, $billed );
		$jobModel->ate_previous_job_ids             = [];

		$this->wpdb->update(
			$this->wpdb->prefix . 'icl_translate_job',
			[
				'wpml_words_to_translate_count'              => $billed,
				'wpml_words_to_translate_count_lifetime_max' => $full,
				'wpml_automatic_translation_costs'           => $jobModel->wpml_automatic_translation_costs,
			],
			[ 'job_id' => $bundleJobId ],
			[ '%d', '%d', '%d' ],
			[ '%d' ]
		);

		return $jobModel;
	}

	public function translateEverythingWords( int $rid, int $jobId, int $full, int $change ): int {
		$paid = $this->priorLifetimeMax( $rid, $jobId );
		if ( $full <= $paid ) {
			return $this->isPtcEngine() ? 0 : $change;
		}

		return min( $change, $full - $paid );
	}

	protected function isPtcEngine(): bool {
		if ( null === $this->ptcEngine ) {
			global $wpml_dic;

			$this->ptcEngine = true;
			if ( $wpml_dic ) {
				try {
					$this->ptcEngine = (bool) $wpml_dic->make( \WPML\Core\Component\WordsToTranslate\Domain\Job\Query\PtcEngineStatusQueryInterface::class )->isDefaultEngine();
				} catch ( \Throwable $e ) {
					$this->ptcEngine = true;
				}
			}
		}

		return $this->ptcEngine;
	}

	private function priorLifetimeMax( int $rid, int $jobId ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT GREATEST(
					COALESCE( ( SELECT MAX( wpml_words_to_translate_count_lifetime_max )
						FROM {$wpdb->prefix}icl_translate_job
						WHERE rid = %d AND job_id < %d
						AND created_by_translate_everything = 1
						AND ( editor_job_id > 0 OR translated = 1 ) ), 0 ),
					COALESCE( ( SELECT MAX( m.words )
						FROM {$wpdb->prefix}icl_term_batch_members m
						INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.job_id = m.batch_job_id
						WHERE m.term_rid = %d AND m.batch_job_id < %d
						AND j.created_by_translate_everything = 1
						AND ( j.editor_job_id > 0 OR j.translated = 1 ) ), 0 )
				 )",
				$rid,
				$jobId,
				$rid,
				$jobId
			)
		);
	}

	private function automaticTranslationCosts( $jobModel, int $billed ) {
		$targetLang = isset( $jobModel->target_language->code ) ? (string) $jobModel->target_language->code : '';
		if ( '' === $targetLang ) {
			return null;
		}
		$sourceLang = isset( $jobModel->source_language->code ) ? (string) $jobModel->source_language->code : null;

		$perWord = $this->costsPerWord( $targetLang, $sourceLang );

		return false === $perWord ? null : $billed * $perWord;
	}

	protected function costsPerWord( string $targetLang, $sourceLang ) {
		global $wpml_dic;

		if ( ! $wpml_dic ) {
			return false;
		}

		try {
			$service = $wpml_dic->make( \WPML\Core\Component\WordsToTranslate\Application\Service\WordsToTranslateService::class );
			$perWord = $service->getCostsPerWordForLang( $targetLang, $sourceLang );

			return false === $perWord ? false : (int) $perWord;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	public function cancelInFlightForTaxonomy( string $taxonomy ): int {
		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT j.job_id, j.editor_job_id, ts.translation_id
				 FROM {$wpdb->prefix}icl_translate_job j
				 INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = j.rid
				 INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = ts.translation_id
				 WHERE t.element_type = %s
				 AND ts.status IN ( %d, %d )
				 AND j.revision IS NULL
				 AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->prefix}icl_term_batch_members m
					WHERE m.term_rid = ts.rid AND m.state = %s
				 )",
				'tax_' . $taxonomy,
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS,
				TermBundleMembers::STATE_IN_BUNDLE
			),
			ARRAY_A
		);
		$rows = is_array( $rows ) ? $rows : [];

		$members = $this->bundleMembers()->liveMembersOfTaxonomy( 'tax_' . $taxonomy );
		if ( $members ) {
			$this->bundleMembers()->dropTaxonomy( 'tax_' . $taxonomy );
			foreach ( $members as $member ) {
				$rows[] = [ 'job_id' => 0, 'editor_job_id' => null, 'translation_id' => $member['translation_id'] ];
			}
			$rows = array_merge( $rows, $this->emptiedBundleRows( array_unique( array_column( $members, 'batch_job_id' ) ) ) );
		}

		if ( ! $rows ) {
			return 0;
		}

		return JobLog::runInGroup(
			JobLog::GROUP_ID_MAINTENANCE,
			'Term jobs cancelled (taxonomy made not translatable)',
			function () use ( $taxonomy, $rows ) {
				return $this->cancelTaxonomyRows( $taxonomy, $rows );
			}
		);
	}

	private function emptiedBundleRows( array $batchJobIds ): array {
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT j.job_id, j.editor_job_id, ts.translation_id
				 FROM {$wpdb->prefix}icl_translate_job j
				 INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = j.rid
				 WHERE j.job_id IN ( " . implode( ', ', array_fill( 0, count( $batchJobIds ), '%d' ) ) . " )
				 AND ts.status IN ( %d, %d )
				 AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->prefix}icl_term_batch_members m
					WHERE m.batch_job_id = j.job_id AND m.state = %s
				 )",
				array_merge( array_map( 'intval', $batchJobIds ), [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS, TermBundleMembers::STATE_IN_BUNDLE ] )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	private function bundleMembers(): TermBundleMembers {
		return new TermBundleMembers( $this->wpdb );
	}

	private function cancelTaxonomyRows( string $taxonomy, array $rows ): int {
		$wpdb = $this->wpdb;

		$ateJobIds = array_values(
			array_filter(
				array_map(
					function ( $row ) {
						return (int) $row['editor_job_id'];
					},
					$rows
				)
			)
		);

		if ( $ateJobIds ) {
			$context = [
				'taxonomy'    => $taxonomy,
				'ate_job_ids' => $ateJobIds,
				'reason'      => \WPML_TM_ATE_API::CANCEL_REASON_CANCELLED,
			];

			try {
				$api       = make( \WPML_TM_ATE_API::class );
				$response  = $api->hideJobs( $ateJobIds, true, \WPML_TM_ATE_API::CANCEL_REASON_CANCELLED, [], CancelSource::TAXONOMY_UNTRANSLATED );
				$confirmed = AteResponse::getConfirmedJobIds( $response );

				if ( is_array( $confirmed ) && ! array_diff( $ateJobIds, $confirmed ) ) {
					JobLog::add( self::EVENT_TAXONOMY_FLIP_CANCELED, array_merge( $context, [ 'confirmed' => $confirmed ] ) );
				} else {
					JobLog::addError( self::EVENT_TAXONOMY_FLIP_CANCEL_UNCONFIRMED, array_merge( $context, [ 'confirmed' => $confirmed ] ) );
				}
			} catch ( \Throwable $e ) {
				JobLog::addError(
					self::EVENT_TAXONOMY_FLIP_CANCEL_UNCONFIRMED,
					array_merge( $context, [ 'error' => $e->getMessage() ] )
				);
			}
		}

		$cancelled = 0;
		foreach ( $rows as $row ) {
			$cancelled += (int) $wpdb->update(
				$wpdb->prefix . 'icl_translation_status',
				[
					'status'        => ICL_TM_ATE_CANCELLED,
					'needs_update'  => 0,
					'review_status' => null,
				],
				[ 'translation_id' => (int) $row['translation_id'] ],
				[ '%d', '%d', '%s' ],
				[ '%d' ]
			);
		}

		return $cancelled;
	}

	public function supersedeWithManualTranslation( int $translationId ): int {
		$wpdb = $this->wpdb;

		$this->bundleMembers()->dropTranslation( $translationId );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT j.job_id, j.editor_job_id, j.rid
				 FROM {$wpdb->prefix}icl_translate_job j
				 INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = j.rid
				 WHERE ts.translation_id = %d
				 AND ts.status IN ( %d, %d )
				 AND j.revision IS NULL
				 ORDER BY j.job_id DESC
				 LIMIT 1",
				$translationId,
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) || empty( $row['job_id'] ) ) {
			return 0;
		}

		return JobLog::runInGroup(
			JobLog::GROUP_ID_MAINTENANCE,
			'Term job superseded by a manual term translation',
			function () use ( $row, $translationId ) {
				return $this->supersedeJobRow( $row, $translationId );
			}
		);
	}

	private function supersedeJobRow( array $row, int $translationId ): int {
		$wpdb = $this->wpdb;

		$jobId    = (int) $row['job_id'];
		$ateJobId = (int) $row['editor_job_id'];
		$rid       = (int) $row['rid'];

		if ( $ateJobId ) {
			$this->cancelSupersededAteJob( $ateJobId, $jobId, $rid, CancelSource::MANUAL_SAVE );
		}

		$context = [
			'wpml_job_id'    => $jobId,
			'ate_job_id'     => $ateJobId,
			'rid'            => $rid,
			'translation_id' => $translationId,
		];

		if ( $this->historyRules ) {
			$context['manual_job_id'] = $this->insertManualJobRow( $rid, $jobId );
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_translate_job
					 SET translated = 1, completed_date = COALESCE( completed_date, %s )
					 WHERE job_id = %d",
					gmdate( 'Y-m-d H:i:s' ),
					$jobId
				)
			);
		}

		JobLog::add( self::EVENT_MANUAL_TRANSLATION_CANCELED, $context );

		return $jobId;
	}

	private function insertManualJobRow( int $rid, int $supersededJobId ): int {
		$wpdb = $this->wpdb;

		$this->stampRevision( $rid, $supersededJobId );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}icl_translate_job
				 ( rid, translator_id, translated, manager_id, title, editor, automatic, completed_date )
				 SELECT rid, %d, 1, %d, title, %s, 0, %s
				 FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d",
				$this->userId,
				$this->userId,
				\WPML_TM_Editors::WP,
				gmdate( 'Y-m-d H:i:s' ),
				$supersededJobId
			)
		);

		return (int) $wpdb->insert_id;
	}

	public function getInsertedJobId(): int {
		return $this->insertedJobId;
	}

	private $insertedJobId = 0;

	private $createdByTranslateEverything = false;

	private $reviewMode = null;

	private $translatorId = null;

	public function withProvenance( bool $createdByTranslateEverything, ?string $reviewMode ): self {
		$this->createdByTranslateEverything = $createdByTranslateEverything;
		$this->reviewMode                   = ( null !== $reviewMode && '' !== $reviewMode ) ? $reviewMode : null;

		return $this;
	}

	public function withTranslator( ?int $translatorId ): self {
		$this->translatorId = $translatorId;

		return $this;
	}

	private function insertJobRow( int $rid, string $title ): int {
		$wpdb = $this->wpdb;

		$row     = [
			'rid'                             => $rid,
			'translator_id'                   => $this->translatorId ?? 0,
			'manager_id'                      => $this->userId,
			'translated'                      => 0,
			'editor'                          => \WPML_TM_Editors::NONE,
			'automatic'                       => null === $this->translatorId ? 1 : 0,
			'title'                           => mb_substr( $title, 0, 160 ),
			'created_by_translate_everything' => $this->createdByTranslateEverything ? 1 : 0,
		];
		$formats = [ '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%d' ];
		if ( null !== $this->reviewMode ) {
			$row['review_mode'] = $this->reviewMode;
			$formats[]          = '%s';
		}

		$wpdb->insert( $wpdb->prefix . 'icl_translate_job', $row, $formats );

		$this->insertedJobId = (int) $wpdb->insert_id;

		return $this->insertedJobId;
	}

	private function stampRevision( int $rid, int $prevJobId ) {
		$wpdb = $this->wpdb;

		$lastRev = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(revision)
				 FROM {$wpdb->prefix}icl_translate_job
				 WHERE rid = %d AND ( revision IS NOT NULL OR translated = 1 )",
				$rid
			)
		);

		$wpdb->update(
			$wpdb->prefix . 'icl_translate_job',
			[ 'revision' => (int) $lastRev + 1 ],
			[ 'job_id' => $prevJobId ],
			[ '%d' ],
			[ '%d' ]
		);
	}

	public function cancelSupersededRemoteJob( int $ateJobId, int $wpmlJobId, int $rid ) {
		$this->cancelSupersededAteJob( $ateJobId, $wpmlJobId, $rid, CancelSource::RESEND );
	}

	private function cancelSupersededAteJob( int $ateJobId, int $wpmlJobId, int $rid, string $source ) {
		$context = [
			'ate_job_id'  => $ateJobId,
			'wpml_job_id' => $wpmlJobId,
			'rid'         => $rid,
			'reason'      => \WPML_TM_ATE_API::CANCEL_REASON_SUPERSEDED,
			'source'      => $source,
		];

		try {
			$api       = make( \WPML_TM_ATE_API::class );
			$response  = $api->hideJobs( [ $ateJobId ], true, \WPML_TM_ATE_API::CANCEL_REASON_SUPERSEDED, [], $source );
			$confirmed = AteResponse::getConfirmedJobIds( $response );

			if ( is_array( $confirmed ) && in_array( $ateJobId, $confirmed, true ) ) {
				JobLog::add( self::EVENT_SUPERSEDED_CANCELED, array_merge( $context, [ 'confirmed' => $confirmed ] ) );

				return;
			}

			JobLog::addError( self::EVENT_SUPERSEDED_CANCEL_UNCONFIRMED, $context );
		} catch ( \Throwable $e ) {
			JobLog::addError(
				self::EVENT_SUPERSEDED_CANCEL_UNCONFIRMED,
				array_merge( $context, [ 'error' => $e->getMessage() ] )
			);
		}
	}
}
