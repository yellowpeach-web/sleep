<?php

namespace WPML\LanguageEditor\Save\Phase;

use WPML\Translation\CancelJobsServiceFactory;

class LanguageJobs {

	const RECORD_TYPE = 'translation-jobs';

	public static function collect( array $langs ) {
		global $wpdb;

		$split = array(
			'queued'      => array(),
			'in_progress' => array(),
		);

		if ( ! $langs || ! is_object( $wpdb ) ) {
			return $split;
		}

		$in = wpml_prepare_in( $langs );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT j.job_id AS job_id, s.status AS status
				   FROM {$wpdb->prefix}icl_translations t
				   INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.translation_id = t.translation_id
				   INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = s.rid
				  WHERE t.language_code IN ({$in})
				    AND s.status IN ( %d, %d )
				    AND j.translated = 0
				    AND j.job_id = ( SELECT MAX( newest.job_id ) FROM {$wpdb->prefix}icl_translate_job newest WHERE newest.rid = j.rid )",
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS
			)
		);

		foreach ( (array) $rows as $row ) {
			$bucket = ( (int) $row->status === (int) ICL_TM_IN_PROGRESS ) ? 'in_progress' : 'queued';

			$split[ $bucket ][] = (int) $row->job_id;
		}

		return $split;
	}

	public static function cancel( array $jobIds ) {
		$jobIds = array_values( array_unique( array_filter( array_map( 'intval', $jobIds ) ) ) );

		if ( ! $jobIds ) {
			return '';
		}

		try {
			\WPML\TM\Jobs\JobLog::runInGroup(
				\WPML\TM\Jobs\JobLog::GROUP_ID_CANCELS,
				'Cancel jobs (language removed)',
				function () use ( $jobIds ) {
					CancelJobsServiceFactory::create()->cancelJobs( $jobIds, false, \WPML\TM\ATE\Cancel\CancelSource::LANGUAGE_REMOVED );
				}
			);
		} catch ( \Throwable $error ) {
			return $error->getMessage();
		}

		return '';
	}

	public static function remaining( array $langs ) {
		global $wpdb;

		if ( ! $langs || ! is_object( $wpdb ) ) {
			return 0;
		}

		$in = wpml_prepare_in( $langs );

		$remaining = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			   FROM {$wpdb->prefix}icl_translations t
			   INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.translation_id = t.translation_id
			   INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = s.rid
			  WHERE t.language_code IN ({$in})"
		);

		return $remaining;
	}

	public static function removeChunk( array $langs, $limit ) {
		global $wpdb;

		$limit = max( 1, (int) $limit );

		if ( ! $langs || ! is_object( $wpdb ) ) {
			return 0;
		}

		$in = wpml_prepare_in( $langs );

		$jobIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT j.job_id
				   FROM {$wpdb->prefix}icl_translations t
				   INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.translation_id = t.translation_id
				   INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = s.rid
				  WHERE t.language_code IN ({$in})
				  LIMIT %d",
				$limit
			)
		);

		$jobIds = array_values( array_filter( array_map( 'intval', (array) $jobIds ) ) );

		if ( ! $jobIds ) {
			return 0;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translation_status
				    SET status = %d
				  WHERE status IN ( %d, %d )
				    AND rid IN ( SELECT j.rid FROM {$wpdb->prefix}icl_translate_job j WHERE j.job_id IN ( " . implode( ',', array_fill( 0, count( $jobIds ), '%d' ) ) . ' ) )',
				array_merge( array( ICL_TM_NOT_TRANSLATED, ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ), $jobIds )
			)
		);

		\WPML_Translation_Records_Delete::jobs_by_ids( $jobIds );

		return count( $jobIds );
	}
}
