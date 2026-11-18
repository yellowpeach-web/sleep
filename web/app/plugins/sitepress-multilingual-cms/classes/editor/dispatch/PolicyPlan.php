<?php

namespace WPML\TM\Editor\Dispatch;

use WPML\TM\ATE\AutoTranslate\PolicyEligibility;
use WPML\TM\ATE\PullDelivery\ReturnDelivery;
use WPML\TM\ATE\Receive\SingleJobDelivery;
use WPML\LanguageEditor\TranslationPause;
use WPML\TM\Editor\TranslationActionResolver;
use WPML\TM\Jobs\Manual;
use WPML\TranslationPolicy\Policy\PolicyReaderInterface;
use WPML\TranslationPolicy\Policy\PolicyTable;

class PolicyPlan implements DispatchPlan {

	private $eligibility;

	private $reader;

	private $delivery;

	private $manual;

	public function __construct( PolicyEligibility $eligibility, PolicyReaderInterface $reader, ReturnDelivery $delivery, $manual = null ) {
		$this->eligibility = $eligibility;
		$this->reader      = $reader;
		$this->delivery    = $delivery;
		$this->manual      = $manual ?: new Manual();
	}

	public function translatesAutomatically( $trid, $postId, $targetLanguage ) {
		return $this->eligibility->shouldAutoTranslate( $trid, $postId, $targetLanguage );
	}

	public function refusesNewAutomaticJob() {
		return $this->eligibility->refusesNewJob();
	}

	public function refusesClick( $trid, $postId, $targetLanguage, $verb ) {
		if ( 'trash' === get_post_status( (int) $postId ) ) {
			return self::REFUSAL_IN_TRASH;
		}

		if ( ! TranslationPause::isPaused( $targetLanguage ) || TranslationActionResolver::VERB_REVIEW === $verb ) {
			return null;
		}

		if ( TranslationActionResolver::VERB_EDIT === $verb
			&& PolicyTable::EDITOR_NATIVE === $this->reader->forPost( (int) $postId )->editor()
		) {
			return null;
		}

		if ( $this->manual->isLocalJobInProgress( [ 'trid' => $trid, 'language_code' => $targetLanguage ] ) ) {
			return null;
		}

		return self::REFUSAL_LANGUAGE_PAUSED;
	}

	public function opensClassicEditor( $jobObject, callable $getPreviousJob ) {
		if ( $this->isInProgress( $jobObject ) ) {
			$ownEditor = $jobObject->get_basic_data_property( 'editor' );

			if ( \WPML_TM_Editors::WPML === $ownEditor ) {
				return true;
			}

			if ( \WPML_TM_Editors::ATE === $ownEditor && \WPML_TM_ATE_Status::can_ate_job_exist() ) {
				return false;
			}
		}

		if ( $jobObject instanceof \WPML_Post_Translation_Job ) {
			$editor = $this->reader->forPost( (int) $jobObject->get_original_element_id() )->editor();

			if ( PolicyTable::EDITOR_CTE === $editor ) {
				return true;
			}

			if ( PolicyTable::EDITOR_ATE === $editor ) {
				return false;
			}
		}

		return ! \WPML_TM_ATE_Status::is_editor_ate();
	}

	public function pullsDelivery( $ateJobId ) {
		if ( ! $ateJobId ) {
			return false;
		}

		$outcome = $this->delivery->deliver( (int) $ateJobId );

		return in_array( $outcome, [ SingleJobDelivery::APPLIED, SingleJobDelivery::ALREADY_APPLIED ], true );
	}

	public function asksNativeEditUrl() {
		return true;
	}

	public function opensRequestOnOpen() {
		return false;
	}

	private function isInProgress( $jobObject ) {
		return ! $jobObject->get_basic_data_property( 'translated' )
			&& in_array( (int) $jobObject->get_status_value(), Manual::IN_PROGRESS_STATUSES, true );
	}
}
