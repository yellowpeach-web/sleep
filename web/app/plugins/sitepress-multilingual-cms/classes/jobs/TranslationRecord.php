<?php

namespace WPML\TM\Jobs;

use WPML\FP\Obj;
use WPML\LIB\WP\User;
use WPML\TM\API\Job\Map;
use WPML\TM\API\Jobs;
use WPML\Translation\PreviousStateServiceFactory;

class TranslationRecord {

	const CLOSED_CLONE = 'wpml_tm_closed_clone_';

	private $wpdb;

	public function __construct( ?\wpdb $wpdb = null ) {
		$this->wpdb = $wpdb;
	}

	public static function isCompleted( $job ) {
		if ( ! $job ) {
			return false;
		}

		if ( $job instanceof \WPML_Translation_Job ) {
			$translated    = $job->get_basic_data_property( 'translated' );
			$completedDate = $job->get_basic_data_property( 'completed_date' );
		} else {
			$translated    = Obj::prop( 'translated', $job );
			$completedDate = Obj::prop( 'completed_date', $job );
		}

		return 1 === (int) $translated || self::isDate( $completedDate );
	}

	public static function followPostTranslation( $postId, $elementType, array $fields ) {
		global $wpdb, $sitepress;

		$postId = (int) $postId;
		if ( ! $postId || ! $fields || ! $sitepress ) {
			return 0;
		}

		$details = $sitepress->get_element_language_details( $postId, (string) $elementType );
		if ( ! $details || empty( $details->source_language_code ) || empty( $details->trid ) ) {
			return 0;
		}
		if ( get_post_meta( $postId, '_icl_lang_duplicate_of', true ) ) {
			return 0;
		}

		$jobId = (int) wpml_load_core_tm()->get_translation_job_id( (int) $details->trid, $details->language_code );
		if ( ! self::isCompletedJobId( $jobId ) ) {
			return 0;
		}

		$written = 0;
		foreach ( $fields as $fieldType => $value ) {
			$written += (int) $wpdb->update(
				$wpdb->prefix . 'icl_translate',
				[
					'field_data_translated' => \WPML\Translation\TranslationElements\FieldCompression::compressAndTrack( base64_encode( (string) $value ), true, $jobId ),
				],
				[
					'job_id'     => $jobId,
					'field_type' => (string) $fieldType,
				]
			);
		}

		return $written;
	}

	public static function followStringTranslation( $stringTranslationId, $value ) {
		global $wpdb, $sitepress;

		if ( ! $sitepress || ! \WPML\Upgrade\SchemaFacts::hasTable( 'icl_string_packages' ) ) {
			return false;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT st.string_id, st.language, s.string_package_id, p.post_id
				FROM {$wpdb->prefix}icl_string_translations st
				JOIN {$wpdb->prefix}icl_strings s ON s.id = st.string_id
				JOIN {$wpdb->prefix}icl_string_packages p ON p.ID = s.string_package_id
				WHERE st.id = %d",
				(int) $stringTranslationId
			)
		);
		if ( ! $row || ! (int) $row->post_id ) {
			return false;
		}

		$postType = get_post_type( (int) $row->post_id );
		$trid     = $postType ? (int) $sitepress->get_element_trid( (int) $row->post_id, 'post_' . $postType ) : 0;
		$jobId    = $trid ? (int) wpml_load_core_tm()->get_translation_job_id( $trid, $row->language ) : 0;
		if ( ! self::isCompletedJobId( $jobId ) ) {
			return false;
		}

		return (bool) $wpdb->update(
			$wpdb->prefix . 'icl_translate',
			[
				'field_data_translated' => \WPML\Translation\TranslationElements\FieldCompression::compressAndTrack( base64_encode( (string) $value ), true, $jobId ),
			],
			[
				'job_id'     => $jobId,
				'field_type' => 'package-string-' . (int) $row->string_package_id . '-' . (int) $row->string_id,
			]
		);
	}

	public static function isCompletedJobId( $jobId ) {
		global $wpdb;

		$jobId = (int) $jobId;
		if ( $jobId <= 0 ) {
			return false;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT translated, completed_date FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d",
				$jobId
			),
			ARRAY_A
		);

		return self::isCompleted( $row );
	}

	public function discardRequest( $requestJobId, $recordJobId ) {
		$recordJobId = (int) $recordJobId;
		if ( $recordJobId <= 0 ) {
			return false;
		}

		$request = $this->openRequestOnRecord( $requestJobId, $recordJobId );
		if ( ! $request || (int) Obj::prop( 'editor_job_id', $request ) > 0 || ! $this->dropRequest( $request ) ) {
			return false;
		}

		$translationId = $this->translationIdOf( (int) $request['rid'] );
		if ( $translationId > 0 ) {
			$this->restoreOrClearPreviousState( $translationId );
		}

		JobLog::add( 'abandoned_request_row_discarded', [
			'request_job_id' => (int) $request['job_id'],
			'record_job_id'  => $recordJobId,
			'rid'            => (int) $request['rid'],
		] );

		return true;
	}

	public function closeRequest( $requestJobId, $recordJobId = 0 ) {
		$request = $this->openRequestOnRecord( $requestJobId, (int) $recordJobId );
		if ( ! $request || ! $this->dropRequest( $request ) ) {
			return false;
		}

		$this->cancelInATE( $request );

		$translationId = $this->translationIdOf( (int) $request['rid'] );
		if ( $translationId > 0 ) {
			$this->restoreOrClearPreviousState( $translationId );
			$this->db()->query(
				$this->db()->prepare(
					"UPDATE {$this->db()->prefix}icl_translation_status SET status = %d, translator_id = %d WHERE translation_id = %d AND status IN ( %d, %d )",
					ICL_TM_COMPLETE,
					(int) Obj::prop( 'translator_id', $request['record'] ),
					$translationId,
					ICL_TM_WAITING_FOR_TRANSLATOR,
					ICL_TM_IN_PROGRESS
				)
			);
		}

		JobLog::add( 'request_closed_on_record', [
			'request_job_id' => (int) $request['job_id'],
			'record_job_id'  => (int) Obj::prop( 'job_id', $request['record'] ),
			'rid'            => (int) $request['rid'],
			'ate_job_id'     => (int) Obj::prop( 'editor_job_id', $request ),
		] );

		return true;
	}

	public function closeOpenOfRecord( $requestJobId, $recordJobId = 0, $evenIfOutOfDate = false, $onlyOfRecordsTranslator = false ) {
		$wpdb = $this->db();
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT j.sent_from, j.translator_id, s.translation_id FROM {$wpdb->prefix}icl_translate_job j
				 INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.rid = j.rid
				 WHERE j.job_id = %d",
				(int) $requestJobId
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) || ! in_array( (int) $row['sent_from'], [ Jobs::SENT_MANUALLY, Jobs::SENT_FROM_REVIEW ], true ) ) {
			return false;
		}

		if ( $onlyOfRecordsTranslator ) {
			$record = Jobs::getPreviousJob( (int) $requestJobId );
			if ( ! $record || (int) Obj::prop( 'translator_id', $record ) !== (int) $row['translator_id'] ) {
				return false;
			}
		}

		$saved = PreviousStateServiceFactory::create()->get( (int) $row['translation_id'] );
		if ( ! $saved || ( ! $evenIfOutOfDate && ! empty( $saved['needs_update'] ) ) ) {
			return false;
		}

		return $this->closeRequest( $requestJobId, $recordJobId );
	}

	private function openRequestOnRecord( $requestJobId, $recordJobId ) {
		$wpdb         = $this->db();
		$requestJobId = (int) $requestJobId;
		if ( $requestJobId <= 0 || $requestJobId === $recordJobId ) {
			return null;
		}

		$request = $wpdb->get_row(
			$wpdb->prepare( "SELECT job_id, rid, translated, completed_date, editor_job_id, editor FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d", $requestJobId ),
			ARRAY_A
		);
		if ( ! is_array( $request ) || ! isset( $request['rid'] ) || self::isCompleted( $request ) ) {
			return null;
		}

		if ( Map::jobIdFromDB( (int) $request['rid'] ) !== $requestJobId ) {
			return null;
		}

		$record = Jobs::getPreviousJob( $requestJobId );
		if ( ! $record || ! self::isCompleted( $record ) || ( $recordJobId && (int) Obj::prop( 'job_id', $record ) !== $recordJobId ) ) {
			return null;
		}
		$request['record'] = $record;

		return $request;
	}

	private function dropRequest( array $request ) {
		$wpdb         = $this->db();
		$requestJobId = (int) $request['job_id'];
		$recordJobId  = (int) Obj::prop( 'job_id', $request['record'] );
		$rid          = (int) $request['rid'];

		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d AND rid = %d AND translated = 0 AND completed_date IS NULL", $requestJobId, $rid ) );
		if ( ! $deleted ) {
			return false;
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id = %d", $requestJobId ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}icl_translate_job SET revision = NULL WHERE job_id = %d", $recordJobId ) );

		Map::rememberJobIdForRid( $rid, $recordJobId );

		return true;
	}

	private function cancelInATE( array $request ) {
		$ateJobId = (int) Obj::prop( 'editor_job_id', $request );
		if ( \WPML_TM_Editors::ATE !== Obj::prop( 'editor', $request ) || $ateJobId <= 0 ) {
			return;
		}

		$job = new \WPML_TM_Post_Job_Entity( (int) $request['rid'], \WPML_TM_Job_Entity::POST_TYPE, 0, new \WPML_TM_Jobs_Batch( 0, '' ), ICL_TM_IN_PROGRESS, [] );
		$job->set_translate_job_id( (int) $request['job_id'] );
		$job->set_translation_service( 'local' );
		$job->set_editor( \WPML_TM_Editors::ATE );
		$job->set_editor_job_id( $ateJobId );

		$this->announceCancelled( $job );

		set_transient( self::CLOSED_CLONE . (int) Obj::prop( 'job_id', $request['record'] ), $ateJobId, 7 * DAY_IN_SECONDS );
	}

	public static function closedCloneOf( $recordJobId ) {
		return (int) get_transient( self::CLOSED_CLONE . (int) $recordJobId );
	}

	protected function announceCancelled( \WPML_TM_Post_Job_Entity $job ) {
		do_action( 'wpml_tm_job_cancelled', $job, \WPML_TM_ATE_API::CANCEL_REASON_CANCELLED );
	}

	private function translationIdOf( $rid ) {
		$wpdb = $this->db();

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT translation_id FROM {$wpdb->prefix}icl_translation_status WHERE rid = %d", $rid ) );
	}

	private function restoreOrClearPreviousState( $translationId ) {
		$wpdb    = $this->db();
		$service = PreviousStateServiceFactory::create();
		$saved   = $service->get( $translationId );
		if ( ! $saved ) {
			return;
		}

		$current = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, translator_id, needs_update, md5, translation_service, timestamp, links_fixed FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = %d",
				$translationId
			),
			ARRAY_A
		);
		if ( is_array( $current ) && self::isSameState( $current, (array) $saved ) ) {
			$this->clearPreviousState( $translationId );

			return;
		}

		try {
			$service->revertToPreviousState( $translationId );
		} catch ( \Throwable $throwable ) {
			JobLog::addError( 'request_discard_restore_failed', [
				'translation_id' => $translationId,
				'message'        => $throwable->getMessage(),
			] );
			$this->clearPreviousState( $translationId );

			return;
		}

		if ( is_array( $current ) && ! empty( $current['needs_update'] ) ) {
			$wpdb->update(
				$wpdb->prefix . 'icl_translation_status',
				[
					'needs_update' => 1,
					'md5'          => (string) $current['md5'],
				],
				[ 'translation_id' => (int) $translationId ],
				[ '%d', '%s' ],
				[ '%d' ]
			);
		}
	}

	private function clearPreviousState( $translationId ) {
		$this->db()->update(
			$this->db()->prefix . 'icl_translation_status',
			[ '_prevstate' => null ],
			[ 'translation_id' => (int) $translationId ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	private static function isSameState( array $current, array $saved ) {
		foreach ( $saved as $column => $value ) {
			if ( ! array_key_exists( $column, $current ) ) {
				continue;
			}
			if ( is_bool( $value ) ) {
				if ( (bool) $current[ $column ] !== $value ) {
					return false;
				}
				continue;
			}
			if ( (string) $current[ $column ] !== (string) $value ) {
				return false;
			}
		}

		return true;
	}

	public static function requestRowOf( $recordJobId, $deliveringAteJobId = null ) {
		$deliveringAteJobId = (int) $deliveringAteJobId;
		if ( $deliveringAteJobId <= 0 ) {
			return 0;
		}

		$request = self::openRequestWithAteJobOf( $recordJobId );

		return $request && $request['editor_job_id'] === $deliveringAteJobId ? $request['job_id'] : 0;
	}

	public static function openRequestWithAteJobOf( $recordJobId ) {
		$newest = self::openRequestRowOf( $recordJobId );

		if ( ! $newest || (int) Obj::prop( 'editor_job_id', $newest ) <= 0 ) {
			return null;
		}

		return [
			'job_id'        => (int) Obj::prop( 'job_id', $newest ),
			'editor_job_id' => (int) Obj::prop( 'editor_job_id', $newest ),
			'rid'           => (int) Obj::prop( 'rid', $newest ),
		];
	}

	public static function rowToOpen( $jobId ) {
		$jobId  = (int) $jobId;
		$newest = self::newerRowOf( $jobId );

		return $newest ? (int) Obj::prop( 'job_id', $newest ) : $jobId;
	}

	private static function openRequestRowOf( $recordJobId ) {
		$newest = self::newerRowOf( $recordJobId );

		return $newest && ! self::isCompleted( $newest ) ? $newest : null;
	}

	private static function newerRowOf( $recordJobId ) {
		global $wpdb;

		$recordJobId = (int) $recordJobId;
		if ( $recordJobId <= 0 || ! self::isCompletedJobId( $recordJobId ) ) {
			return null;
		}

		$newest = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT newest.job_id, newest.rid, newest.editor_job_id, newest.translated, newest.completed_date
				 FROM {$wpdb->prefix}icl_translate_job record
				 INNER JOIN {$wpdb->prefix}icl_translate_job newest ON newest.rid = record.rid
				 WHERE record.job_id = %d
				 ORDER BY newest.job_id DESC
				 LIMIT 1",
				$recordJobId
			),
			ARRAY_A
		);

		return $newest && (int) Obj::prop( 'job_id', $newest ) > $recordJobId ? $newest : null;
	}

	public function openRequest( \WPML_Element_Translation_Job $record, $sentFrom = null ) {
		$recordId      = (int) $record->get_id();
		$rid           = (int) $record->get_basic_data_property( 'rid' );
		$translationId = (int) $record->get_basic_data_property( 'translation_id' );

		if ( ! $rid ) {
			JobLog::addError( 'request_row_not_opened', [ 'job_id' => $recordId, 'reason' => 'no rid' ] );

			return null;
		}

		$package = $this->packageFor( $record );
		if ( ! $package ) {
			JobLog::addError( 'request_row_not_opened', [ 'job_id' => $recordId, 'rid' => $rid, 'reason' => 'no translation package' ] );

			return null;
		}

		$this->savePreviousState( $translationId, $rid );

		$requestId = (int) $this->actionHelper()->add_translation_job(
			$rid,
			$this->translatorForRequest( $record ),
			$package,
			[],
			$sentFrom,
			true,
			false
		);

		if ( ! $requestId || $requestId === $recordId ) {
			JobLog::addError( 'request_row_not_opened', [ 'job_id' => $recordId, 'rid' => $rid, 'reason' => $requestId ? 'add_translation_job reused the record' : 'add_translation_job returned no row' ] );

			return null;
		}

		$this->ensureRecordIsNumbered( $rid, $recordId );

		$request = wpml_tm_load_job_factory()->get_translation_job_as_active_record( $requestId );
		if ( ! $request instanceof \WPML_Element_Translation_Job ) {
			JobLog::addError( 'request_row_not_opened', [ 'job_id' => $recordId, 'rid' => $rid, 'request_job_id' => $requestId, 'reason' => 'the new row could not be loaded' ] );

			return null;
		}

		if ( 'local' !== $request->get_translation_service() ) {
			$request->set_basic_data_property( 'translation_service', 'local' );
			Jobs::setTranslationService( $requestId, 'local' );
		}

		JobLog::add( 'request_row_opened_for_completed_job', [
			'record_job_id'  => $recordId,
			'request_job_id' => $requestId,
			'rid'            => $rid,
			'sent_from'      => $sentFrom,
		] );

		return $request;
	}

	public function savePreviousState( $translationId, $rid ) {
		$wpdb = $this->db();

		$translationId = (int) $translationId;
		$rid           = (int) $rid;

		if ( ! $translationId || ! $rid ) {
			return false;
		}

		$status = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, translator_id, needs_update, md5, translation_service, timestamp, links_fixed
				 FROM {$wpdb->prefix}icl_translation_status
				 WHERE rid = %d",
				$rid
			),
			ARRAY_A
		);

		return $this->savePreviousStateOf( $translationId, $status );
	}

	public function savePreviousStateOf( $translationId, $status ) {
		$translationId = (int) $translationId;

		if ( ! $translationId || ! is_array( $status ) || ! isset( $status['status'] ) ) {
			return false;
		}

		$statusValue = (int) $status['status'];
		if ( in_array( $statusValue, [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ], true ) ) {
			$statusValue = ICL_TM_COMPLETE;
		}

		if ( ICL_TM_COMPLETE !== $statusValue ) {
			return false;
		}

		return PreviousStateServiceFactory::create()->update(
			$translationId,
			[
				'status'              => ICL_TM_COMPLETE,
				'translator_id'       => (int) ( $status['translator_id'] ?? 0 ),
				'needs_update'        => (bool) ( $status['needs_update'] ?? false ),
				'md5'                 => (string) ( $status['md5'] ?? '' ),
				'translation_service' => (string) ( $status['translation_service'] ?? 'local' ),
				'timestamp'           => (string) ( $status['timestamp'] ?? '' ),
				'links_fixed'         => (bool) ( $status['links_fixed'] ?? false ),
			]
		);
	}

	public function savePreviousStateForRid( $rid ) {
		$wpdb = $this->db();

		$translationId = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT translation_id FROM {$wpdb->prefix}icl_translation_status WHERE rid = %d",
				(int) $rid
			)
		);

		return $this->savePreviousState( $translationId, (int) $rid );
	}

	private function ensureRecordIsNumbered( $rid, $recordId ) {
		$wpdb = $this->db();

		$lastRevision = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(revision) FROM {$wpdb->prefix}icl_translate_job
				 WHERE rid = %d AND ( revision IS NOT NULL OR translated = 1 )",
				$rid
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translate_job SET revision = %d WHERE job_id = %d AND revision IS NULL",
				$lastRevision + 1,
				$recordId
			)
		);
	}

	private function translatorForRequest( \WPML_Element_Translation_Job $record ) {
		$recordTranslator = (int) $record->get_translator_id();
		$currentUserId    = (int) User::getCurrentId();

		if ( $currentUserId && $recordTranslator !== $currentUserId && ! User::canManageTranslations() ) {
			return $currentUserId;
		}

		return $recordTranslator;
	}

	private function packageFor( \WPML_Element_Translation_Job $record ) {
		$elementId = (int) $record->get_original_element_id();
		if ( ! $elementId ) {
			return null;
		}

		$elementType = (string) $record->get_basic_data_property( 'original_post_type' );
		$prefix      = false !== strpos( $elementType, '_' ) ? substr( $elementType, 0, strpos( $elementType, '_' ) ) : $elementType;
		$item        = wpml_load_core_tm()->get_post( $elementId, $prefix ?: 'post' );
		if ( ! $item ) {
			return null;
		}

		$package = $this->actionHelper()->create_translation_package( $item );

		return is_array( $package ) && ! empty( $package['contents'] ) ? $package : null;
	}

	protected function actionHelper() {
		return new \WPML_TM_Action_Helper();
	}

	private function db() {
		return $this->wpdb ?: $GLOBALS['wpdb'];
	}

	private static function isDate( $value ) {
		return is_string( $value ) && '' !== $value && 0 !== strpos( $value, '0000-00-00' );
	}
}
