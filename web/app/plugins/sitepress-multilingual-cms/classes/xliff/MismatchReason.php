<?php

namespace WPML\TM\XLIFF;

class MismatchReason {

	const FILTERED         = 'job_id_filtered';
	const JOB_MISSING      = 'job_missing';
	const REPLACED         = 'job_replaced_and_cleaned';
	const FIELD_MISSING    = 'original_field_missing';
	const FIELD_DAMAGED    = 'original_field_damaged';
	const STATUS_MISSING   = 'status_missing';
	const OTHER_CONTENT    = 'job_translates_other_content';
	const NOT_LOADABLE     = 'job_not_loadable';
	const TRANSLATION_GONE = 'translation_row_missing';

	private $wpdb;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function sentence( $declaredJobId, $usedJobId, $loads, $md5Matches ) {
		return $this->details( $declaredJobId, $usedJobId, $loads, $md5Matches )['sentence'];
	}

	public function details( $declaredJobId, $usedJobId, $loads, $md5Matches ) {
		return $this->explain(
			function () use ( $declaredJobId, $usedJobId, $loads, $md5Matches ) {
				return $this->read( (int) $declaredJobId, (int) $usedJobId, (bool) $loads, (bool) $md5Matches );
			},
			[ self::class, 'decide' ]
		);
	}

	public function unsavedDetails( $jobId ) {
		return $this->explain(
			function () use ( $jobId ) {
				return $this->read( (int) $jobId, (int) $jobId, true, true );
			},
			[ self::class, 'decideUnsaved' ]
		);
	}

	private function explain( callable $read, callable $decide ) {
		try {
			$facts = $read();
		} catch ( \Throwable $e ) {
			return [ 'sentence' => '', 'data' => [] ];
		}

		$reason   = $decide( $facts );
		$sentence = self::describe( $reason, $facts );

		return [ 'sentence' => $sentence, 'data' => '' === $sentence ? [] : self::data( (string) $reason, $facts ) ];
	}

	public static function data( $reason, array $facts ) {
		$jobData = [];
		if ( self::originalIdIsValid( $facts ) ) {
			$jobData['original_doc_id'] = (int) $facts['original_id'];
		}
		if ( isset( $facts['translation']['element_id'] ) && (int) $facts['translation']['element_id'] > 0 ) {
			$jobData['elementId'] = (int) $facts['translation']['element_id'];
		}

		return [ 'reasonCode' => $reason, 'jobData' => $jobData ];
	}

	public function read( $declaredJobId, $usedJobId, $loads, $md5Matches ) {
		$wpdb  = $this->wpdb;
		$facts = [
			'declared_job_id'       => $declaredJobId,
			'job_id'                => $usedJobId,
			'loads'                 => $loads,
			'md5_matches'           => $md5Matches,
			'job'                   => null,
			'newest_translated_job' => null,
			'field_rows'            => 0,
			'original_id'           => null,
			'status'                => null,
			'translation'           => null,
		];

		$facts['job'] = $wpdb->get_row(
			$wpdb->prepare( "SELECT job_id, rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d", $usedJobId ),
			ARRAY_A
		);
		if ( ! $facts['job'] ) {
			return $facts;
		}

		$rid = (int) $facts['job']['rid'];
		$newestTranslated               = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT MAX(job_id) FROM {$wpdb->prefix}icl_translate_job WHERE rid = %d AND translated = 1", $rid )
		);
		$facts['newest_translated_job'] = $newestTranslated > 0 ? $newestTranslated : null;
		$facts['field_rows']            = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_translate WHERE job_id = %d", $usedJobId )
		);
		$facts['original_id']           = $wpdb->get_var(
			$wpdb->prepare( "SELECT field_data FROM {$wpdb->prefix}icl_translate WHERE job_id = %d AND field_type = 'original_id' LIMIT 1", $usedJobId )
		);
		$facts['status']                = $wpdb->get_row(
			$wpdb->prepare( "SELECT rid, translation_id FROM {$wpdb->prefix}icl_translation_status WHERE rid = %d", $rid ),
			ARRAY_A
		);
		if ( ! $facts['status'] ) {
			return $facts;
		}

		$facts['translation'] = $wpdb->get_row(
			$wpdb->prepare( "SELECT translation_id, element_id, element_type, trid, language_code FROM {$wpdb->prefix}icl_translations WHERE translation_id = %d", (int) $facts['status']['translation_id'] ),
			ARRAY_A
		);

		return $facts;
	}

	public static function decide( array $facts ) {
		if ( (int) $facts['declared_job_id'] !== (int) $facts['job_id'] ) {
			return self::FILTERED;
		}
		if ( ! $facts['job'] ) {
			return self::JOB_MISSING;
		}
		if ( null === $facts['original_id'] ) {
			$cleaned = $facts['newest_translated_job'] && (int) $facts['newest_translated_job'] > (int) $facts['job_id'] && 0 === (int) $facts['field_rows'];

			return $cleaned ? self::REPLACED : self::FIELD_MISSING;
		}
		if ( ! self::originalIdIsValid( $facts ) ) {
			return self::FIELD_DAMAGED;
		}
		if ( ! $facts['status'] ) {
			return self::STATUS_MISSING;
		}
		if ( $facts['loads'] && ! $facts['md5_matches'] ) {
			return self::OTHER_CONTENT;
		}
		if ( ! $facts['loads'] ) {
			return self::NOT_LOADABLE;
		}

		return null;
	}

	public static function decideUnsaved( array $facts ) {
		return $facts['job'] && $facts['status'] && ! $facts['translation'] ? self::TRANSLATION_GONE : null;
	}

	private static function originalIdIsValid( array $facts ) {
		return null !== $facts['original_id'] && ctype_digit( (string) $facts['original_id'] ) && (int) $facts['original_id'] > 0;
	}

	public static function describe( $reason, array $facts ) {
		$job  = (int) $facts['job_id'];
		$type = isset( $facts['translation']['element_type'] ) ? (string) $facts['translation']['element_type'] : '';

		switch ( $reason ) {
			case self::FILTERED:
				/* translators: %1$d: the job ID the translation file names, %2$d: the job ID a filter changed it to. */
				return sprintf( __( 'The file names job %1$d, but a "wpml_job_id" filter on this site changed it to job %2$d.', 'sitepress' ), (int) $facts['declared_job_id'], $job );
			case self::JOB_MISSING:
				/* translators: %d: translation job ID. */
				return sprintf( __( 'Job %d does not exist on this site.', 'sitepress' ), $job );
			case self::REPLACED:
				/* translators: %1$d: translation job ID, %2$d: the newer job ID of the same translation. */
				return sprintf( __( 'Job %1$d was replaced by job %2$d of the same translation, which removed the records of job %1$d.', 'sitepress' ), $job, (int) $facts['newest_translated_job'] );
			case self::FIELD_MISSING:
				/* translators: %d: translation job ID. */
				return sprintf( __( 'The record of which content job %d translates is missing on this site.', 'sitepress' ), $job );
			case self::FIELD_DAMAGED:
				/* translators: %d: translation job ID. */
				return sprintf( __( 'The record of which content job %d translates is damaged on this site.', 'sitepress' ), $job );
			case self::STATUS_MISSING:
				/* translators: %d: translation job ID. */
				return sprintf( __( 'The translation status record of job %d is missing on this site.', 'sitepress' ), $job );
			case self::OTHER_CONTENT:
				if ( '' === $type ) {
					/* translators: %1$d: translation job ID, %2$d: content ID. */
					return sprintf( __( 'Job %1$d now translates different content (ID %2$d) than the file was made for.', 'sitepress' ), $job, (int) $facts['original_id'] );
				}
				/* translators: %1$d: translation job ID, %2$s: content type, %3$d: content ID. */
				return sprintf( __( 'Job %1$d now translates different content (%2$s %3$d) than the file was made for.', 'sitepress' ), $job, $type, (int) $facts['original_id'] );
			case self::NOT_LOADABLE:
				/* translators: %d: translation job ID. */
				return sprintf( __( 'The records of job %d exist on this site, but the job could not be loaded.', 'sitepress' ), $job );
			case self::TRANSLATION_GONE:
				/* translators: %d: translation job ID. */
				return sprintf( __( 'The translation record of job %d is missing on this site.', 'sitepress' ), $job );
			default:
				return '';
		}
	}
}
