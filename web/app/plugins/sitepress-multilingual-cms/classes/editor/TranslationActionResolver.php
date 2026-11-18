<?php

namespace WPML\TM\Editor;

use WPML\Core\Component\Post\Application\LanguagePanel\SiteEditorEntityTypes;
use WPML\Element\API\Languages;
use WPML\FP\Obj;
use WPML\LIB\WP\User;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\API\ErrorMessages;
use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\ATE\JobCreation\AteCreateReconciler;
use WPML\TM\Editor\Dispatch\DispatchPlan;
use WPML\TM\Editor\Dispatch\DispatchPlanFactory;
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

	private $selfTranslator;

	private $plan;

	public function __construct( Editor $editor, SelfTranslator $selfTranslator, ?DispatchPlan $plan = null ) {
		$this->editor         = $editor;
		$this->selfTranslator = $selfTranslator;
		$this->plan           = $plan ?: DispatchPlanFactory::create();
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
			return $this->openOriginal( $postId, $sourceLanguage, $intent );
		}

		$listedSource = self::VERB_TRANSLATE === $verb
			? $this->getListedSource( $trid, $targetLanguage, Obj::prop( 'sourceLanguage', $intent ), $sourceLanguage )
			: null;

		list( $jobSourcePostId, $jobSourceLanguage ) = $listedSource ?: $this->getRecordedSource( $trid, $targetLanguage, $postId, $sourceLanguage );

		if ( ! $this->canTranslatePair( $jobSourceLanguage, $targetLanguage, $jobSourcePostId ) ) {
			return $this->blocked( 'not-allowed', __( 'You are not allowed to translate this language pair.', 'sitepress' ) );
		}

		$refusal = $this->plan->refusesClick( $trid, $postId, $targetLanguage, $verb );
		if ( null !== $refusal ) {
			return $this->blocked( $refusal, $this->refusalMessage( $refusal, $postId, $targetLanguage ) );
		}

		if ( self::VERB_TRANSLATE === $verb && $this->plan->translatesAutomatically( $trid, $postId, $targetLanguage ) ) {
			$answer = $this->translateAutomatically( $trid, $postId, $targetLanguage, Obj::prop( 'currentUrl', $intent ) );
			if ( null !== $answer ) {
				return $answer;
			}
		}

		return $this->mapEditorResponse(
			$this->editor->open( $this->buildOpenParams( $intent, $trid, $targetLanguage, $jobSourceLanguage, $verb ) ),
			Obj::prop( 'currentUrl', $intent )
		);
	}

	private function openOriginal( $postId, $language, array $intent ) {
		$url = get_edit_post_link( $postId, 'raw' );
		if ( ! $url ) {
			return $this->blocked( 'cannot-edit-original', __( 'You are not allowed to edit this post.', 'sitepress' ) );
		}

		if ( SiteEditorEntityTypes::SCREEN === Obj::prop( 'screen', $intent ) && $this->isPageInTheWordPressEditor( $postId ) ) {
			$url = admin_url( SiteEditorEntityTypes::pageEditorPath( (int) $postId, (string) $language ) );
		}

		return [
			'action' => self::ACTION_NAVIGATE,
			'editor' => 'wp',
			'url'    => $url,
		];
	}

	private function isPageInTheWordPressEditor( $postId ) {
		$post = get_post( (int) $postId );

		return $post
		       && SiteEditorEntityTypes::PAGE === $post->post_type
		       && (bool) \WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( (int) $postId );
	}

	private function canTranslatePair( $langFrom, $langTo, $postId ) {
		$userId = User::getCurrentId();
		$args   = [
			'lang_from'      => $langFrom,
			'lang_to'        => $langTo,
			'admin_override' => User::isAdministrator(),
			'post_id'        => $postId,
		];

		$default = false;
		if ( \WPML\Plugins::isTMLoadedForRequest() && function_exists( 'wpml_tm_load_blog_translators' ) ) {
			$translators = wpml_tm_load_blog_translators();
			if ( $translators instanceof \WPML_TM_Blog_Translators ) {
				$default = (bool) $translators->is_translator( $userId, $args );
			}
		}

		$allowed = (bool) apply_filters( 'wpml_is_translator', $default, $userId, $args );

		if ( ! $allowed ) {
			$allowed = $this->selfTranslator->can_translate_pair( $langFrom, $langTo );
		}

		return $allowed;
	}

	private function translateAutomatically( $trid, $postId, $targetLanguage, $currentUrl ) {
		global $wpml_translation_job_factory;

		$openLock   = new OpenLock();
		$lock       = $openLock->acquire( [ 'trid' => $trid, 'language_code' => $targetLanguage ] );
		$waitingJob = null;

		try {
			$openLock->readLive();

			$existing = Jobs::getTridJob( $trid, $targetLanguage );

			if ( $existing && ! (int) Obj::propOr( 0, 'translated', $existing ) ) {
				$existingJobId = (int) Obj::prop( 'job_id', $existing );

				if ( (int) Obj::propOr( 0, 'automatic', $existing ) ) {
					$waitingJob = $existing;
				} elseif ( $existingJobId && ! (int) Obj::propOr( 0, 'editor_job_id', $existing ) ) {
					$answer = $this->resolveLeftBehindJob( $existingJobId, $currentUrl, $existing, $postId );
					if ( null !== $answer ) {
						return $answer;
					}
				}
			}

			if ( ! $waitingJob ) {
				$refusal = $this->plan->refusesNewAutomaticJob();
				if ( null !== $refusal ) {
					return $this->blocked( $refusal, $this->refusalMessage( $refusal, $postId, $targetLanguage ) );
				}

				$jobId = $wpml_translation_job_factory->create_local_post_job( $postId, $targetLanguage );
			}
		} finally {
			if ( $lock ) {
				$lock->release();
			}
		}

		if ( $waitingJob ) {
			if ( $this->plan->pullsDelivery( (int) Obj::propOr( 0, 'editor_job_id', $waitingJob ) ) ) {
				return null;
			}

			return [
				'action' => self::ACTION_AUTO_TRANSLATE,
				'jobId'  => (int) Obj::prop( 'job_id', $waitingJob ),
			];
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

		if ( SiteEditorEntityTypes::SCREEN === Obj::prop( 'screen', $intent ) ) {
			$params['screen'] = SiteEditorEntityTypes::SCREEN;
		}

		$renderedAt = (int) filter_var( Obj::prop( 'renderedAt', $intent ), FILTER_SANITIZE_NUMBER_INT );
		if ( $renderedAt > 0 ) {
			$params[ \WPML_Post_Status_Display::RENDERED_AT_PARAM ] = $renderedAt;
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

	private function getListedSource( $trid, $targetLanguage, $listedLanguage, $originalLanguage ) {
		if (
			! is_string( $listedLanguage )
			|| '' === $listedLanguage
			|| $listedLanguage === $targetLanguage
			|| $listedLanguage === $originalLanguage
		) {
			return null;
		}

		global $sitepress;

		$translations = $sitepress->post_translations();
		if ( $translations->get_element_id( $targetLanguage, $trid ) ) {
			return null;
		}

		$listedId = (int) $translations->get_element_id( $listedLanguage, $trid );

		return $listedId ? [ $listedId, $listedLanguage ] : null;
	}

	private function getSourceLanguage( $postId ) {
		global $sitepress;

		return $sitepress->post_translations()->get_element_lang_code( $postId );
	}

	private function refusalMessage( $reason, $postId, $targetLanguage ) {
		if ( DispatchPlan::REFUSAL_IN_TRASH === $reason ) {
			return sprintf(
				/* translators: Shown next to a translation icon when the original is in the Trash. %s: the post type's name in the singular, lower-cased (post, page, product). */
				__( 'This %s is in the Trash. Restore it to translate it.', 'sitepress' ),
				$this->postTypeLabel( $postId )
			);
		}

		if ( DispatchPlan::REFUSAL_LANGUAGE_PAUSED === $reason ) {
			return sprintf(
				/* translators: Shown next to a translation icon when translation into the language is paused. %s: the language name. */
				__( 'Translation into %s is paused.', 'sitepress' ),
				$this->languageName( $targetLanguage )
			);
		}

		if ( DispatchPlan::REFUSAL_NO_WORDS !== $reason ) {
			/* translators: Shown next to the "+" icon of a translation when WPML cannot translate it automatically now. */
			return __( 'WPML cannot translate this automatically now.', 'sitepress' );
		}

		if ( User::canManageTranslations() ) {
			/* translators: Shown next to the "+" icon of a translation when WPML cannot translate it automatically because the site's automatic translation account has no words left. */
			return __( 'Your automatic translation account has no words left. Get more words to translate this automatically.', 'sitepress' );
		}

		/* translators: Shown next to the "+" icon of a translation to a user who cannot buy words, when WPML cannot translate it automatically because the site's automatic translation account has no words left. */
		return __( 'The automatic translation account of this site has no words left. Ask an administrator to get more words.', 'sitepress' );
	}

	private function postTypeLabel( $postId ) {
		$postType = get_post_type_object( (string) get_post_type( $postId ) );
		$label    = $postType ? (string) Obj::pathOr( '', [ 'labels', 'singular_name' ], $postType ) : '';

		/* translators: The name of a content type inside a sentence, as in "This post has 3 translations" in the dialog shown before content is deleted, and the fallback name of a post type that has no name. Singular, in lower case. */
		return '' !== $label ? strtolower( $label ) : __( 'post', 'sitepress' );
	}

	private function languageName( $languageCode ) {
		return (string) Obj::propOr( $languageCode, 'display_name', Languages::getLanguageDetails( $languageCode ) );
	}

	private function blocked( $reason, $message ) {
		return [
			'action'  => self::ACTION_BLOCKED,
			'reason'  => $reason,
			'message' => $message,
		];
	}
}
