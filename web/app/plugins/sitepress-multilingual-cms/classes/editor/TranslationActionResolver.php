<?php

namespace WPML\TM\Editor;

use WPML\FP\Obj;
use WPML\LIB\WP\User;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\API\ErrorMessages;
use WPML\TM\ATE\AutoTranslate\Eligibility;
use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\ATE\JobCreation\AteCreateReconciler;
use WPML\TranslationRoles\SelfTranslator;
use function WPML\Container\make;

class TranslationActionResolver {

	const ACTION_AUTO_TRANSLATE = 'auto-translate';
	const ACTION_NAVIGATE       = 'navigate';
	const ACTION_BLOCKED        = 'blocked';

	const VERB_TRANSLATE = 'translate';
	const VERB_EDIT      = 'edit';
	const VERB_REVIEW    = 'review';

	const VERBS = [ self::VERB_TRANSLATE, self::VERB_EDIT, self::VERB_REVIEW ];

	private $editor;

	private $eligibility;

	private $selfTranslator;

	public function __construct( Editor $editor, Eligibility $eligibility, SelfTranslator $selfTranslator ) {
		$this->editor         = $editor;
		$this->eligibility    = $eligibility;
		$this->selfTranslator = $selfTranslator;
	}

	public function resolve( array $intent ) {
		return ApiCommunication::duringUserSend(
			function () use ( $intent ) {
				return $this->doResolve( $intent );
			}
		);
	}

	private function doResolve( array $intent ) {
		$trid           = (int) Obj::prop( 'trid', $intent );
		$targetLanguage = (string) Obj::prop( 'targetLanguage', $intent );
		$verb           = Obj::propOr( self::VERB_TRANSLATE, 'verb', $intent );

		if ( ! $trid || ! $targetLanguage || ! in_array( $verb, self::VERBS, true ) ) {
			return $this->blocked( 'invalid-intent', __( 'The translation request is malformed.', 'sitepress' ) );
		}

		$postId = (int) \SitePress::get_original_element_id_by_trid( $trid );
		if ( ! $postId ) {
			return $this->blocked( 'post-not-found', __( 'Post cannot be found by trid', 'sitepress' ) );
		}

		$sourceLanguage = $this->getSourceLanguage( $postId );

		if ( $targetLanguage === $sourceLanguage ) {
			return $this->openOriginal( $postId );
		}

		list( $jobSourcePostId, $jobSourceLanguage ) = $this->getRecordedSource( $trid, $targetLanguage, $postId, $sourceLanguage );

		if ( ! $this->canTranslatePair( $jobSourceLanguage, $targetLanguage, $jobSourcePostId ) ) {
			return $this->blocked( 'not-allowed', __( 'You are not allowed to translate this language pair.', 'sitepress' ) );
		}

		if ( self::VERB_TRANSLATE === $verb && $this->eligibility->shouldAutoTranslate( $trid, $postId, $targetLanguage ) ) {
			return $this->translateAutomatically( $trid, $postId, $targetLanguage, Obj::prop( 'currentUrl', $intent ) );
		}

		return $this->mapEditorResponse(
			$this->editor->open( $this->buildOpenParams( $intent, $trid, $targetLanguage, $jobSourceLanguage, $verb ) ),
			Obj::prop( 'currentUrl', $intent )
		);
	}

	private function openOriginal( $postId ) {
		$url = get_edit_post_link( $postId, 'raw' );
		if ( ! $url ) {
			return $this->blocked( 'cannot-edit-original', __( 'You are not allowed to edit this post.', 'sitepress' ) );
		}

		return [
			'action' => self::ACTION_NAVIGATE,
			'editor' => 'wp',
			'url'    => $url,
		];
	}

	private function canTranslatePair( $langFrom, $langTo, $postId ) {
		$allowed = (bool) apply_filters( 'wpml_is_translator', false, User::getCurrentId(), [
			'lang_from'      => $langFrom,
			'lang_to'        => $langTo,
			'admin_override' => User::isAdministrator(),
			'post_id'        => $postId,
		] );

		if ( ! $allowed ) {
			$allowed = $this->selfTranslator->can_translate_pair( $langFrom, $langTo );
		}

		return $allowed;
	}

	private function translateAutomatically( $trid, $postId, $targetLanguage, $currentUrl ) {
		global $wpml_translation_job_factory;

		$lock = ( new OpenLock() )->acquire( [ 'trid' => $trid, 'language_code' => $targetLanguage ] );

		try {
			$existing = Jobs::getTridJob( $trid, $targetLanguage );

			if ( $existing && ! (int) Obj::propOr( 0, 'translated', $existing ) ) {
				$existingJobId = (int) Obj::prop( 'job_id', $existing );

				if ( (int) Obj::propOr( 0, 'automatic', $existing ) ) {
					return [
						'action' => self::ACTION_AUTO_TRANSLATE,
						'jobId'  => $existingJobId,
					];
				}

				if ( $existingJobId && ! (int) Obj::propOr( 0, 'editor_job_id', $existing ) ) {
					$answer = $this->resolveLeftBehindJob( $existingJobId, $currentUrl, $existing, $postId );
					if ( null !== $answer ) {
						return $answer;
					}
				}
			}

			$jobId = $wpml_translation_job_factory->create_local_post_job( $postId, $targetLanguage );
		} finally {
			if ( $lock ) {
				$lock->release();
			}
		}

		$job = Jobs::get( (int) $jobId );

		if ( ! $job ) {
			/* translators: Message shown when WPML could not make the translation job for a piece of content. */
			return $this->blocked( 'job-not-created', __( 'Job could not be created', 'sitepress' ) );
		}

		if ( Obj::prop( 'automatic', $job ) ) {
			return [
				'action' => self::ACTION_AUTO_TRANSLATE,
				'jobId'  => $jobId,
			];
		}

		return [
			'action' => self::ACTION_NAVIGATE,
			'editor' => 'wpml',
			'url'    => Jobs::getEditUrl( $currentUrl, $jobId ),
			'jobId'  => $jobId,
		];
	}

	private function resolveLeftBehindJob( $jobId, $currentUrl, $existing, $postId ) {
		$expiry = get_option( AteCreateReconciler::IN_FLIGHT_OPTION_PREFIX . $jobId, 0 );
		if ( is_numeric( $expiry ) && (int) $expiry > time() ) {
			return [
				'action' => self::ACTION_AUTO_TRANSLATE,
				'jobId'  => $jobId,
			];
		}

		if ( ! $this->isSourceUnchanged( $existing, $postId ) ) {
			return null;
		}

		if ( 'manual' === AteCreateReconciler::markedMode( $jobId ) ) {
			return null;
		}

		$ateApi  = make( \WPML_TM_ATE_API::class );
		$ateJobs = make( \WPML_TM_ATE_Jobs::class );

		if ( ! $ateApi || ! $ateJobs ) {
			return null;
		}

		$reconciler = new AteCreateReconciler( $ateApi, $ateJobs );
		$result     = $reconciler->reconcile( [ $jobId ] );

		if ( AteCreateReconciler::OUTCOME_NO_ANSWER === $result['outcome'] ) {
			return $this->blocked( 'ate-not-responding', ErrorMessages::serverUnavailableHeader() );
		}

		if ( AteCreateReconciler::OUTCOME_ADOPTED !== $result['outcome'] ) {
			return null;
		}

		$byClone = AteCreateReconciler::markedByClone( $jobId );
		$reconciler->bind( $result['adopted'], [ $jobId => $byClone ? 'manual' : 'auto' ], Jobs::SENT_MANUALLY );

		ApiCommunication::noteCreateConfirmed();

		if ( $byClone ) {
			return [
				'action' => self::ACTION_NAVIGATE,
				'editor' => 'wpml',
				'url'    => Jobs::getEditUrl( $currentUrl, $jobId ),
				'jobId'  => $jobId,
			];
		}

		return [
			'action' => self::ACTION_AUTO_TRANSLATE,
			'jobId'  => $jobId,
		];
	}

	private function isSourceUnchanged( $existing, $postId ) {
		if ( (int) Obj::propOr( 0, 'needs_update', $existing ) ) {
			return false;
		}

		$rid = (int) Obj::prop( 'rid', $existing );
		if ( $rid <= 0 ) {
			return false;
		}

		global $wpdb;

		$md5 = wpml_tm_get_records()->icl_translation_status_by_rid( $rid )->md5();
		if ( null === $md5 && ! empty( $wpdb->last_error ) ) {
			return true;
		}

		return ( new \WPML_TM_Action_Helper() )->post_md5_matches( $postId, $md5 );
	}

	private function buildOpenParams( $intent, $trid, $targetLanguage, $sourceLanguage, $verb ) {
		$params = [
			'trid'                 => $trid,
			'language_code'        => $targetLanguage,
			'source_language_code' => $sourceLanguage,
		];

		if ( self::VERB_REVIEW === $verb ) {
			$params['preview'] = 1;
		}

		$currentUrl = Obj::prop( 'currentUrl', $intent );
		if ( $currentUrl ) {
			$params['return_url'] = $currentUrl;
		}

		return $params;
	}

	private function mapEditorResponse( $response, $currentUrl ) {
		$jobObject = Obj::prop( 'jobObject', $response );
		$jobId     = $jobObject ? (int) $jobObject->get_id() : null;

		switch ( Obj::prop( 'editor', $response ) ) {
			case \WPML_TM_Editors::ATE:
				$answer = [ 'action' => self::ACTION_NAVIGATE, 'editor' => 'ate', 'url' => Obj::prop( 'url', $response ) ];
				break;
			case \WPML_TM_Editors::WP:
				$answer = [ 'action' => self::ACTION_NAVIGATE, 'editor' => 'wp', 'url' => Obj::prop( 'url', $response ) ];
				break;
			case \WPML_TM_Editors::WPML:
				$answer = [ 'action' => self::ACTION_NAVIGATE, 'editor' => 'wpml', 'url' => Jobs::getEditUrl( $currentUrl, $jobId ) ];
				break;
			default:
				return $this->blocked( 'editor-unavailable', Editor::noEditorMessage( Obj::prop( 'reason', $response ) ) );
		}

		if ( $jobId ) {
			$answer['jobId'] = $jobId;
		}

		return $answer;
	}

	private function getRecordedSource( $trid, $targetLanguage, $originalId, $originalLanguage ) {
		global $sitepress;

		$translations = $sitepress->post_translations();
		$targetId     = (int) $translations->get_element_id( $targetLanguage, $trid );
		$recorded     = $targetId ? $translations->get_source_lang_code( $targetId ) : null;
		$sourceId     = $recorded && $recorded !== $targetLanguage ? (int) $translations->get_element_id( $recorded, $trid ) : 0;

		return $sourceId ? [ $sourceId, (string) $recorded ] : [ $originalId, $originalLanguage ];
	}

	private function getSourceLanguage( $postId ) {
		global $sitepress;

		return $sitepress->post_translations()->get_element_lang_code( $postId );
	}

	private function blocked( $reason, $message ) {
		return [
			'action'  => self::ACTION_BLOCKED,
			'reason'  => $reason,
			'message' => $message,
		];
	}
}
