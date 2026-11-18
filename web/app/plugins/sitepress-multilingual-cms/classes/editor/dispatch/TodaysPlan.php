<?php

namespace WPML\TM\Editor\Dispatch;

use WPML\TM\ATE\AutoTranslate\Eligibility;

class TodaysPlan implements DispatchPlan {

	private $eligibility;

	public function __construct( Eligibility $eligibility ) {
		$this->eligibility = $eligibility;
	}

	public function translatesAutomatically( $trid, $postId, $targetLanguage ) {
		return $this->eligibility->shouldAutoTranslate( $trid, $postId, $targetLanguage );
	}

	public function refusesNewAutomaticJob() {
		return null;
	}

	public function refusesClick( $trid, $postId, $targetLanguage, $verb ) {
		return null;
	}

	public function opensClassicEditor( $jobObject, callable $getPreviousJob ) {
		if ( ! \WPML_TM_ATE_Status::is_editor_ate() ) {
			return true;
		}

		$previousJob = $getPreviousJob();

		if ( ! $jobObject->get_basic_data_property( 'translated' ) && $previousJob ) {
			return wpml_tm_load_old_jobs_editor()->shouldStickToWPMLEditor( $jobObject->get_id(), $previousJob );
		}

		return wpml_tm_load_old_jobs_editor()->editorForTranslationsPreviouslyCreatedUsingCTE() === \WPML_TM_Editors::WPML &&
		       wpml_tm_load_old_jobs_editor()->get_current_editor( $jobObject->get_id() ) === \WPML_TM_Editors::WPML;
	}

	public function pullsDelivery( $ateJobId ) {
		return false;
	}

	public function asksNativeEditUrl() {
		return false;
	}

	public function opensRequestOnOpen() {
		return true;
	}
}
