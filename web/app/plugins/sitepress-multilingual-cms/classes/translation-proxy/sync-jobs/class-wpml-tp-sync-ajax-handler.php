<?php

class WPML_TP_Sync_Ajax_Handler {

	const AJAX_ACTION = 'wpml-tp-sync-job-states';

	private $tp_sync;

	private $wpml_tm_last_picked_up;

	public function __construct( WPML_TP_Sync_Jobs $tp_sync, WPML_TM_Last_Picked_Up $wpml_tm_last_picked_up ) {
		$this->tp_sync                = $tp_sync;
		$this->wpml_tm_last_picked_up = $wpml_tm_last_picked_up;
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( self::AJAX_ACTION, \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( 'sync-job-states', 'nonce' ) ), array( $this, 'handle' ) );
	}

	public function handle() {
		if ( \WPML\Setup\Initializer::rejectSettingsMutationAjax() ) {
			return false;
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'sync-job-states' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ) );
		}

		\WPML\TM\Jobs\JobLog::maybeInitRequest();
		\WPML\TM\Jobs\JobLog::createNewGroup(
			\WPML\TM\Jobs\JobLog::GROUP_ID_SYNC_JOBS,
			'TP pickup status sync',
			array(
				'paged'  => isset( $_POST['paged'] ),
				'cursor' => isset( $_POST['cursor'] ) ? (int) $_POST['cursor'] : null,
				'limit'  => isset( $_POST['limit'] ) ? (int) $_POST['limit'] : null,
			)
		);

		try {
			if ( isset( $_POST['paged'] ) ) {
				$response = $this->handle_paged_sync();
			} else {
				$jobs     = $this->tp_sync->sync();
				$response = $jobs->map( array( $this, 'map_job_to_result' ) );

				\WPML\TM\Jobs\JobLog::add( 'tp_sync_completed', array( 'changed' => count( $jobs ) ) );

				$this->maybe_stamp_last_picked_up( $jobs );
			}

			do_action( 'wpml_tm_empty_mail_queue' );

			\WPML\TM\Jobs\JobLog::finishCurrentGroup();

			wp_send_json_success( $response );

			return true;
		} catch ( Exception $e ) {
			\WPML\TM\Jobs\JobLog::addError( 'tp_sync_failed', array( 'message' => $e->getMessage() ) );
			\WPML\TM\Jobs\JobLog::finishCurrentGroup();

			wp_send_json_error(
				\WPML\WordPress\ClientSafeError::message( 'TP sync AJAX ' . self::AJAX_ACTION, $e ),
				503
			);

			return false;
		}
	}

	private function handle_paged_sync() {
		$cursor    = isset( $_POST['cursor'] ) ? (int) $_POST['cursor'] : 0;
		$page_size = \WPML\TM\TranslationProxy\SendTuning::pickupPageSize();
		if ( isset( $_POST['limit'] ) && (int) $_POST['limit'] > 0 ) {
			$page_size = min( (int) $_POST['limit'], $page_size );
		}

		$page_ids = $this->tp_sync->get_in_flight_tp_ids( $cursor, $page_size );
		$total    = $this->tp_sync->count_in_flight_tp_ids( $cursor );

		$jobs      = new WPML_TM_Jobs_Collection( array() );
		$processed = array();

		$deadline = microtime( true ) + \WPML\TM\TranslationProxy\SendTuning::receiveTimeBudgetSeconds();

		if ( 0 === $cursor ) {
			$jobs = $jobs->append( $this->tp_sync->sync_revised() );
		}

		$synced_a_chunk = false;

		foreach ( array_chunk( $page_ids, WPML_TP_Jobs_API::CHUNK_SIZE ) as $chunk ) {
			if ( $synced_a_chunk && microtime( true ) >= $deadline ) {
				break;
			}

			$jobs           = $jobs->append( $this->tp_sync->sync( $chunk ) );
			$processed      = array_merge( $processed, $chunk );
			$synced_a_chunk = true;
		}

		$remaining = max( 0, $total - count( $processed ) );

		$this->maybe_stamp_last_picked_up( $jobs );

		$next_cursor = ( $remaining > 0 && $processed ) ? (int) end( $processed ) : null;

		\WPML\TM\Jobs\JobLog::add(
			'tp_sync_page',
			array(
				'processed'  => count( $processed ),
				'requested'  => count( $page_ids ),
				'remaining'  => $remaining,
				'nextCursor' => $next_cursor,
				'changed'    => count( $jobs ),
			)
		);

		return array(
			'jobs'       => $jobs->map( array( $this, 'map_job_to_result' ) ),
			'processed'  => count( $processed ),
			'remaining'  => $remaining,
			'nextCursor' => $next_cursor,
		);
	}

	private function maybe_stamp_last_picked_up( WPML_TM_Jobs_Collection $jobs ) {
		if ( isset( $_REQUEST['update_last_picked_up'] ) && count( $jobs ) > 0 ) {
			$this->wpml_tm_last_picked_up->set();
			\WPML\TM\Jobs\JobLog::add( 'tp_last_picked_up_stamped', array( 'changed' => count( $jobs ) ) );
		}
	}

	public function map_job_to_result( WPML_TM_Job_Entity $job ) {
		return array(
			'id'                      => $job->get_id(),
			'type'                    => $job->get_type(),
			'status'                  => $job->get_status(),
			'hasCompletedTranslation' => $job->has_completed_translation(),
			'needsUpdate'             => $job->does_need_update(),
		);
	}
}
