<?php

class WPML_TM_Old_Jobs_Editor {

	const OPTION_NAME = 'wpml-old-jobs-editor';

	private $wpdb;

	private $job_factory;

	public function __construct( WPML_Translation_Job_Factory $job_factory ) {
		global $wpdb;
		$this->wpdb = $wpdb;

		$this->job_factory = $job_factory;
	}


	public function get( $job_id ) {
		$current_editor = $this->get_current_editor( $job_id );

		if ( WPML_TM_Editors::NONE === $current_editor || WPML_TM_Editors::ATE === $current_editor ) {
			return $current_editor;
		} else {
			return $this->editorForTranslationsPreviouslyCreatedUsingCTE();
		}
	}

	public function shouldStickToWPMLEditor( $job_id, $previousJob = false ) {
		$wpdb = $this->wpdb;

		$previousJobEditor = $previousJob ? $previousJob->editor : $wpdb->get_var(
			$wpdb->prepare(
				"SELECT job.editor
				FROM {$wpdb->prefix}icl_translate_job job
				WHERE job.job_id < %d AND job.rid = (
					SELECT rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %s
				)
				ORDER BY job.job_id DESC",
				$job_id,
				$job_id
			)
		);

		return $previousJobEditor === WPML_TM_Editors::WPML
		       && $this->editorForTranslationsPreviouslyCreatedUsingCTE() === WPML_TM_Editors::WPML;
	}

	public function editorForTranslationsPreviouslyCreatedUsingCTE(  ) {
		return get_option( self::OPTION_NAME, WPML_TM_Editors::WPML );
	}

	public function set( $job_id, $editor ) {
		$this->job_factory->update_job_data( $job_id, [ 'editor' => $editor ] );
	}


	public function get_current_editor( $job_id ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT editor FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d",
				$job_id
			)
		);
	}
}
