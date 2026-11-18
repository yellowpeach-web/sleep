<?php

namespace WPML\TM\ATE\Retranslation\BackgroundTask;

use WPML\BackgroundTask\AbstractTaskEndpoint;
use WPML\Collect\Support\Collection;
use WPML\Core\BackgroundTask\Model\BackgroundTask;

class RetranslationTask extends AbstractTaskEndpoint {

	const PHASE_PREPARING     = 'preparing';
	const PHASE_ATE_PROCESSING = 'ate_processing';
	const PHASE_FINALIZING    = 'finalizing';
	const PHASE_WPML_SYNCING  = 'wpml_syncing';

	const OUTCOME_NOT_COMPLETED = 'not_completed';

	public function runBackgroundTask( BackgroundTask $task ) {
		return $task;
	}

	public function getDescription( Collection $data ) {
		$phase = $data->get( 'phase', self::PHASE_PREPARING );

		if ( self::OUTCOME_NOT_COMPLETED === $data->get( 'outcome' ) ) {
			/* translators: Shown when translation improvements (for example a glossary change) could not be applied to every page. */
			return __( 'Some translation improvements could not be applied. To apply them, send the pages to translation again.', 'sitepress' );
		}

		if ( self::PHASE_PREPARING === $phase ) {
			return __( 'Preparing translation updates.', 'sitepress' );
		}

		if ( self::PHASE_WPML_SYNCING === $phase || self::PHASE_FINALIZING === $phase ) {
			/* translators: Message shown while WPML is bringing translations up to date in the background. */
			return __( 'Updating translations.', 'sitepress' );
		}

		$suggestionsCount = $data->get( 'suggestions_count' );
		if ( $suggestionsCount ) {
			return sprintf(
				/* translators: %d is the number of translation improvement suggestions being applied */
				_n(
					'Applying %d translation improvement.',
					'Applying %d translation improvements.',
					(int) $suggestionsCount,
					'sitepress'
				),
				(int) $suggestionsCount
			);
		}

		return __( 'Applying translation improvements.', 'sitepress' );
	}

	public function getTotalRecords( Collection $data ) {
		$wpmlJobIds = $data->get( 'wpml_job_ids', [] );
		if ( $wpmlJobIds ) {
			return count( $wpmlJobIds );
		}

		$ateJobCount = $data->get( 'ate_job_count' );

		return $ateJobCount ? (int) $ateJobCount : 1;
	}
}
