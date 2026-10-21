<?php

require_once WPML_TM_PATH . '/inc/translation-jobs/helpers/wpml-translation-job-helper.class.php';
require_once WPML_TM_PATH . '/inc/translation-jobs/helpers/wpml-translation-job-helper-with-api.class.php';
require_once WPML_TM_PATH . '/inc/translation-jobs/wpml-translation-jobs-collection.class.php';
require_once WPML_TM_PATH . '/inc/translation-jobs/helpers/wpml-save-translation-data-action.class.php';

function wpml_tm_save_job_fields_from_post( $job_id ) {
	$job = new WPML_Post_Translation_Job( $job_id );
	$job->update_fields_from_post();
}

add_action( 'wpml_save_job_fields_from_post', 'wpml_tm_save_job_fields_from_post', 10, 1 );

function wpml_tm_save_data( array $data, $redirect_after_saving = true ) {
	$job_factory      = wpml_tm_load_job_factory();
	$save_factory     = new WPML_TM_Job_Action_Factory( $job_factory );
	$save_data_action = $save_factory->save_action( $data );
	$result           = $save_data_action->save_translation();
	$redirect_target  = $redirect_after_saving ? $save_data_action->get_redirect_target() : false;
	if ( (bool) $redirect_target === true ) {
		wp_redirect( $redirect_target );
	}

	return $result;
}

function action_wpml_tm_save_data( $data ) {
	wpml_tm_save_data( $data );
}

add_action( 'wpml_save_translation_data', 'action_wpml_tm_save_data', 10, 1 );

function wpml_tm_add_translation_job( $rid, $translator_id, $translation_package, $batch_options, $sendFrom = null, $addJobLogs = false ) {

	if ( \WPML\TM\Jobs\TranslationPauseGate::refusesRid( $rid ) ) {
		\WPML\TM\Jobs\JobLog::add(
			'translation_paused_job_creation_refused',
			[ 'rid' => (int) $rid ]
		);

		return false;
	}

	$helper = new WPML_TM_Action_Helper();
	return $helper->add_translation_job( $rid, $translator_id, $translation_package, $batch_options, $sendFrom, $addJobLogs );
}

add_action( 'wpml_add_translation_job', 'wpml_tm_add_translation_job', 10, 4 );

function wpml_tm_ensure_local_translation_job( $post, $translation_id, $status, array $options = array() ) {
	global $iclTranslationManagement;

	$post           = is_numeric( $post ) ? get_post( (int) $post ) : $post;
	$translation_id = (int) $translation_id;

	if ( ! $post instanceof WP_Post || $translation_id <= 0 || ! $iclTranslationManagement instanceof TranslationManagement ) {
		return false;
	}

	$translator_id = ! empty( $options['translator_id'] ) ? (int) $options['translator_id'] : get_current_user_id();
	$service       = isset( $options['service'] ) ? (string) $options['service'] : 'local';
	$md5           = ! empty( $options['md5'] ) ? $options['md5'] : $iclTranslationManagement->post_md5( $post );

	$translation_package = $iclTranslationManagement->create_translation_package( $post->ID );

	list( $rid, $update ) = $iclTranslationManagement->update_translation_status(
		array(
			'translation_id'      => $translation_id,
			'status'              => (int) $status,
			'translator_id'       => $translator_id,
			'needs_update'        => 0,
			'md5'                 => $md5,
			'translation_service' => $service,
			'translation_package' => serialize( $translation_package ),
		)
	);

	if ( ! $update ) {
		wpml_tm_add_translation_job( $rid, $translator_id, $translation_package, array() );
	}

	return array( $rid, $update );
}

function wpml_tm_set_local_translation_status( $translation_id, $status, array $options = array() ) {
	global $iclTranslationManagement;

	$translation_id = (int) $translation_id;
	if ( $translation_id <= 0 || ! $iclTranslationManagement instanceof TranslationManagement ) {
		return false;
	}

	$translator_id = ! empty( $options['translator_id'] ) ? (int) $options['translator_id'] : get_current_user_id();
	$needs_update  = isset( $options['needs_update'] ) ? (int) $options['needs_update'] : 0;

	return $iclTranslationManagement->update_translation_status(
		array(
			'translation_id' => $translation_id,
			'status'         => (int) $status,
			'needs_update'   => $needs_update,
			'translator_id'  => $translator_id,
		)
	);
}

require_once dirname( __FILE__ ) . '/wpml-private-filters.php';

function wpml_set_job_translated_term_values( $job_id ) {
	global $sitepress;

	$delete     = $sitepress->get_setting( 'tm_block_retranslating_terms' );
	$job_object = new WPML_Post_Translation_Job( $job_id );
	$job_object->load_terms_from_post_into_job( $delete );
}

add_action( 'wpml_added_local_translation_job', 'wpml_set_job_translated_term_values' );

function wpml_tm_assign_translation_job( $job_id, $translator_id, $service, $type ) {

	if ( WPML_TM_Job_Entity::TAXONOMY_TYPE === $type ) {
		return \WPML\TM\ATE\Review\TermJob::assign( (int) $job_id, (int) $translator_id );
	}

	$job = $type === 'string'
		? new WPML_String_Translation_Job( $job_id )
		: wpml_tm_load_job_factory()->get_translation_job(
			$job_id,
			false,
			0,
			true
		);
	if ( $job ) {
		return $job->assign_to( $translator_id, $service );
	}

	return null;
}

add_action( 'wpml_tm_assign_translation_job', 'wpml_tm_assign_translation_job', 10, 4 );
