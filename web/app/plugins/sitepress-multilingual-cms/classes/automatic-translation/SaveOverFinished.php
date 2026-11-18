<?php

namespace WPML\TM\AutomaticTranslation\Actions;

use WPML\TM\API\Job\Map;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Handshake\ApplyFinished;
use WPML\TM\ATE\Handshake\Clock;
use WPML\TM\ATE\PullDelivery\Cadence;
use WPML\TM\Jobs\JobLog;

class SaveOverFinished {

	const ACTION_DISPATCH = 'wpml_tea_dispatch_after_apply_first';

	const SUPERSEDED = 'superseded_meanwhile';

	const CHANGED = 'changed_meanwhile';

	public static function candidates( \WPML_Translation_Element $element, array $languages ) {
		if ( ! $languages || ! $element instanceof \WPML_Post_Element ) {
			return [];
		}

		$candidates = [];

		foreach ( $languages as $language ) {
			$row   = Jobs::getElementJob( (int) $element->get_element_id(), $element->get_wpml_element_type(), (string) $language );
			$jobId = is_object( $row ) && isset( $row->job_id ) ? (int) $row->job_id : 0;

			if ( $jobId <= 0 ) {
				continue;
			}

			$job = Jobs::get( $jobId );

			if ( ApplyFinished::isCandidate( $job ) ) {
				$candidates[ (string) $language ] = [
					'job_id'     => $jobId,
					'ate_job_id' => (int) $job->editor_job_id,
				];
			}
		}

		return $candidates;
	}

	public static function completedOnly( array $candidates, ?ApplyFinished $applier = null ) {
		if ( ! $candidates ) {
			return [];
		}

		$applier   = $applier ?: new ApplyFinished();
		$ateJobIds = [];

		foreach ( $candidates as $item ) {
			$ateJobIds[ (int) $item['job_id'] ] = (int) $item['ate_job_id'];
		}

		$completed = $applier->completedAtAte( $ateJobIds, Clock::exact() + ApplyFinished::CHECK_SECONDS );
		$deferred  = [];
		$kept      = [];

		foreach ( $candidates as $language => $item ) {
			if ( in_array( (int) $item['ate_job_id'], $completed, true ) ) {
				$deferred[ $language ] = $item;
			} else {
				$kept[] = (string) $language;
			}
		}

		JobLog::add( 'auto_translate_apply_first_check', [
			'deferred' => array_keys( $deferred ),
			'kept'     => $kept,
		] );

		return $deferred;
	}

	public static function applyInline( array $candidates, ?ApplyFinished $applier = null ) {
		$applier  = $applier ?: new ApplyFinished();
		$deadline = self::deadline();
		$outcomes = [];

		foreach ( $candidates as $language => $item ) {
			$outcomes[ $language ] = self::applyOne( $applier, (int) $item['job_id'], (int) $item['ate_job_id'], $deadline );
		}

		JobLog::add( 'auto_translate_apply_first_inline', [ 'outcomes' => $outcomes ] );

		return $outcomes;
	}

	public static function settleAndDispatch( $postId, array $items, $source, ?ApplyFinished $applier = null, $dispatch = null ) {
		$applier   = $applier ?: new ApplyFinished();
		$deadline  = self::deadline();
		$outcomes  = [];
		$languages = [];
		$applied   = [];

		foreach ( $items as $language => $item ) {
			$outcome                       = self::settleOne( $applier, (int) $item['job_id'], (int) $item['ate_job_id'], $deadline );
			$outcomes[ (string) $language ] = $outcome;

			if ( self::SUPERSEDED !== $outcome ) {
				$languages[] = (string) $language;
			}
			if ( ApplyFinished::APPLIED === $outcome ) {
				$applied[] = (int) $item['job_id'];
			}
		}

		JobLog::add( 'auto_translate_apply_first_settled', [
			'post_id'  => (int) $postId,
			'outcomes' => $outcomes,
		] );

		if ( $languages ) {
			if ( is_callable( $dispatch ) ) {
				$dispatch( (int) $postId, $languages, (string) $source, $applied );
			} else {
				do_action( self::ACTION_DISPATCH, (int) $postId, $languages, (string) $source, $applied );
			}
		}

		return $outcomes;
	}

	private static function settleOne( ApplyFinished $applier, $jobId, $ateJobId, $deadline ) {
		$rid = $jobId > 0 ? (int) Map::ridFromDB( $jobId ) : 0;

		if ( ! $rid || (int) Map::jobIdFromDB( $rid ) !== $jobId ) {
			return self::SUPERSEDED;
		}

		if ( ! ApplyFinished::isCandidate( Jobs::get( $jobId ) ) ) {
			return self::CHANGED;
		}

		return self::applyOne( $applier, $jobId, $ateJobId, $deadline );
	}

	private static function applyOne( ApplyFinished $applier, $jobId, $ateJobId, $deadline ) {
		$outcome = $applier->apply( $jobId, $ateJobId, $deadline );

		if ( ApplyFinished::APPLIED === $outcome ) {
			Jobs::setNeedsUpdate( $jobId );
		}

		return $outcome;
	}

	private static function deadline() {
		return Clock::exact() + max( 1, (int) Cadence::collectBudgetSeconds() );
	}
}
