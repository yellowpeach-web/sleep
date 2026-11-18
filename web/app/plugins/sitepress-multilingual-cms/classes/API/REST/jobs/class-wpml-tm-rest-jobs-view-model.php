<?php

use WPML\Element\API\PostTranslations;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\TM\API\Jobs;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\TM\ATE\JobCreation\CreateFailureRecord;
use WPML\TM\ATE\PullDelivery\State as PullDeliveryState;
use WPML\TM\AutomaticTranslation\Actions\DeferredDispatch;
use WPML\WP\OptionManager;
use WPML\TM\ATE\Review\PreviewLink;
use WPML\TM\ATE\Review\ReviewStatus;
use WPML\TM\Jobs\Utils\ElementLink;
use WPML\TM\Menu\TranslationQueue\TranslationQueuePage;

class WPML_TM_Rest_Jobs_View_Model {
	private $translation_service;

	private $element_info;

	private $language_names;

	private $translator_name;

	private $progress;

	private $element_link;

	private $next_try_in;

	private $translate_everything_on;

	public function __construct(
		WPML_TM_Rest_Jobs_Translation_Service $translation_service,
		WPML_TM_Rest_Jobs_Element_Info $element_info,
		WPML_TM_Rest_Jobs_Language_Names $language_names,
		WPML_TM_Rest_Job_Translator_Name $translator_name,
		WPML_TM_Rest_Job_Progress $progress,
		ElementLink $element_link
	) {
		$this->translation_service = $translation_service;
		$this->element_info        = $element_info;
		$this->language_names      = $language_names;
		$this->translator_name     = $translator_name;
		$this->progress            = $progress;
		$this->element_link        = $element_link;
	}

	public function build( WPML_TM_Jobs_Collection $jobs, $total_jobs_count, WPML_TM_Jobs_Search_Params $jobs_search_params, $page_name = '' ) {
		$result = [ 'jobs' => [] ];

		$held_for_payment = array_map( 'intval', (array) PullDeliveryState::getKey( 'payment_pending_job_ids', [] ) );

		$parked              = array_map( 'intval', (array) PullDeliveryState::getKey( 'insufficient_balance_job_ids', [] ) );
		$payAsYouGo          = $this->isPayAsYouGoAccount();
		$waiting_for_payment = $payAsYouGo ? $parked : [];
		$waiting_for_words = $payAsYouGo ? [] : $parked;

		foreach ( $jobs as $job ) {
			$result['jobs'][] = $this->map_job( $job, $jobs_search_params, $page_name, $held_for_payment, $waiting_for_payment, $waiting_for_words );
		}

		$result['total'] = $total_jobs_count;

		return $result;
	}

	private function map_job( WPML_TM_Job_Entity $job, WPML_TM_Jobs_Search_Params $jobs_search_params, $page_name = '', array $held_for_payment = [], array $waiting_for_payment = [], array $waiting_for_words = [] ) {
		$extra_data          = [];
		$viewUrl             = '';
		$heldForPayment      = false;
		$waitingForPayment   = false;
		$waitingForWords     = false;
		$sending             = false;
		$translatedElementId = null;
		$viewOpensEditor     = false;

		if ( $job instanceof WPML_TM_Post_Job_Entity ) {
			$extra_data['icl_translate_job_id'] = $job->get_translate_job_id();
			$extra_data['editor_job_id']        = $job->get_editor_job_id();
			$heldForPayment                     = in_array( (int) $job->get_translate_job_id(), $held_for_payment, true );
			$waitingForPayment                  = in_array( (int) $job->get_translate_job_id(), $waiting_for_payment, true );
			$waitingForWords                    = in_array( (int) $job->get_translate_job_id(), $waiting_for_words, true );
			$sending = DeferredDispatch::isParkedForSending( $job->get_status(), $job->get_translate_job_id() );

			$viewUrl = WPML_TM_Job_Entity::TAXONOMY_TYPE === $job->get_type()
				? ''
				: $this->getViewUrl( $job, $jobs_search_params, $page_name );

			if ( WPML_TM_Job_Entity::POST_TYPE === $job->get_type() ) {
				$translatedElementId = (int) $job->get_element_id() > 0 ? (int) $job->get_element_id() : null;
				$viewOpensEditor     = $this->isPostTypeWithoutPublicView( $job );
			}
		}

		return [
			'id'                     => $job->get_rid(),
			'original_element_id'    => $job->get_original_element_id(),
			'type'                   => $job->get_type(),
			'tp_id'                  => $job->get_tp_id(),
			'status'                 => $job->get_status(),
			'needs_update'           => $job->does_need_update(),
			'review_status'          => $job->get_review_status(),
			'language_codes'         => [
				'source' => $job->get_source_language(),
				'target' => $job->get_target_language(),
			],
			'languages'              => [
				'source' => $this->language_names->get( $job->get_source_language() ),
				'target' => $this->language_names->get( $job->get_target_language() ),
			],
			'translation_service_id' => $job->get_translation_service(),
			'translation_service'    => $this->translation_service->get_name( $job->get_translation_service() ),
			'sent_date'              => $job->get_sent_date()->format( 'Y-m-d' ),
			'deadline'               => $job->get_deadline() ? $job->get_deadline()->format( 'Y-m-d' ) : '',
			'ts_status'              => (string) $job->get_ts_status(),
			'element'                => $this->element_info->get( $job ),
			'translator_name'        => $job->get_translator_id() ? $this->translator_name->get( $job->get_translator_id() ) : '',
			'translator_id'          => $job->get_translator_id() ? (int) $job->get_translator_id() : '',
			'is_ate_job'             => $job->is_ate_job(),
			'automatic'              => $job->is_automatic(),
			'created_by_translate_everything' => $job->is_created_by_translate_everything(),
			'held_for_payment'       => $heldForPayment,
			'waiting_for_payment'    => $waitingForPayment,
			'waiting_for_words'      => $waitingForWords,
			'sending'                => $sending,
			'create_failure'         => $this->create_failure( $job, $sending ),
			'progress'               => $this->progress->get( $job ),
			'batch'                  => [
				'id'    => $job->get_batch()->get_id(),
				'name'  => $job->get_batch()->get_name(),
				'tp_id' => $job->get_batch()->get_tp_id(),
			],
			'extra_data'             => $extra_data,
			'edit_url'               => $this->get_edit_url( $job ),
			'view_url'               => $viewUrl,
			'view_original_url'      => $this->element_link->getOriginal( $job ),
			'translated_element_id'  => $translatedElementId,
			'view_opens_editor'      => $viewOpensEditor,
		];
	}

	private function create_failure( WPML_TM_Job_Entity $job, $sending ) {
		if ( $sending || ! $job instanceof WPML_TM_Post_Job_Entity ) {
			return null;
		}

		$status = (int) $job->get_status();
		if ( ! in_array( $status, [ ICL_TM_ATE_NEEDS_RETRY, TranslationStatus::ATE_UNSOLVABLE ], true ) ) {
			return null;
		}

		$failure = CreateFailureRecord::read( $job->get_translate_job_id() );
		if ( TranslationStatus::ATE_UNSOLVABLE === $status ) {
			return $failure ? [ 'kind' => $failure['kind'], 'next_try_in' => null ] : null;
		}

		$kind = $failure ? $failure['kind'] : '';

		if ( $job->is_automatic() && ! $this->isTranslateEverythingOn() ) {
			return [
				'kind'        => $kind,
				'next_try_in' => null,
				'held'        => true,
			];
		}

		if ( null === $this->next_try_in ) {
			$this->next_try_in = CreateFailureRecord::secondsUntilNextTry();
		}

		return [
			'kind'        => $kind,
			'next_try_in' => $this->next_try_in,
			'held'        => false,
		];
	}

	private function isTranslateEverythingOn() {
		if ( null === $this->translate_everything_on ) {
			$this->translate_everything_on = AutomaticTranslationCapabilities::shouldTranslateEverythingFresh();
		}

		return $this->translate_everything_on;
	}

	private function isPostTypeWithoutPublicView( WPML_TM_Post_Job_Entity $job ) {
		$elementType = (string) $job->get_element_type();
		$prefix      = WPML_TM_Job_Entity::POST_TYPE . '_';

		if ( 0 !== strpos( $elementType, $prefix ) ) {
			return false;
		}

		$postType = get_post_type_object( substr( $elementType, strlen( $prefix ) ) );

		return ! $postType || empty( $postType->public ) || empty( $postType->show_ui );
	}

	private function get_edit_url( $job ) {
		$edit_url = '';
		if ( $job->get_original_element_id() ) {

			$jobId = $job instanceof WPML_TM_Post_Job_Entity ? $job->get_translate_job_id() : $job->get_rid();

			$translation_queue_page = admin_url( TranslationQueuePage::base() . '&job_id=' . $jobId );
			$edit_url               = apply_filters( 'icl_job_edit_url', $translation_queue_page, $jobId );
		}

		return $edit_url;
	}

	private function getViewUrl( WPML_TM_Post_Job_Entity $job, WPML_TM_Jobs_Search_Params $jobs_search_params, $page_name = '' ) {
		$needsReview = Lst::includes( $job->get_review_status(), [
			ReviewStatus::NEEDS_REVIEW,
			ReviewStatus::EDITING
		] );

		return $needsReview ? $this->getReviewUrl( $job, $jobs_search_params, $page_name ) : $this->element_link->getTranslation( $job );
	}

	private function getReviewUrl( WPML_TM_Post_Job_Entity $job, WPML_TM_Jobs_Search_Params $jobs_search_params, $page_name = '' ) {
		$target_language = $jobs_search_params->get_target_language();
		$element_type    = $jobs_search_params->get_element_type();

		$filterParams = '';

		if ( is_string( $element_type ) && strlen( $element_type ) > 0 ) {
			$filterParams .= '&element_type=' . $element_type;
		}
		if ( is_array( $target_language ) && count( $target_language ) > 0 ) {
			$filterParams .= '&targetLanguages=' . implode( ',', $target_language );
		}

		$returnPage = WPML_TM_Jobs_List_Script_Data::TM_JOBS_PAGE === $page_name
			? 'admin.php?page=' . TranslationQueuePage::SLUG . '&tab=jobs'
			: TranslationQueuePage::base();

		$returnUrl = admin_url( $returnPage . $filterParams );

		$translation     = PostTranslations::getInLanguage( $job->get_original_element_id(), $job->get_target_language() );

		if ( ! $translation || ! $translation->element_id ) {
			$element_id = (int) $job->get_element_id();

			if ( $element_id <= 0 || ! get_post( $element_id ) ) {
				return PreviewLink::getWithSpecifiedReturnUrl( $returnUrl, $element_id, $job->get_translate_job_id() );
			}

			return '';
		}

		return PreviewLink::getWithSpecifiedReturnUrl( $returnUrl, $translation->element_id, $job->get_translate_job_id() );
	}

	private function isPayAsYouGoAccount() {
		$credits = OptionManager::getOr( [], 'TM', 'Account::credits' );

		return is_array( $credits ) && ! empty( $credits['pay_as_you_go'] );
	}
}
