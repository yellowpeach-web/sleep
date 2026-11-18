<?php

namespace WPML\TM\Editor;

use WPML\FP\Obj;
use WPML\LIB\WP\User;
use WPML\TM\API\Jobs;
use WPML\TM\Editor\Dispatch\DispatchPlan;
use WPML\TM\Jobs\JobLog;
use WPML\TM\Jobs\TranslationRecord;

class ClassicEditorRequest {

	const SESSION_FIELD = 'request_session';

	const SESSION_TTL = 86400;

	private $plan;

	private $record;

	private $wpdb;

	public function __construct( DispatchPlan $plan, ?TranslationRecord $record = null, ?\wpdb $wpdb = null ) {
		$this->plan   = $plan;
		$this->record = $record ?: new TranslationRecord();
		$this->wpdb   = $wpdb;
	}

	public function forSave( array $data ) {
		$recordId = (int) Obj::prop( 'job_id', $data );

		if ( ! $recordId || $this->plan->opensRequestOnOpen() || ! TranslationRecord::isCompletedJobId( $recordId ) ) {
			return $data;
		}

		$openLock = new OpenLock();
		$lock     = $openLock->acquire( [ 'job_id' => $recordId ] );

		try {
			return $this->moveToRequestRow( $data, $recordId );
		} finally {
			if ( $lock ) {
				$lock->release();
			}
		}
	}

	private function moveToRequestRow( array $data, int $recordId ) {
		$newer = [];
		foreach ( $this->newerRowsOf( $recordId ) as $row ) {
			if ( \WPML_TM_Editors::WPML === Obj::prop( 'editor', $row ) ) {
				$newer[] = $row;
				continue;
			}

			if ( ! TranslationRecord::isClosedRequestOnRecord( (int) $row['job_id'] ) ) {
				return $data;
			}
		}

		$newest  = $newer ? end( $newer ) : null;
		$made    = false;
		$session = $this->sessionKey( $recordId, $data );
		$request = $newest && ! TranslationRecord::isCompleted( $newest ) ? (int) $newest['job_id'] : 0;

		if ( ! $request && $newest && $session && (int) get_transient( $session ) === (int) $newest['job_id'] ) {
			$request = (int) $newest['job_id'];
		}

		if ( ! $request ) {
			$request = $this->openRequestFrom( $newest ? (int) $newest['job_id'] : $recordId );
			if ( ! $request ) {
				return null;
			}
			$made = true;
		}

		$fields = $this->fieldsOnRequest( $recordId, $request, (array) Obj::propOr( [], 'fields', $data ) );
		if ( null === $fields ) {
			if ( $made ) {
				$this->record->discardRequest( $request, $newest ? (int) $newest['job_id'] : $recordId );
			}

			return null;
		}

		if ( $made && $session ) {
			set_transient( $session, $request, self::SESSION_TTL );
		}

		JobLog::add( 'classic_editor_save_moved_to_request_row', [
			'record_job_id'  => $recordId,
			'request_job_id' => $request,
			'made'           => $made,
			'fields'         => count( $fields ),
		] );

		$data['job_id'] = $request;
		$data['fields'] = $fields;

		return $data;
	}

	private function sessionKey( int $recordId, array $data ): string {
		$token = Obj::prop( self::SESSION_FIELD, $data );
		if ( ! is_string( $token ) || '' === $token ) {
			return '';
		}

		return 'wpml_cte_request_' . md5( $recordId . '|' . User::getCurrentId() . '|' . $token );
	}

	private function openRequestFrom( int $completedJobId ): int {
		$completed = wpml_tm_load_job_factory()->get_translation_job_as_active_record( $completedJobId );
		if ( ! $completed instanceof \WPML_Element_Translation_Job ) {
			return 0;
		}

		$request = $this->record->openRequest( $completed, Jobs::SENT_MANUALLY );
		if ( ! $request ) {
			return 0;
		}

		wpml_tm_load_old_jobs_editor()->set( $request->get_id(), \WPML_TM_Editors::WPML );
		if ( $request->get_translator_id() <= 0 ) {
			$request->assign_to( User::getCurrentId() );
		}

		return (int) $request->get_id();
	}

	private function fieldsOnRequest( int $recordId, int $requestId, array $fields ) {
		$recordTypes = $this->fieldTypesOf( $recordId );
		$requestTids = array_flip( $this->fieldTypesOf( $requestId ) );

		foreach ( $fields as $name => $field ) {
			if ( ! is_array( $field ) || empty( $field['tid'] ) ) {
				continue;
			}

			$tid  = (int) $field['tid'];
			$type = isset( $recordTypes[ $tid ] ) ? $recordTypes[ $tid ] : (string) $name;

			if ( ! isset( $requestTids[ $type ] ) ) {
				JobLog::addError( 'classic_editor_save_field_not_on_request_row', [
					'record_job_id'  => $recordId,
					'request_job_id' => $requestId,
					'field'          => (string) $name,
					'tid'            => $tid,
				] );

				return null;
			}

			$fields[ $name ]['tid'] = (int) $requestTids[ $type ];
		}

		return $fields;
	}

	private function newerRowsOf( int $recordId ): array {
		$db = $this->db();

		$rows = $db->get_results(
			$db->prepare(
				"SELECT newer.job_id, newer.editor, newer.translated, newer.completed_date
				 FROM {$db->prefix}icl_translate_job record
				 INNER JOIN {$db->prefix}icl_translate_job newer ON newer.rid = record.rid AND newer.job_id > record.job_id
				 WHERE record.job_id = %d
				 ORDER BY newer.job_id",
				$recordId
			),
			ARRAY_A
		);

		return is_array( $rows ) ? array_values( $rows ) : [];
	}

	private function fieldTypesOf( int $jobId ): array {
		$db = $this->db();

		$rows = $db->get_results(
			$db->prepare( "SELECT tid, field_type FROM {$db->prefix}icl_translate WHERE job_id = %d", $jobId ),
			ARRAY_A
		);

		$types = [];
		foreach ( (array) $rows as $row ) {
			$types[ (int) $row['tid'] ] = (string) $row['field_type'];
		}

		return $types;
	}

	private function db() {
		global $wpdb;

		return $this->wpdb ?: $wpdb;
	}
}
