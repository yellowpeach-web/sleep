<?php

namespace WPML\TM\ATE;

use WPML\TM\Jobs\JobLog;

class NothingToTranslateJob {

	public static function isJobModel( $model ) {
		return empty( $model->existing_ate_id )
			&& isset( $model->file )
			&& empty( $model->file->content );
	}

	public static function completeJob( $jobId ) {
		$job = wpml_tm_load_job_factory()->get_translation_job( $jobId, true );

		if ( ! $job ) {
			return false;
		}

		$data = [
			'job_id'   => $jobId,
			'fields'   => [],
			'complete' => 1,
		];

		$elements = empty( $job->elements ) ? [] : $job->elements;

		foreach ( $elements as $element ) {
			$translated = base64_decode( (string) $element->field_data_translated );
			if ( '' === $translated ) {
				$translated = base64_decode( (string) $element->field_data );
			}

			$data['fields'][ $element->field_type ] = [
				'data'       => $translated,
				'finished'   => 1,
				'tid'        => $element->tid,
				'field_type' => $element->field_type,
				'format'     => $element->field_format,
			];
		}

		kses_remove_filters();
		try {
			wpml_tm_save_data( $data, false );
		} finally {
			kses_init();
		}

		wpml_tm_load_old_jobs_editor()->set( (int) $jobId, \WPML_TM_Editors::ATE );

		JobLog::add(
			'ate_job_skipped_empty',
			[
				'job_id'      => (int) $jobId,
				'target_lang' => $job->language_code,
			]
		);

		return true;
	}
}
