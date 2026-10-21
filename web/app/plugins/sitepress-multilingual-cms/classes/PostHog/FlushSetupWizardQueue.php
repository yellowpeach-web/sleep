<?php

namespace WPML\PostHog;

use WPML\Core\Component\PostHog\Application\Repository\SetupWizardEventQueueRepositoryInterface;
use WPML\Infrastructure\WordPress\Component\PostHog\Application\Event\SetupWizardEventQueueReplayScheduler;
use WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository\SetupWizardEventQueueRepository;

class FlushSetupWizardQueue {

	const MAX_ROUNDS = 5;

	public static function flushIfGranted() {
		$mode = get_option( 'wpml_posthog_tracking_mode' );

		if ( 'all' !== $mode ) {
			return false;
		}

		for ( $i = 0; $i < self::MAX_ROUNDS; $i++ ) {
			do_action( SetupWizardEventQueueReplayScheduler::REPLAY_HOOK );

			if ( ! self::hasPendingRows() ) {
				break;
			}
		}

		if ( ! self::hasPendingRows() ) {
			wp_clear_scheduled_hook( SetupWizardEventQueueReplayScheduler::REPLAY_HOOK );
		}

		return true;
	}


	private static function hasPendingRows() {
		$rows = get_option( SetupWizardEventQueueRepository::OPTION_KEY, [] );

		if ( ! is_array( $rows ) ) {
			return false;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$status = isset( $row['status'] ) && is_string( $row['status'] )
				? $row['status']
				: SetupWizardEventQueueRepositoryInterface::STATUS_PENDING;
			if ( SetupWizardEventQueueRepositoryInterface::STATUS_PENDING === $status ) {
				return true;
			}
		}

		return false;
	}

}
