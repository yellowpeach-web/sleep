<?php

namespace WPML\TM\Settings;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;

class SchedulePreferenceReapply implements IHandler {

	private $preferenceReapply;

	public function __construct( PreferenceReapply $preferenceReapply ) {
		$this->preferenceReapply = $preferenceReapply;
	}

	public function run( Collection $data ) {
		$applyToExisting = (bool) $data->get( 'applyToExisting', false );
		$kind            = ReapplyPreferenceChanges::kindOf( [ 'kind' => $data->get( 'kind' ) ] );
		$unit            = ReapplyPreferenceChanges::UNIT_POSTS;

		if ( ReapplyPreferenceChanges::KIND_PAGE_URL === $kind ) {
			$transition = ReapplyPreferenceChanges::pageUrlTransitionOf( [ 'transition' => $data->get( 'transition' ) ] );
			$taskId     = $this->preferenceReapply->schedulePageUrl( $applyToExisting, $transition );
			$summary = $taskId ? $this->preferenceReapply->getSummary( $taskId ) : null;
			$total   = $summary ? (int) $summary['total'] : 0;
			$unit    = $summary ? (string) $summary['unit'] : ReapplyPreferenceChanges::UNIT_TRANSLATIONS;
		} else {
			$changes = (array) $data->get( 'changes', [] );
			$total   = $applyToExisting ? $this->countAffectedContent( $changes ) : 0;
			$taskId  = $this->preferenceReapply->schedule( $changes, $applyToExisting );
		}

		return Either::of(
			[
				'taskId'   => $taskId,
				'total'    => $taskId ? (int) $total : 0,
				'unit'     => $unit,
				'endpoint' => ReapplyPreferenceChanges::class,
			]
		);
	}

	private function countAffectedContent( array $changes ): int {
		return $this->preferenceReapply->countAffectedForChanges( $changes )
			+ $this->preferenceReapply->countAffectedTermsForChanges( $changes );
	}
}
