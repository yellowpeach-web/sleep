<?php

namespace WPML\TM\Editor;

class ClassicEditorActions {

	public function addHooks() {
		\WPML\Request\Adapter\Ajax::register( 'wpml_save_job_ajax', \WPML\Request\Policy\Policy::capability( [ 'translate', 'manage_translations' ], \WPML\Request\Policy\Authenticity::wpmlActionNonce( 'wpml_save_job' ) ), [ $this, 'saveJob' ] );
		\WPML\Request\Adapter\AdminPost::register(
			'wpml_tm_resign_job',
			\WPML\Request\Policy\Policy::capability(
				[ 'translate', 'manage_translations' ],
				\WPML\Request\Policy\Authenticity::verifier(
					function () {
						$jobId = isset( $_GET['job_id'] ) ? (int) $_GET['job_id'] : 0;
						$nonce = isset( $_GET['nonce'] ) && is_string( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

						return $jobId > 0 && false !== wp_verify_nonce( $nonce, 'wpml_tm_resign_job_' . $jobId );
					},
					'nonce "wpml_tm_resign_job_{job_id}" in nonce (GET), bound to the job'
				)
			),
			[ $this, 'resignJob' ]
		);
	}

	public function resignJob() {
		$jobId = isset( $_GET['job_id'] ) ? (int) $_GET['job_id'] : 0;
		$nonce = isset( $_GET['nonce'] ) && is_string( $_GET['nonce'] ) ? wp_unslash( $_GET['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpml_tm_resign_job_' . $jobId ) ) {
			wp_die( 'Invalid request authenticity token', 403 );
		}

		if ( ! \WPML\TM\Jobs\Authorization\JobAuthorization::currentUserOwnsJob( $jobId ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		wpml_load_core_tm()->resign_translator( $jobId );

		wp_safe_redirect(
			admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&tab=tasks&resigned=' . $jobId ),
			302,
			'WPML'
		);
		exit;
	}

	public function saveJob() {
		if ( ! wpml_is_action_authenticated( 'wpml_save_job' ) ) {
			wp_send_json_error( 'Permission denied.' );

			return;
		}

		$data      = [];
		$post_data = \WPML_TM_Post_Data::strip_slashes_for_single_quote( $_POST['data'] );
		parse_str( $post_data, $data );

		$data = apply_filters( 'wpml_translation_editor_save_job_data', $data );

		$jobId = isset( $data['job_id'] ) ? $data['job_id'] : 0;
		if ( ! \WPML\TM\Jobs\Authorization\JobAuthorization::currentUserCanTranslateJob( $jobId ) ) {
			wp_send_json_error( 'Permission denied.' );

			return;
		}

		$authoritativeJob = wpml_tm_load_job_factory()->get_translation_job( (int) $jobId );
		if (
			! $authoritativeJob
			|| ( isset( $data['job_post_id'] ) && (int) $data['job_post_id'] !== (int) $authoritativeJob->original_doc_id )
			|| ( isset( $data['job_post_type'] ) && (string) $data['job_post_type'] !== (string) $authoritativeJob->original_post_type )
			|| ( isset( $data['target_lang'] ) && (string) $data['target_lang'] !== (string) $authoritativeJob->language_code )
		) {
			wp_send_json_error( 'Permission denied.' );

			return;
		}

		$job = \WPML\Container\make( \WPML_TM_Editor_Job_Save::class );

		$job_details = [
			'job_type'             => $authoritativeJob->original_post_type,
			'job_id'               => $authoritativeJob->original_doc_id,
			'target'               => $authoritativeJob->language_code,
			'translation_complete' => isset( $data['complete'] ) ? true : false,
			'translation_job_id'   => (int) $jobId,
		];
		$job         = apply_filters( 'wpml-translation-editor-fetch-job', $job, $job_details );

		$ajax_response = $job->save( $data );
		$ajax_response->send_json();

	}
}
