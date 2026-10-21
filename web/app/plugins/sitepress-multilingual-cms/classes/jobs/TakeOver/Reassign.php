<?php

namespace WPML\TM\Jobs\TakeOver;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LIB\WP\User;
use WPML\TM\API\Jobs;
use WPML\TM\Jobs\TranslationRecord;

class Reassign implements IHandler {

	public function run( Collection $data ) {
		$jobId          = (int) $data->get( 'jobId' );
		$expectedTassId = (int) $data->get( 'expectedTranslatorId' );
		$currentUserId  = User::getCurrentId();

		$hasTranslateCap = function () {
			return current_user_can( User::CAP_TRANSLATE ) || current_user_can( User::CAP_MANAGE_OPTIONS );
		};

		return Either::of( $jobId )
			->chain(
				function ( $id ) use ( $hasTranslateCap ) {
					return $hasTranslateCap() ? Either::right( $id ) : Either::left( 'not_allowed' );
				}
			)
			->chain(
				function ( $id ) use ( $expectedTassId, $currentUserId ) {
					return $this->loadTakeableJob( $id, $expectedTassId, $currentUserId );
				}
			)
			->chain(
				function ( $job ) use ( $currentUserId ) {
					if ( ! $job->assign_to( $currentUserId ) ) {
						return Either::left( 'reassign_failed' );
					}

					return Either::right( [ 'editUrl' => Jobs::getEditUrl( null, $job->get_id() ) ] );
				}
			);
	}

	public function loadTakeableJob( $jobId, $expectedTranslatorId, $currentUserId ) {
		$job = $jobId ? wpml_tm_load_job_factory()->get_translation_job_as_active_record( TranslationRecord::rowToOpen( $jobId ) ) : null;

		if ( ! $job ) {
			return Either::left( 'job_not_found' );
		}

		if ( (int) $job->get_translator_id() !== (int) $expectedTranslatorId ) {
			return Either::left( 'status_changed' );
		}

		$decision = Decision::forJob( $job->get_status_value(), $job->get_translator_id(), $currentUserId );
		if ( Decision::HARD !== $decision ) {
			return Either::left( 'status_changed' );
		}

		if ( ! $job->user_can_translate( new \WP_User( $currentUserId ) ) ) {
			return Either::left( 'not_allowed' );
		}

		return Either::right( $job );
	}
}
