<?php

namespace WPML\ST\Batch\Translation;

use WPML\Core\SharedKernel\Component\String\Application\Service\StringBatchCleanupService;
use WPML\Translation\StringBatchCleanupServiceFactory;

class OrphanedBatchSweep {

	const TTL = 15 * MINUTE_IN_SECONDS;

	const MAX_ROWS_PER_PASS = 200;

	const ENDED_JOB_STATUSES = [ ICL_TM_NOT_TRANSLATED, ICL_TM_ATE_CANCELLED ];

	private $wpdb;

	private $cleanupService;

	public function __construct( \wpdb $wpdb, ?StringBatchCleanupService $cleanupService = null ) {
		$this->wpdb           = $wpdb;
		$this->cleanupService = $cleanupService;
	}

	public function run() {
		$ttl = (int) apply_filters( 'wpml_st_orphaned_batch_ttl', self::TTL );

		if ( $ttl < 1 ) {
			return 0;
		}

		return $this->returnStranded( $ttl ) + $this->releaseEnded( $ttl );
	}

	private function returnStranded( $ttl ) {
		$rows = $this->findStranded( $ttl, self::MAX_ROWS_PER_PASS );

		if ( ! $rows ) {
			return 0;
		}

		$this->resetToUntranslated( \wp_list_pluck( $rows, 'id' ) );

		\do_action( 'wpml_st_orphaned_batch_strings_recovered', $rows );

		return count( $rows );
	}

	private function releaseEnded( $ttl ) {
		$rows = $this->findEnded( $ttl, self::MAX_ROWS_PER_PASS );

		if ( ! $rows ) {
			return 0;
		}

		$byBatch = [];
		foreach ( $rows as $row ) {
			$key = (int) $row->batch_id . '|' . $row->language;
			if ( ! isset( $byBatch[ $key ] ) ) {
				$byBatch[ $key ] = [
					'batch_id' => (int) $row->batch_id,
					'language' => (string) $row->language,
					'rows'     => [],
				];
			}
			$byBatch[ $key ]['rows'][] = $row;
		}

		$released = [];
		foreach ( $byBatch as $batch ) {
			if ( ! $batch['batch_id'] || $this->hasStringInFlightElsewhere( $batch['batch_id'], $batch['language'] ) ) {
				continue;
			}

			try {
				$this->cleanupService()->cleanupBatch(
					$batch['batch_id'],
					$batch['language'],
					$this->isInFlightInAnotherLanguage( $batch['batch_id'], $batch['language'] )
				);
			} catch ( \Throwable $e ) {
				\do_action( 'wpml_st_ended_batch_release_failed', $e, $batch['batch_id'], $batch['language'] );
				continue;
			}

			$released = array_merge( $released, $batch['rows'] );
		}

		if ( $released ) {
			\do_action( 'wpml_st_ended_batch_strings_released', $released );
		}

		return count( $released );
	}

	private function findEnded( $ttl, $limit ) {
		$wpdb = $this->wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT candidate.id, candidate.string_id, candidate.language,
				COALESCE(
					( SELECT newest_original.element_id
						FROM {$wpdb->prefix}icl_translation_status newest_status
						INNER JOIN {$wpdb->prefix}icl_translations newest_target
							ON newest_target.translation_id = newest_status.translation_id
						INNER JOIN {$wpdb->prefix}icl_translations newest_original
							ON newest_original.trid = newest_target.trid
							AND newest_original.element_type = 'st-batch_strings'
							AND newest_original.source_language_code IS NULL
						WHERE newest_status.rid = candidate.newest_rid
						LIMIT 1 ),
					( SELECT MAX( any_batch.batch_id )
						FROM {$wpdb->prefix}icl_string_batches any_batch
						WHERE any_batch.string_id = candidate.string_id )
				) AS batch_id
			FROM (
				SELECT st.id, st.string_id, st.language, st.status,
					(
						SELECT MAX( batch_status.rid )
						FROM {$wpdb->prefix}icl_string_batches batch
						INNER JOIN {$wpdb->prefix}icl_translations batch_original
							ON batch_original.element_id = batch.batch_id
							AND batch_original.element_type = 'st-batch_strings'
							AND batch_original.source_language_code IS NULL
						INNER JOIN {$wpdb->prefix}icl_translations batch_target
							ON batch_target.trid = batch_original.trid
							AND batch_target.source_language_code IS NOT NULL
						INNER JOIN {$wpdb->prefix}icl_translation_status batch_status
							ON batch_status.translation_id = batch_target.translation_id
						WHERE batch.string_id = st.string_id
							AND batch_target.language_code = st.language
					) AS newest_rid
				FROM {$wpdb->prefix}icl_string_translations st
				WHERE st.status IN ( %d, %d )
					AND st.translation_date < DATE_SUB( NOW(), INTERVAL %d SECOND )
					AND EXISTS (
						SELECT 1
						FROM {$wpdb->prefix}icl_string_batches entered
						WHERE entered.string_id = st.string_id
					)
			) candidate
			LEFT JOIN {$wpdb->prefix}icl_translation_status newest
				ON newest.rid = candidate.newest_rid
			WHERE (
					candidate.newest_rid IS NULL
					AND candidate.status = %d
				)
				OR (
					candidate.newest_rid IS NOT NULL
					AND newest.status IN ( %d, %d )
				)
			ORDER BY candidate.id
			LIMIT %d
				",
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS,
				(int) $ttl,
				ICL_TM_IN_PROGRESS,
				self::ENDED_JOB_STATUSES[0],
				self::ENDED_JOB_STATUSES[1],
				(int) $limit
			)
		);
	}

	private function hasStringInFlightElsewhere( $batchId, $language ) {
		$wpdb = $this->wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1
				FROM {$wpdb->prefix}icl_string_batches mine
				INNER JOIN {$wpdb->prefix}icl_string_batches other
					ON other.string_id = mine.string_id
					AND other.batch_id <> mine.batch_id
				INNER JOIN {$wpdb->prefix}icl_translations other_original
					ON other_original.element_id = other.batch_id
					AND other_original.element_type = 'st-batch_strings'
					AND other_original.source_language_code IS NULL
				INNER JOIN {$wpdb->prefix}icl_translations other_target
					ON other_target.trid = other_original.trid
					AND other_target.source_language_code IS NOT NULL
				INNER JOIN {$wpdb->prefix}icl_translation_status other_status
					ON other_status.translation_id = other_target.translation_id
				WHERE mine.batch_id = %d
					AND other_target.language_code = %s
					AND other_status.status NOT IN ( %d, %d, %d )
				LIMIT 1",
				(int) $batchId,
				$language,
				self::ENDED_JOB_STATUSES[0],
				self::ENDED_JOB_STATUSES[1],
				ICL_TM_COMPLETE
			)
		);
	}

	private function isInFlightInAnotherLanguage( $batchId, $language ) {
		$wpdb = $this->wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1
				FROM {$wpdb->prefix}icl_translations batch_original
				INNER JOIN {$wpdb->prefix}icl_translations batch_target
					ON batch_target.trid = batch_original.trid
					AND batch_target.source_language_code IS NOT NULL
				INNER JOIN {$wpdb->prefix}icl_translation_status batch_status
					ON batch_status.translation_id = batch_target.translation_id
				WHERE batch_original.element_id = %d
					AND batch_original.element_type = 'st-batch_strings'
					AND batch_original.source_language_code IS NULL
					AND batch_target.language_code <> %s
					AND batch_status.status NOT IN ( %d, %d, %d )
				LIMIT 1",
				(int) $batchId,
				$language,
				self::ENDED_JOB_STATUSES[0],
				self::ENDED_JOB_STATUSES[1],
				ICL_TM_COMPLETE
			)
		);
	}

	private function cleanupService() {
		if ( ! $this->cleanupService ) {
			$this->cleanupService = StringBatchCleanupServiceFactory::create();
		}

		return $this->cleanupService;
	}

	private function findStranded( $ttl, $limit ) {
		$sql = "
			SELECT st.id, st.string_id, st.language
			FROM {$this->wpdb->prefix}icl_string_translations st
			WHERE st.status = %d
				AND st.translation_date < DATE_SUB( NOW(), INTERVAL %d SECOND )
				-- The 7665 parking shape provably ENTERED the batch flow: layer 5
				-- marks WAITING at batch creation, so a batch row exists by the
				-- time the job creation can raise. A WAITING string with no batch
				-- row of any kind belongs to another flow entirely (legacy
				-- pre-batch rows on upgraded sites, still awaiting a translator)
				-- and is not this sweep's business - without this guard the NOT
				-- EXISTS below is vacuously true for every one of them.
				AND EXISTS (
					SELECT 1
					FROM {$this->wpdb->prefix}icl_string_batches entered
					WHERE entered.string_id = st.string_id
				)
				AND NOT EXISTS (
					SELECT 1
					FROM {$this->wpdb->prefix}icl_string_batches batch
					INNER JOIN {$this->wpdb->prefix}icl_translations batch_original
						ON batch_original.element_id = batch.batch_id
						AND batch_original.element_type = 'st-batch_strings'
						AND batch_original.source_language_code IS NULL
					INNER JOIN {$this->wpdb->prefix}icl_translations batch_target
						ON batch_target.trid = batch_original.trid
						AND batch_target.source_language_code IS NOT NULL
					INNER JOIN {$this->wpdb->prefix}icl_translation_status batch_status
						ON batch_status.translation_id = batch_target.translation_id
					-- Both correlations live in the WHERE on purpose: MySQL does
					-- not resolve an outer reference inside a subquery ON clause.
					WHERE batch.string_id = st.string_id
						AND batch_target.language_code = st.language
				)
			ORDER BY st.id
			LIMIT %d
		";

		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare(
				$sql,
				ICL_TM_WAITING_FOR_TRANSLATOR,
				(int) $ttl,
				(int) $limit
			)
		);
	}

	private function resetToUntranslated( array $ids ) {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );

		if ( ! $ids ) {
			return;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->wpdb->prefix}icl_string_translations
					SET status = %d
					WHERE id IN ( {$placeholders} )",
				array_merge( [ ICL_TM_NOT_TRANSLATED ], $ids )
			)
		);
	}
}
