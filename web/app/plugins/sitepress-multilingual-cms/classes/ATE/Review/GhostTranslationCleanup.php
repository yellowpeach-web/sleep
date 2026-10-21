<?php

namespace WPML\TM\ATE\Review;

class GhostTranslationCleanup implements \IWPML_Backend_Action, \IWPML_REST_Action {

	private $detected = array();

	private $detected_jobs = array();

	public function add_hooks() {
		add_action( 'wpml_tm_ghost_translation_detected', array( $this, 'collect' ), 10, 2 );
		add_action( 'shutdown', array( $this, 'repair' ) );
	}

	public function collect( $element_id, $job_id = 0 ) {
		$element_id = (int) $element_id;
		$job_id     = (int) $job_id;

		if ( $element_id > 0 ) {
			$this->detected[ $element_id ] = $element_id;
		} elseif ( $job_id > 0 ) {
			$this->detected_jobs[ $job_id ] = $job_id;
		}
	}

	public function repair() {
		$detected            = $this->detected;
		$detected_jobs       = $this->detected_jobs;
		$this->detected      = array();
		$this->detected_jobs = array();

		if ( empty( $detected ) && empty( $detected_jobs ) ) {
			return;
		}

		if ( ! is_admin() && ! $this->is_rest_request() ) {
			return;
		}

		foreach ( $detected_jobs as $job_id ) {
			$this->remove_translation_with_no_post( $job_id );
		}

		global $wpml_post_translations;

		if ( ! $wpml_post_translations instanceof \WPML_Post_Translation ) {
			return;
		}

		foreach ( $detected as $element_id ) {
			if ( get_post( $element_id ) ) {
				continue;
			}

			if ( ! $this->is_complete( $element_id ) ) {
				continue;
			}

			$wpml_post_translations->delete_post_translation_entry( $element_id );
		}
	}

	private function remove_translation_with_no_post( $job_id ) {
		global $wpdb;

		$translation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.translation_id, t.trid, t.element_type
				FROM {$wpdb->prefix}icl_translate_job j
				INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.rid = j.rid
				INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = s.translation_id
				WHERE j.job_id = %d
					AND s.status = %d
					AND ( t.element_id IS NULL OR t.element_id = 0 )
					AND t.element_type LIKE %s
				LIMIT 1",
				$job_id,
				ICL_TM_COMPLETE,
				'post%'
			)
		);

		if ( ! $translation ) {
			return;
		}

		$update_args = array(
			'context'        => 'post',
			'trid'           => (int) $translation->trid,
			'element_type'   => $translation->element_type,
			'translation_id' => (int) $translation->translation_id,
		);

		do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'before_delete' ) ) );
		\WPML_Translation_Records_Delete::translations_by_ids( array( (int) $translation->translation_id ) );
		do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'after_delete' ) ) );
	}

	protected function is_rest_request() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	private function is_complete( $element_id ) {
		$records = wpml_tm_get_records();

		try {
			$translation_id = $records->icl_translations_by_element_id_and_type_prefix( $element_id, 'post' )->translation_id();
		} catch ( \InvalidArgumentException $e ) {
			return false;
		}

		return $translation_id
			&& ICL_TM_COMPLETE === $records->icl_translation_status_by_translation_id( $translation_id )->status();
	}
}
