<?php

namespace WPML\TM\ATE\Release;

use WPML\TM\API\Jobs;
use WPML\TM\Jobs\JobLog;

class WordsLifetimeMax {

	const EVENT_LOWERED = 'lifetime_max_lowered_after_release';

	const EVENT_SKIPPED = 'lifetime_max_release_skipped';

	public static function releaseJob( int $ateJobId, int $releasedWords ): void {
		if ( $ateJobId <= 0 || $releasedWords <= 0 ) {
			return;
		}

		$job = self::readJob( $ateJobId );

		if ( ! $job ) {
			JobLog::add( self::EVENT_SKIPPED, [
				'ate_job_id'     => $ateJobId,
				'released_words' => $releasedWords,
				'reason'         => 'no_job_row',
			] );

			return;
		}

		$jobId        = (int) $job->job_id;
		$rid          = (int) $job->rid;
		$lifetimeMax  = null === $job->wpml_words_to_translate_count_lifetime_max
			? null
			: (int) $job->wpml_words_to_translate_count_lifetime_max;
		$billed       = null === $job->wpml_words_to_translate_count
			? null
			: (int) $job->wpml_words_to_translate_count;

		if ( ! $lifetimeMax ) {
			JobLog::add( self::EVENT_SKIPPED, [
				'wpml_job_id'    => $jobId,
				'ate_job_id'     => $ateJobId,
				'rid'            => $rid,
				'released_words' => $releasedWords,
				'reason'         => null === $lifetimeMax ? 'no_lifetime_max' : 'lifetime_max_already_zero',
			] );

			return;
		}

		$baseline = Jobs::previousJobsLifetimeMax( $rid, $jobId );

		$contribution = max( 0, $lifetimeMax - $baseline );

		if ( 0 === $contribution ) {
			JobLog::add( self::EVENT_SKIPPED, [
				'wpml_job_id'    => $jobId,
				'ate_job_id'     => $ateJobId,
				'rid'            => $rid,
				'baseline'       => $baseline,
				'released_words' => $releasedWords,
				'reason'         => 'nothing_above_baseline',
			] );

			return;
		}

		$isPartial     = null !== $billed && $releasedWords < $billed;
		$wordsToRemove = $isPartial ? min( $contribution, $releasedWords ) : $contribution;

		$rowsUpdated = self::lower( $rid, $jobId, $baseline, $wordsToRemove );

		JobLog::add( self::EVENT_LOWERED, [
			'wpml_job_id'    => $jobId,
			'ate_job_id'     => $ateJobId,
			'rid'            => $rid,
			'baseline'       => $baseline,
			'from'           => $lifetimeMax,
			'to'             => max( $baseline, $lifetimeMax - $wordsToRemove ),
			'released_words' => $releasedWords,
			'rows_updated'   => $rowsUpdated,
		] );
	}


	private static function readJob( $ateJobId ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT job_id, rid,
				        wpml_words_to_translate_count_lifetime_max,
				        wpml_words_to_translate_count
				 FROM {$wpdb->prefix}icl_translate_job
				 WHERE editor_job_id = %d AND automatic = 1
				 ORDER BY job_id DESC
				 LIMIT 1",
				$ateJobId
			)
		);

		return $row ?: null;
	}


	private static function lower( $rid, $jobId, $baseline, $words ) {
		global $wpdb;

		$rowsUpdated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translate_job
				 SET wpml_words_to_translate_count_lifetime_max = GREATEST(
				         %d,
				         CAST( wpml_words_to_translate_count_lifetime_max AS SIGNED ) - %d
				     )
				 WHERE rid = %d
				   AND job_id >= %d
				   AND wpml_words_to_translate_count_lifetime_max IS NOT NULL",
				$baseline,
				$words,
				$rid,
				$jobId
			)
		);

		return (int) $rowsUpdated;
	}

}
