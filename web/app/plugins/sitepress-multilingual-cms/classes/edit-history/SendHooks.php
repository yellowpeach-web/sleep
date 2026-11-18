<?php

namespace WPML\EditHistory;

class SendHooks implements \IWPML_Action {

	public function add_hooks() {
		add_action( 'wpml_tm_ate_jobs_created', [ $this, 'onJobsCreated' ], 10, 1 );
		add_filter( 'wpml_dashboard_initial_script_data', [ $this, 'onDashboardData' ] );
		add_action( ReSend::HOOK, [ $this, 'onReSend' ] );
		add_action( ReSend::DROPPED_ACTION, [ $this, 'onDeliveryDropped' ], 10, 2 );
	}

	public function onReSend( $rid ) {
		$resend = SendFactory::resend();
		if ( ! $resend ) {
			return;
		}

		$ownsLog = \WPML\TM\Jobs\JobLog::maybeInitBackgroundRequest( 'wpml-edit-history-resend' );
		try {
			$resend->run( (int) $rid );
		} finally {
			if ( $ownsLog ) {
				\WPML\TM\Jobs\JobLog::flushBackgroundRequest();
			}
		}
	}

	public function onDeliveryDropped( $jobId, $deliveryId = 0 ) {
		$resend = SendFactory::resend();
		if ( $resend ) {
			$resend->afterDroppedDelivery( (int) $jobId, (int) $deliveryId );
		}
	}

	public function onDashboardData( $data ) {
		return ( new EditedTranslations( $GLOBALS['wpdb'] ) )->addToDashboard( $data );
	}

	public function onJobsCreated( $ateJobIds ) {
		$send = SendFactory::send();
		if ( $send && is_array( $ateJobIds ) ) {
			$send->bind( $ateJobIds );
		}
	}
}
