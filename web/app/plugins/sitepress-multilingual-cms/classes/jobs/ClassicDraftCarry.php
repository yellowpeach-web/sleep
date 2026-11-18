<?php

namespace WPML\TM\Jobs;

class ClassicDraftCarry {

	private static $draftsByTranslation = [];

	private static $translatorStays = [];

	public static function holdsClassicWorkInProgress( $status, $editor, $service ) {
		return ICL_TM_IN_PROGRESS === (int) $status
		       && \WPML_TM_Editors::WPML === $editor
		       && ( ! $service || 'local' === $service );
	}

	public static function keep( $translationId, $jobId, $translatorStays ) {
		$job    = wpml_tm_load_job_factory()->get_translation_job( (int) $jobId );
		$drafts = [];
		foreach ( ( is_object( $job ) && isset( $job->elements ) ) ? $job->elements : [] as $element ) {
			if ( '' !== trim( (string) $element->field_data_translated ) ) {
				$drafts[ (string) $element->field_type ] = $element;
			}
		}

		self::$draftsByTranslation[ (int) $translationId ] = $drafts;
		if ( $translatorStays ) {
			self::$translatorStays[ (int) $jobId ] = true;
		}

		JobLog::add( 'classic_drafts_carried', [
			'translation_id'   => (int) $translationId,
			'job_id'           => (int) $jobId,
			'fields'           => array_keys( $drafts ),
			'translator_stays' => (bool) $translatorStays,
		] );
	}

	public static function take( $rid ) {
		if ( ! self::$draftsByTranslation ) {
			return [];
		}

		global $wpdb;
		$translationId = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT translation_id FROM {$wpdb->prefix}icl_translation_status WHERE rid = %d", (int) $rid )
		);
		$drafts        = self::$draftsByTranslation[ $translationId ] ?? [];
		unset( self::$draftsByTranslation[ $translationId ] );

		return $drafts;
	}

	public static function translatorStays( $jobId ) {
		return isset( self::$translatorStays[ (int) $jobId ] );
	}

	public static function reset() {
		self::$draftsByTranslation = [];
		self::$translatorStays     = [];
	}
}
