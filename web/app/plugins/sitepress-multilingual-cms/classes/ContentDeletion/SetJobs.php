<?php

namespace WPML\ContentDeletion;

use WPML\LanguageEditor\Save\Phase\LanguageJobs;

class SetJobs {

	public static function collect( $trid ) {
		global $wpdb;

		$split = array(
			'queued'        => array(),
			'in_progress'   => array(),
			'charged_words' => 0,
		);

		$trid = (int) $trid;

		if ( ! $trid || ! is_object( $wpdb ) ) {
			return $split;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT j.job_id AS job_id, s.status AS status, j.wpml_words_to_translate_count AS words
				   FROM {$wpdb->prefix}icl_translations t
				   INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.translation_id = t.translation_id
				   INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = s.rid
				  WHERE t.trid = %d
				    AND s.status IN ( %d, %d )
				    AND j.translated = 0
				    AND j.job_id = ( SELECT MAX( newest.job_id ) FROM {$wpdb->prefix}icl_translate_job newest WHERE newest.rid = j.rid )",
				$trid,
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS
			)
		);

		foreach ( (array) $rows as $row ) {
			if ( (int) $row->status === (int) ICL_TM_IN_PROGRESS ) {
				$split['in_progress'][]   = (int) $row->job_id;
				$split['charged_words'] += (int) $row->words;
				continue;
			}

			$split['queued'][] = (int) $row->job_id;
		}

		return $split;
	}

	public static function cancel( array $jobIds ) {
		return LanguageJobs::cancel( $jobIds );
	}
}
