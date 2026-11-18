<?php

use WPML\TM\Settings\RequestSettings;
use function WPML\PHP\Logger\error as logError;
use function WPML\PHP\Logger\notice as logNotice;

class WPML_TM_Batch_Report_Email_Process {

	private $batch_report;

	private $email_builder;

	public function __construct( WPML_TM_Batch_Report $batch_report, WPML_TM_Batch_Report_Email_Builder $email_builder ) {
		$this->batch_report  = $batch_report;
		$this->email_builder = $email_builder;
	}

	public function process_emails() {
		try {
			if ( ! self::is_new_job_notification_on() ) {
				$cleared = $this->batch_report->clear();
				if ( $cleared ) {
					logNotice( sprintf( 'Translation job notification emails are off: %d queued job(s) cleared without an email.', $cleared ) );
				}

				return;
			}

			$batch_jobs = $this->batch_report->get_jobs();

			$this->email_builder->prepare_assigned_jobs_emails( $batch_jobs );
			$this->email_builder->prepare_unassigned_jobs_emails( $batch_jobs );

			$this->send_emails( $batch_jobs );
		} catch ( Throwable $e ) {
			self::log_failure( $e );
		}
	}

	private static function is_new_job_notification_on() {
		return ICL_TM_NOTIFICATION_IMMEDIATELY === (int) RequestSettings::tmNotificationSettings()['new-job'];
	}

	private static function log_failure( Throwable $e ) {
		$file = defined( 'WP_CONTENT_DIR' ) ? str_replace( WP_CONTENT_DIR . '/', '', $e->getFile() ) : $e->getFile();
		logError( sprintf( 'Translation job notification email failed: %s: %s in %s:%d', get_class( $e ), $e->getMessage(), $file, $e->getLine() ) );
	}

	private function send_emails( array $batch_jobs ) {
		$this->batch_report->clean_batch_jobs();

		$headers         = array();
		$headers[]       = 'Content-type: text/html; charset=UTF-8';
		$translators_ids = [ 0 ];

		foreach ( $this->email_builder->get_emails() as $email ) {
			$email['attachment'] = isset( $email['attachment'] ) ? $email['attachment'] : array();

			try {
				$email_sent = WPML_Mail_Sender::send( $email['email'], $email['subject'], $email['body'], $headers, $email['attachment'], 'batch-report' );
			} catch ( Throwable $e ) {
				self::log_failure( $e );
				$email_sent = false;
			}

			if ( $email_sent ) {
				$translators_ids[] = $email['translator_id'];
			}
		}

		$orphaned_translators_ids = $this->email_builder->get_orphaned_translators_ids();
		$dnd_translators_ids      = $this->email_builder->get_dnd_translators_ids();
		$translators_ids          = array_merge( $translators_ids, $orphaned_translators_ids, $dnd_translators_ids );

		$this->batch_report->remove_emailed_jobs( $batch_jobs, $translators_ids );
	}
}
