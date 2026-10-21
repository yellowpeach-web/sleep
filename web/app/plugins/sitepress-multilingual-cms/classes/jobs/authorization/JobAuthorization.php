<?php

namespace WPML\TM\Jobs\Authorization;

use WPML\LIB\WP\User;

class JobAuthorization {

	public static function currentUserCanTranslateJob( $jobId, $type = null ) {
		if ( User::canManageTranslations() ) {
			return true;
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return false;
		}

		$job = self::loadJob( $jobId, $type );

		return $job ? (bool) $job->user_can_translate( $user ) : false;
	}

	public static function currentUserOwnsJob( $jobId, $type = null ) {
		if ( User::canManageTranslations() ) {
			return true;
		}

		$job = self::loadJob( $jobId, $type );

		return $job && (int) $job->get_translator_id() === get_current_user_id();
	}

	public static function currentUserCanAssignJob( $jobId, $translatorId, $type = null ) {
		if ( User::canManageTranslations() ) {
			return true;
		}

		return (int) $translatorId === get_current_user_id()
			&& self::currentUserCanTranslateJob( $jobId, $type );
	}

	public static function currentUserCanTranslateJobEntity( \WPML_TM_Job_Entity $job ) {
		if ( User::canManageTranslations() ) {
			return true;
		}

		if ( $job instanceof \WPML_TM_Post_Job_Entity && $job->get_translate_job_id() ) {
			return self::currentUserCanTranslateJob( $job->get_translate_job_id(), $job->get_type() );
		}

		$userId = get_current_user_id();

		return $userId && (int) $job->get_translator_id() === $userId;
	}

	private static function loadJob( $jobId, $type = null ) {
		$jobId = (int) $jobId;
		if ( ! $jobId ) {
			return false;
		}

		if ( 'string' === $type ) {
			return new \WPML_String_Translation_Job( $jobId );
		}

		return wpml_tm_load_job_factory()->get_translation_job_as_active_record( $jobId );
	}
}
