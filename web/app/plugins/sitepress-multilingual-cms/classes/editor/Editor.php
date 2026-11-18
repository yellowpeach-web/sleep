<?php

namespace WPML\TM\Editor;

use WPML\Core\Component\Post\Application\LanguagePanel\SiteEditorEntityTypes;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Left;
use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\UrlHandling\UrlBuilder;
use WPML\FP\Right;
use WPML\LIB\WP\User;
use WPML\Setup\Option as SetupOption;
use WPML\TM\API\ATE;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Log\Entry;
use WPML\TM\ATE\Log\Storage;
use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\ATE\ClonedSites\ReconnectState;
use WPML\TM\ATE\JobCreation\AteCreateReconciler;
use WPML\TM\ATE\Review\ReviewStatus;
use WPML\TM\ATE\Sync\Trigger;
use WPML\TM\Editor\Dispatch\DispatchPlan;
use WPML\TM\Editor\Dispatch\DispatchPlanFactory;
use WPML\TM\Jobs\JobLog;
use WPML\TM\Jobs\Manual;
use WPML\TM\Jobs\TranslationRecord;
use WPML\TM\Menu\TranslationQueue\CloneJobs;
use WPML\UIPage;
use function WPML\Container\make;
use function WPML\FP\curryN;
use function WPML\FP\invoke;
use function WPML\FP\pipe;

class Editor {

	const ATE_JOB_COULD_NOT_BE_CREATED = 101;
	const ATE_EDITOR_URL_COULD_NOT_BE_FETCHED = 102;
	const ATE_IS_NOT_ACTIVE = 103;
	const ATE_SITE_RECONNECTING = 104;
	const ATE_JOB_CANCELLED_IN_WPML = 105;
	const ATE_DELIVERY_NOT_APPLIED = 106;
	const ATE_AUTOMATIC_TRANSLATION_RUNNING = 107;

	const REASON_JOB_NOT_FOUND = 'job-not-found';
	const REASON_JOB_NOT_CREATED = 'job-not-created';
	const REASON_TRANSLATION_RECORD_NOT_WRITTEN = 'translation-record-not-written';
	const REASON_NOT_ALLOWED = 'not-allowed';
	const REASON_EDITOR_CANNOT_OPEN = 'editor-cannot-open';
	const REASON_NOTHING_TO_TRANSLATE = 'nothing-to-translate';
	const REASON_TRANSLATION_NOT_CREATED = 'translation-not-created';
	const REASON_PART_TRANSLATION_NOT_CREATED = 'part-translation-not-created';
	const REASON_PAGE_TRANSLATION_NOT_CREATED = 'page-translation-not-created';
	const REASON_CANNOT_CREATE_TEMPLATE = 'cannot-create-template';
	const REASON_CANNOT_CREATE_PART = 'cannot-create-template-part';
	const REASON_CANNOT_CREATE_PAGE = 'cannot-create-page';

	private $clone_jobs;

	private $manualJobs;

	private $record;

	private $cloneRefused = [];

	private $ateOpenFailed = false;

	private $siteEditorCreator;

	private $plan;

	public function __construct( CloneJobs $clone_jobs, Manual $manualJobs, ?TranslationRecord $record = null, ?SiteEditorTranslationCreator $siteEditorCreator = null, ?DispatchPlan $plan = null ) {
		$this->clone_jobs        = $clone_jobs;
		$this->manualJobs        = $manualJobs;
		$this->record            = $record ?: new TranslationRecord();
		$this->siteEditorCreator = $siteEditorCreator ?: new SiteEditorTranslationCreator();
		$this->plan              = $plan ?: DispatchPlanFactory::create();
	}

	public function open( $params ) {
		return JobLog::runInGroup(
			JobLog::GROUP_ID_EDITOR_OPEN,
			'Translation editor opened',
			function () use ( $params ) {
				return ApiCommunication::duringUserSend(
					function () use ( $params ) {
						$openLock = new OpenLock();
						$lock     = $openLock->acquire( is_array( $params ) ? $params : [] );

						try {
							$openLock->readLive();

							return $this->doOpen( $params );
						} finally {
							if ( $lock ) {
								$lock->release();
							}
						}
					}
				);
			}
		);
	}

	private function doOpen( $params ) {
		$shouldOpenCTE = function ( $jobObject ) use ( $params ) {
			return $this->plan->opensClassicEditor(
				$jobObject,
				function () use ( $params, $jobObject ) {
					return $this->previousJob( $params, $jobObject );
				}
			);
		};

		$maybeUpdateTranslationServiceColumn = function ( $jobObject ) {
			if ( $jobObject->get_translation_service() !== 'local' && ! TranslationRecord::isCompleted( $jobObject ) ) {
				$jobObject->set_basic_data_property( 'translation_service', 'local' );
				Jobs::setTranslationService( $jobObject->get_id(), 'local' );
			}

			return $jobObject;
		};

		$this->ateOpenFailed  = false;

		$automaticJobInFlight = ! Obj::prop( 'preview', $params ) ? $this->manualJobs->getAutomaticJobInFlight( $params ) : null;
		if ( $automaticJobInFlight ) {
			$returnUrl = $this->getReturnUrl( $params );

			return [
				'editor' => \WPML_TM_Editors::ATE,
				'url'    => add_query_arg(
					[
						'ateJobCreationError' => self::ATE_AUTOMATIC_TRANSLATION_RUNNING,
						'jobId'               => (int) Obj::prop( 'job_id', $automaticJobInFlight ),
					],
					$returnUrl ? $returnUrl : UIPage::getTMDashboard()
				),
			];
		}

		$isLocalJobInProgress =$this->manualJobs->isLocalJobInProgress( $params )
		                        && ! $this->noWpmlEditorCanOpenTheLocalJob( $params );

		$fromSiteEditor = SiteEditorEntityTypes::SCREEN === Obj::prop( 'screen', $params );
		$screen         = (string) Obj::propOr( '', 'screen', $params );

		$dataOfTranslationCreatedInNativeEditorViaConnection = $this->manualJobs->maybeGetDataIfTranslationCreatedInNativeEditorViaConnection( $params );
		if ( $dataOfTranslationCreatedInNativeEditorViaConnection
		     && ! $isLocalJobInProgress
		     && $this->usesNativeEditor( $dataOfTranslationCreatedInNativeEditorViaConnection, $screen ) ) {
			return $this->displayWPNative( $dataOfTranslationCreatedInNativeEditorViaConnection, $fromSiteEditor, $screen );
		}

		$dataForNewTranslationInNativeEditor = $this->manualJobs->maybeGetDataForNewTranslationInNativeEditor( $params );
		if ( $dataForNewTranslationInNativeEditor
		     && ! $isLocalJobInProgress
		     && $this->usesNativeEditor( $dataForNewTranslationInNativeEditor, $screen ) ) {
			return $this->displayWPNativeForNewTranslation( $dataForNewTranslationInNativeEditor, $fromSiteEditor, $this->renderedAt( $params ), $screen );
		}

		$response = Either::of( $params )
		                  ->chain( function ( $params ) {
			                  $jobObject = $this->manualJobs->createOrReuse( $params );
			                  if ( $jobObject ) {
				                  return Either::of( $jobObject );
			                  }

			                  return Either::left( self::noEditor( self::reasonNoJob( $params ) ) );
		                  } )
		                  ->chain( function ( $jobObject ) {
			                  return $jobObject->user_can_translate( User::getCurrent() )
				                  ? Either::of( $jobObject )
				                  : Either::left( self::noEditor( self::REASON_NOT_ALLOWED ) );
		                  } )
		                  ->map( $maybeUpdateTranslationServiceColumn )
		                  ->map( $this->maybeTakeOwnershipOnOpen() )
		                  ->map( Logic::ifElse( $shouldOpenCTE, $this->displayCTE(), $this->tryToDisplayATE( $params ) ) )
		                  ->coalesce( Fns::identity(), Fns::identity() )
		                  ->get();

		if ( $this->ateOpenFailed ) {
			if ( $dataOfTranslationCreatedInNativeEditorViaConnection
			     && $this->usesNativeEditor( $dataOfTranslationCreatedInNativeEditorViaConnection, $screen ) ) {
				return $this->displayWPNative( $dataOfTranslationCreatedInNativeEditorViaConnection, false, $screen );
			}

			if ( $dataForNewTranslationInNativeEditor
			     && $this->usesNativeEditor( $dataForNewTranslationInNativeEditor, $screen ) ) {
				return $this->displayWPNativeForNewTranslation( $dataForNewTranslationInNativeEditor, false, $this->renderedAt( $params ), $screen );
			}
		}

		return $response;
	}

	/**
	 * Whether the WordPress editor is the one to open for the element: the
	 * stored editor choice (`WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor()`),
	 * read for a type the WordPress editor is OFFERED for.
	 *
	 * wpmldev-8629 P2 M8, ruling Q-SE-6 (c): it is never offered for a
	 * navigation menu. With the WordPress Editor chosen as the translation
	 * editor (globally, for the type or for the menu), a menu's "+" used to land
	 * on `post-new.php?post_type=wp_navigation` - WordPress's title-only "Add
	 * Navigation Menu" form, which creates nothing and edits no menu (M0 SE-B4,
	 * the same on develop) - and an existing translation's pencil on the
	 * title-only `post.php`. A menu therefore takes the job tree below whatever
	 * the choice says: ATE when it is enabled and active, else the Translation
	 * Editor (`$shouldOpenCTE`); no Site Editor copy either (the creator is for
	 * templates and parts, Q-SE-2 (a)). The panel's editor choice does not
	 * offer the WordPress Editor for a menu (PostLanguagePanelQuery::editorMode()).
	 *
	 * wpmldev-11657: the type must also have a WordPress edit screen (`show_ui`);
	 * a type its plugin keeps out of the admin takes the job tree too. After the
	 * cutover (wpmldev-12248), such a type keeps the WordPress editor when an
	 * integration declares the post's own edit screen (a Contact Form 7 form).
	 *
	 * @param array  $data   Manual's native-editor data: `postType` (`wp_navigation` or `post_wp_navigation`), `originalPostId`, and `translatedPostId` for an existing translation.
	 * @param string $screen The `screen` of the open params.
	 *
	 * @return bool
	 */
	private function usesNativeEditor( array $data, $screen = '' ) {
		$postType = (string) Obj::propOr( '', 'postType', $data );
		if ( ! SiteEditorEntityTypes::offersNativeEditor( $postType ) ) {
			return false;
		}

		$translatedPostId = (int) Obj::propOr( 0, 'translatedPostId', $data );
		if (
			! \WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( $data['originalPostId'] )
			&& ! ( $translatedPostId && \WPML_TM_Post_Edit_TM_Editor_Mode::is_translated_independently( $translatedPostId ) )
		) {
			return false;
		}

		if ( SiteEditorTranslationCreator::isSiteEditorType( $postType ) ) {
			return true;
		}

		$object = '' !== $postType ? get_post_type_object( self::postTypeOf( $postType ) ) : null;
		if ( ! $object || ! isset( $object->show_ui ) || (bool) $object->show_ui ) {
			return true;
		}

		return $this->plan->asksNativeEditUrl()
			&& NativeEditUrl::isAnswered( $this->wpEditorUrl( $data ), $this->nativePostId( $data ), $screen, $this->nativeContext( $data ) );
	}

	private function wpEditorUrl( array $data, $renderedAt = 0 ) {
		if ( ! empty( $data['translatedPostId'] ) ) {
			return admin_url(
				'post.php?' . http_build_query(
					[
						'lang'      => $data['targetLanguageCode'],
						'action'    => 'edit',
						'post_type' => str_replace( 'post_', '', $data['postType'] ),
						'post'      => $data['translatedPostId'],
					]
				)
			);
		}

		$query = [
			'lang'        => $data['targetLanguageCode'],
			'post_type'   => $data['postType'],
			'trid'        => $data['trid'],
			'source_lang' => $data['sourceLanguageCode'],
		];

		if ( $renderedAt > 0 ) {
			$query[ \WPML_Post_Status_Display::RENDERED_AT_PARAM ] = $renderedAt;
		}

		return admin_url( 'post-new.php?' . http_build_query( $query ) );
	}

	private function nativeEditUrl( $url, array $data, $screen ) {
		if ( ! $this->plan->asksNativeEditUrl() ) {
			return $url;
		}

		return NativeEditUrl::answer( $url, $this->nativePostId( $data ), $screen, $this->nativeContext( $data ) );
	}

	private function nativePostId( array $data ) {
		return (int) ( empty( $data['translatedPostId'] ) ? $data['originalPostId'] : $data['translatedPostId'] );
	}

	private function nativeContext( array $data ) {
		$isNew          = empty( $data['translatedPostId'] );
		$originalPostId = (int) $data['originalPostId'];
		$elementType    = 'post_' . self::postTypeOf( (string) $data['postType'] );

		$trid = isset( $data['trid'] )
			? (int) $data['trid']
			: (int) apply_filters( 'wpml_element_trid', 0, $originalPostId, $elementType );

		$sourceLanguage = isset( $data['sourceLanguageCode'] )
			? (string) $data['sourceLanguageCode']
			: (string) apply_filters( 'wpml_element_language_code', '', [ 'element_id' => $originalPostId, 'element_type' => $elementType ] );

		return [
			'trid'           => $trid,
			'language'       => (string) $data['targetLanguageCode'],
			'sourceLanguage' => $sourceLanguage,
			'isNew'          => $isNew,
		];
	}

	private function noWpmlEditorCanOpenTheLocalJob( $params ) {
		$trid     = (int) filter_var( Obj::prop( 'trid', $params ), FILTER_SANITIZE_NUMBER_INT );
		$language = (string) filter_var( Obj::prop( 'language_code', $params ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$jobId    = (int) filter_var( Obj::propOr( 0, 'job_id', $params ), FILTER_SANITIZE_NUMBER_INT );

		$job = $trid && $language ? Jobs::getTridJob( $trid, $language ) : ( $jobId ? Jobs::get( $jobId ) : null );
		if ( ! $job ) {
			return false;
		}

		$editor = (string) Obj::prop( 'editor', $job );

		if ( \WPML_TM_Editors::WP === $editor ) {
			return true;
		}

		return in_array( $editor, [ \WPML_TM_Editors::ATE, \WPML_TM_Editors::NONE ], true )
		       && ReconnectState::isReconnecting();
	}

	private function maybeTakeOwnershipOnOpen() {
		return function ( $jobObject ) {
			$currentUserId = User::getCurrentId();
			$isOwnJob      = (int) $jobObject->get_translator_id() === $currentUserId;

			if ( ! $isOwnJob && ! User::canManageTranslations() && ! TranslationRecord::isCompleted( $jobObject ) ) {
				$jobObject->assign_to( $currentUserId );
			}

			return $jobObject;
		};
	}

	private function tryToDisplayATE( $params = null, $jobObject = null ) {
		$fn = curryN( 2, function ( $params, $jobObject ) {
			$handleNotActiveATE = Logic::ifElse(
				[ \WPML_TM_ATE_Status::class, 'is_active' ],
				Either::of(),
				pipe( $this->handleATEJobCreationError( $params, self::ATE_IS_NOT_ACTIVE ), Either::left() )
			);

			$refuseStaleDeliveredJob = function ( $jobObject ) use ( $params ) {
				if ( Obj::prop( 'preview', $params ) ) {
					return Either::of( $jobObject );
				}

				if ( ! $this->isStaleDeliveredAteJob( $jobObject ) ) {
					return Either::of( $jobObject );
				}

				$freshJob = $this->manualJobs->recreateFromCurrentContent( $params );

				if ( ! $freshJob ) {
					return Either::of( $jobObject );
				}

				JobLog::add( 'stale_delivered_job_recreated', [
					'stale_job_id'     => (int) $jobObject->get_id(),
					'stale_ate_job_id' => (int) $jobObject->get_basic_data_property( 'editor_job_id' ),
					'fresh_job_id'     => (int) $freshJob->get_id(),
				] );

				return Either::of( $freshJob );
			};

			$cloneCompletedATEJob = function ( $jobObject ) use ( $params ) {
				if ( $this->isValidATEJob( $jobObject ) && TranslationRecord::isCompleted( $jobObject ) && ! $this->isUnderAnOpenStatus( $jobObject ) && ! $this->historyRefusesClone( $jobObject ) ) {
					$sentFrom = isset( $params['preview'] ) ? Jobs::SENT_FROM_REVIEW : Jobs::SENT_MANUALLY;

					return $this->clone_jobs->cloneCompletedATEJob( $jobObject, $sentFrom )
					                        ->bichain(
						                        function ( $failedJobObject ) {
							                        $this->cloneRefused[ (int) $failedJobObject->get_id() ] = true;

							                        return Either::of( $failedJobObject );
						                        },
						                        Either::of()
					                        );
				}

				return Either::of( $jobObject );
			};

			$handleMissingATEJob = function ( $jobObject ) use ( $params ) {
				if ( $this->isValidATEJob( $jobObject ) && empty( $this->cloneRefused[ (int) $jobObject->get_id() ] ) ) {
					return Either::of( $jobObject );
				}

				if ( $this->isCreateInFlight( $jobObject ) ) {
					return Either::left( $this->handleATEJobCreationError( $params, self::ATE_JOB_COULD_NOT_BE_CREATED, $jobObject ) );
				}

				if ( TranslationRecord::isCompleted( $jobObject ) ) {
					$request = $this->requestRowFor( $params, $jobObject );
					if ( ! $request ) {
						return Either::left( $this->handleATEJobCreationError( $params, self::ATE_JOB_COULD_NOT_BE_CREATED, $jobObject ) );
					}

					return $this->createATECounterpartForExistingWPMLJob( $params, $request );
				}

				if ( $jobObject->get_basic_data_property( 'editor' ) === \WPML_TM_Editors::WPML ) {
					return $this->reconcileThenCreateATECounterpart( $params, $jobObject );
				}

				if ( ! $jobObject->get_basic_data_property( 'translated' ) && $this->previousJob( $params, $jobObject ) ) {
					$outcome = $this->reconcileMissingATEJob( $params, $jobObject );
					if ( AteCreateReconciler::OUTCOME_ADOPTED === $outcome ) {
						return $this->openedATECounterpart( $jobObject );
					}

					$unknown = in_array( $outcome, [ AteCreateReconciler::OUTCOME_NO_ANSWER, AteCreateReconciler::OUTCOME_NO_LOOKUP ], true );
					$record  = $unknown ? null : $this->recordBehindAbandonedRequest( $params, $jobObject );
					if ( $record ) {
						try {
							if ( $this->discardAbandonedRequest( $jobObject, $record ) ) {
								return $this->recloneDiscardedRecord( $params, $record );
							}
						} catch ( \Throwable $throwable ) {
							JobLog::addError( 'abandoned_request_discard_threw', [
								'request_job_id' => (int) $jobObject->get_id(),
								'record_job_id'  => (int) $record->get_id(),
								'message'        => $throwable->getMessage(),
								'origin'         => $throwable->getFile() . ':' . $throwable->getLine(),
							] );
						}
					}

					return Either::left( $this->handleATEJobCreationError( $params, self::ATE_JOB_COULD_NOT_BE_CREATED, $jobObject ) );
				}

				return $this->reconcileThenCreateATECounterpart( $params, $jobObject );
			};

			return Either::of( $jobObject )
			             ->chain( $handleNotActiveATE )
			             ->chain( $refuseStaleDeliveredJob )
			             ->chain( $cloneCompletedATEJob )
			             ->chain( $handleMissingATEJob )
			             ->map( Fns::tap( pipe( invoke( 'get_id' ), Jobs::setStatus( Fns::__, ICL_TM_IN_PROGRESS ) ) ) )
			             ->map( $this->openATE( $params ) )
			             ->coalesce( Fns::identity(), Fns::identity() )
			             ->get();
		} );

		return call_user_func_array( $fn, func_get_args() );
	}

	private function displayCTE( $jobObject = null ) {
		$fn = curryN( 1, function ( $jobObject ) {
			if ( TranslationRecord::isCompleted( $jobObject ) && ! $this->plan->opensRequestOnOpen() ) {
				return [ 'editor' => \WPML_TM_Editors::WPML, 'jobObject' => $jobObject ];
			}

			if ( TranslationRecord::isCompleted( $jobObject ) ) {
				$jobObject = $this->requestRowFor( null, $jobObject );
				if ( ! $jobObject ) {
					return self::noEditor( self::REASON_EDITOR_CANNOT_OPEN );
				}
			}

			wpml_tm_load_old_jobs_editor()->set( $jobObject->get_id(), \WPML_TM_Editors::WPML );

			return [ 'editor' => \WPML_TM_Editors::WPML, 'jobObject' => $jobObject ];
		} );

		return call_user_func_array( $fn, func_get_args() );
	}

	private function opensInSiteEditor( $postType, $fromSiteEditor ) {
		return SiteEditorTranslationCreator::isSiteEditorType( $postType )
		       || ( $fromSiteEditor && SiteEditorTranslationCreator::isPageType( $postType ) );
	}

	private function translationNotCreatedReason( $postType ) {
		if ( SiteEditorTranslationCreator::isPageType( $postType ) ) {
			return self::REASON_PAGE_TRANSLATION_NOT_CREATED;
		}

		return 'wp_template_part' === self::postTypeOf( $postType )
			? self::REASON_PART_TRANSLATION_NOT_CREATED
			: self::REASON_TRANSLATION_NOT_CREATED;
	}

	private function cannotCreateReason( $postType ) {
		if ( SiteEditorTranslationCreator::isPageType( $postType ) ) {
			return self::REASON_CANNOT_CREATE_PAGE;
		}

		return 'wp_template_part' === self::postTypeOf( $postType )
			? self::REASON_CANNOT_CREATE_PART
			: self::REASON_CANNOT_CREATE_TEMPLATE;
	}

	private function userCanCreate( $postType ) {
		$typeObject = get_post_type_object( self::postTypeOf( $postType ) );

		return $typeObject
		       && isset( $typeObject->cap->create_posts )
		       && current_user_can( $typeObject->cap->create_posts );
	}

	private static function postTypeOf( $postType ) {
		$postType = (string) $postType;

		return 0 === strpos( $postType, 'post_' ) ? substr( $postType, 5 ) : $postType;
	}

	private function displayWPNative( array $dataOfTranslationCreatedInNativeEditorViaConnection, $fromSiteEditor = false, $screen = '' ) {
		if ( $this->opensInSiteEditor( $dataOfTranslationCreatedInNativeEditorViaConnection['postType'], $fromSiteEditor ) ) {
			return [
				'editor'    => \WPML_TM_Editors::WP,
				'jobObject' => null,
				'url'       => $this->siteEditorCreator->siteEditorUrl(
					(int) $dataOfTranslationCreatedInNativeEditorViaConnection['translatedPostId'],
					$dataOfTranslationCreatedInNativeEditorViaConnection['targetLanguageCode']
				),
			];
		}

		$url = $this->nativeEditUrl(
			$this->wpEditorUrl( $dataOfTranslationCreatedInNativeEditorViaConnection ),
			$dataOfTranslationCreatedInNativeEditorViaConnection,
			$screen
		);

		return [ 'editor' => \WPML_TM_Editors::WP, 'jobObject' => null, 'url' => $url ];
	}

	private function displayWPNativeForNewTranslation( array $dataForNewTranslationInNativeEditor, $fromSiteEditor = false, $renderedAt = 0, $screen = '' ) {
		if ( $this->opensInSiteEditor( $dataForNewTranslationInNativeEditor['postType'], $fromSiteEditor ) ) {
			if ( ! $this->userCanCreate( (string) $dataForNewTranslationInNativeEditor['postType'] ) ) {
				return self::noEditor( $this->cannotCreateReason( (string) $dataForNewTranslationInNativeEditor['postType'] ) );
			}

			$translationId = $this->siteEditorCreator->findOrCreate(
				(int) $dataForNewTranslationInNativeEditor['originalPostId'],
				(int) $dataForNewTranslationInNativeEditor['trid'],
				$dataForNewTranslationInNativeEditor['targetLanguageCode'],
				$dataForNewTranslationInNativeEditor['sourceLanguageCode']
			);

			if ( ! $translationId ) {
				return self::noEditor( $this->translationNotCreatedReason( (string) $dataForNewTranslationInNativeEditor['postType'] ) );
			}

			return [
				'editor'    => \WPML_TM_Editors::WP,
				'jobObject' => null,
				'url'       => $this->siteEditorCreator->siteEditorUrl( $translationId, $dataForNewTranslationInNativeEditor['targetLanguageCode'] ),
			];
		}

		$url = $this->nativeEditUrl(
			$this->wpEditorUrl( $dataForNewTranslationInNativeEditor, $renderedAt ),
			$dataForNewTranslationInNativeEditor,
			$screen
		);

		return [ 'editor' => \WPML_TM_Editors::WP, 'jobObject' => null, 'url' => $url ];
	}

	private function renderedAt( $params ) {
		return (int) filter_var( Obj::prop( \WPML_Post_Status_Display::RENDERED_AT_PARAM, is_array( $params ) ? $params : [] ), FILTER_SANITIZE_NUMBER_INT );
	}

	private static function noEditor( $reason ) {
		return [ 'editor' => \WPML_TM_Editors::NONE, 'jobObject' => null, 'reason' => $reason ];
	}

	private static function reasonNoJob( array $params ) {
		if ( (int) Obj::propOr( 0, 'job_id', $params ) > 0 ) {
			return self::REASON_JOB_NOT_FOUND;
		}

		global $iclTranslationManagement;

		return $iclTranslationManagement instanceof \TranslationManagement
			&& $iclTranslationManagement->translation_row_write_failed( (int) Obj::propOr( 0, 'trid', $params ), (string) Obj::propOr( '', 'language_code', $params ) )
			? self::REASON_TRANSLATION_RECORD_NOT_WRITTEN
			: self::REASON_JOB_NOT_CREATED;
	}

	public static function noEditorMessage( $reason ) {
		switch ( $reason ) {
			case self::REASON_JOB_NOT_FOUND:
				/* translators: Message shown when someone opens a translation job that WPML no longer has. */
				return __( 'This translation job no longer exists. It may have been cancelled or replaced by a newer job. Find the content in the list below, or ask a translation manager to send it to translation again.', 'sitepress' );
			case self::REASON_JOB_NOT_CREATED:
				/* translators: Message shown when WPML could not start a translation job for the content someone tried to translate. */
				return __( 'WPML couldn\'t start a translation job for this content. You may not be assigned to translate this language pair. Ask a translation manager to add the language pair to your translator profile, then open the translation again.', 'sitepress' );
			case self::REASON_TRANSLATION_RECORD_NOT_WRITTEN:
				if ( \WPML\TM\Jobs\StatusWriteFailure::isTranslationsTableRepairOffered() ) {
					/* translators: Message shown when WPML could not start a translation job because the row linking the content to its translation could not be written to the database. "Repair the translations table" is the name of a tool on WPML → Support. */
					return __( 'WPML could not create the translation job: the translation record for this content could not be written. Run "Repair the translations table" in WPML → Support.', 'sitepress' );
				}
				/* translators: Message shown when WPML could not start a translation job because the row linking the content to its translation could not be written to the database, and WPML → Support offers no repair for it. "Translation job logs" is the name of a tool on WPML → Support. */
				return __( 'WPML could not create the translation job: the translation record for this content could not be written. Try again. If it fails again, send WPML support the newest entry in WPML → Support → Translation job logs.', 'sitepress' );
			case self::REASON_NOT_ALLOWED:
				/* translators: Message shown when someone opens a translation job they are not allowed to translate. */
				return __( 'You can\'t translate this job. It is assigned to another translator, or you don\'t translate its language pair. Ask a translation manager to assign the job to you.', 'sitepress' );
			case self::REASON_EDITOR_CANNOT_OPEN:
				/* translators: Message shown when the translation editor could not open a completed translation for new edits. */
				return __( 'The translation editor couldn\'t open this translation, because WPML couldn\'t start a new round of edits on it. Open the translation again. If it still doesn\'t open, contact WPML support.', 'sitepress' );
			case self::REASON_NOTHING_TO_TRANSLATE:
				/* translators: Message shown when the content had nothing to translate, so no translation editor opened. */
				return __( 'This content has nothing to translate, so WPML completed the translation without opening an editor.', 'sitepress' );
			case self::REASON_TRANSLATION_NOT_CREATED:
				/* translators: Message shown in the Site Editor when WPML could not create the copy of a template that a translation in the WordPress editor starts from. */
				return __( 'WPML couldn\'t create the translation of this template. Try again, and if it still doesn\'t work, contact WPML support.', 'wpml' );
			case self::REASON_PART_TRANSLATION_NOT_CREATED:
				/* translators: Message shown in the Site Editor when WPML could not create the copy of a template part that a translation in the WordPress editor starts from. */
				return __( 'WPML couldn\'t create the translation of this template part. Try again, and if it still doesn\'t work, contact WPML support.', 'wpml' );
			case self::REASON_PAGE_TRANSLATION_NOT_CREATED:
				/* translators: Message shown in the Site Editor when WPML could not create the copy of a page that a translation in the WordPress editor starts from. */
				return __( 'WPML couldn\'t create the translation of this page. Try again, and if it still doesn\'t work, contact WPML support.', 'wpml' );
			case self::REASON_CANNOT_CREATE_PAGE:
				/* translators: Message shown when someone whose user role may not add pages tries to create the translation of a page in the WordPress editor from the Site Editor. */
				return __( 'You can\'t create the translation of this page, because your user role isn\'t allowed to add pages. Ask an administrator to give your role that permission, or ask someone who can add pages to create the translation.', 'wpml' );
			case self::REASON_CANNOT_CREATE_TEMPLATE:
				/* translators: Message shown when someone whose user role may not add templates tries to create the translation of a template in the WordPress editor. */
				return __( 'You can\'t create the translation of this template, because your user role isn\'t allowed to add templates. Ask an administrator to give your role that permission, or ask someone who can add templates to create the translation.', 'wpml' );
			case self::REASON_CANNOT_CREATE_PART:
				/* translators: Message shown when someone whose user role may not add template parts tries to create the translation of a template part in the WordPress editor. */
				return __( 'You can\'t create the translation of this template part, because your user role isn\'t allowed to add template parts. Ask an administrator to give your role that permission, or ask someone who can add template parts to create the translation.', 'wpml' );
			default:
				return __( 'The translation cannot be opened right now.', 'sitepress' );
		}
	}

	private function maybeSetReviewStatus( $jobObject ) {
		if ( Relation::propEq( 'review_status', ReviewStatus::NEEDS_REVIEW, $jobObject->to_array() ) ) {
			Jobs::setReviewStatus( $jobObject->get_id(), SetupOption::shouldBeReviewed() ? ReviewStatus::EDITING : null );
		}
	}

	private function handleATEJobCreationError( $params = null, $code = null, $jobObject = null ) {
		$fn = curryN( 3, function ( $params, $code, $jobObject ) {
			$this->ateOpenFailed = true;

			if ( in_array( $code, [ self::ATE_JOB_COULD_NOT_BE_CREATED, self::ATE_EDITOR_URL_COULD_NOT_BE_FETCHED ], true )
			     && ReconnectState::isReconnecting()
			     && ! ReconnectState::isNoAnswer() ) {
				return [
					'editor' => \WPML_TM_Editors::ATE,
					'url'    => add_query_arg( [ 'ateJobCreationError' => self::ATE_SITE_RECONNECTING, 'jobId' => $jobObject->get_id() ], $this->getReturnUrl( $params ) ),
				];
			}

			ATERetry::incrementCount( $jobObject->get_id() );

			$retryCount = ATERetry::getCount( $jobObject->get_id() );
			if ( $retryCount > 0 ) {
				Storage::add( Entry::retryJob( $jobObject->get_id(),
					[
						'retry_count' => ATERetry::getCount( $jobObject->get_id() )
					]
				) );
			}

			return [
				'editor' => \WPML_TM_Editors::ATE,
				'url'    => add_query_arg( [ 'ateJobCreationError' => $code, 'jobId' => $jobObject->get_id() ], $this->getReturnUrl( $params ) )
			];
		} );

		return call_user_func_array( $fn, func_get_args() );
	}

	private function isJobEditorEqualTo( $editor, $jobObject ) {
		return $jobObject->get_basic_data_property( 'editor' ) === $editor;
	}

	private function previousJob( $params, $jobObject ) {

		$jobObjectJobId = (int) $jobObject->get_id();
		$jobIdInParams  = (int) Obj::prop( 'job_id', $params );

		if ( $jobIdInParams && $jobObjectJobId !== $jobIdInParams ) {
			return Jobs::get( $jobIdInParams );
		}

		$previousJob = Jobs::getPreviousJob( $jobObjectJobId );

		return $previousJob && (int) $previousJob->job_id !== $jobObjectJobId ? $previousJob : false;
	}

	private function reconcileThenCreateATECounterpart( $params, $jobObject ) {
		$outcome = $this->reconcileMissingATEJob( $params, $jobObject );

		if ( AteCreateReconciler::OUTCOME_ADOPTED === $outcome ) {
			return $this->openedATECounterpart( $jobObject );
		}

		if ( AteCreateReconciler::OUTCOME_NO_ANSWER === $outcome ) {
			return Either::left( $this->handleATEJobCreationError( $params, self::ATE_JOB_COULD_NOT_BE_CREATED, $jobObject ) );
		}

		return $this->createATECounterpartForExistingWPMLJob( $params, $jobObject );
	}

	private function reconcileMissingATEJob( $params, $jobObject ) {
		$ateApi  = make( \WPML_TM_ATE_API::class );
		$ateJobs = make( \WPML_TM_ATE_Jobs::class );

		if ( ! $ateApi || ! $ateJobs ) {
			return AteCreateReconciler::OUTCOME_NONE;
		}

		$reconciler = new AteCreateReconciler( $ateApi, $ateJobs );
		$jobId      = (int) $jobObject->get_id();
		$result     = $reconciler->reconcile( [ $jobId ] );

		if ( AteCreateReconciler::OUTCOME_ADOPTED === $result['outcome'] ) {
			$mode = AteCreateReconciler::markedMode( $jobId );
			if ( null === $mode ) {
				$mode = \WPML_TM_Editors::ATE === $jobObject->get_basic_data_property( 'editor' )
				        || (int) $jobObject->get_basic_data_property( 'automatic' ) ? 'auto' : 'manual';
			}
			$reconciler->bind(
				$result['adopted'],
				[ $jobId => $mode ],
				isset( $params['preview'] ) ? Jobs::SENT_FROM_REVIEW : Jobs::SENT_MANUALLY
			);

			ApiCommunication::noteCreateConfirmed();
		}

		return $result['outcome'];
	}

	private function isCreateInFlight( $jobObject ) {
		$expiry = get_option( AteCreateReconciler::IN_FLIGHT_OPTION_PREFIX . (int) $jobObject->get_id(), 0 );

		return is_numeric( $expiry ) && (int) $expiry > time();
	}

	private function openedATECounterpart( $jobObject ) {
		ATERetry::reset( $jobObject->get_id() );
		$jobObject->set_basic_data_property( 'editor', \WPML_TM_Editors::ATE );

		return Either::of( $jobObject );
	}

	private function recordBehindAbandonedRequest( $params, $jobObject ) {
		if ( (int) $jobObject->get_basic_data_property( 'editor_job_id' ) > 0 ) {
			return null;
		}

		$previous = $this->previousJob( $params, $jobObject );
		if ( ! $previous
		     || (int) Obj::prop( 'rid', $previous ) !== (int) $jobObject->get_basic_data_property( 'rid' )
		     || ! TranslationRecord::isCompleted( $previous ) ) {
			return null;
		}

		$record = wpml_tm_load_job_factory()->get_translation_job_as_active_record( (int) Obj::prop( 'job_id', $previous ) );

		return $record instanceof \WPML_Element_Translation_Job && TranslationRecord::isCompleted( $record ) ? $record : null;
	}

	private function recloneDiscardedRecord( $params, $record ) {
		$sentFrom = isset( $params['preview'] ) ? Jobs::SENT_FROM_REVIEW : Jobs::SENT_MANUALLY;

		return $this->clone_jobs->cloneCompletedATEJob( $record, $sentFrom )->bichain(
			function () use ( $params, $record ) {
				JobLog::add( 'abandoned_request_reclone_refused', [ 'record_job_id' => (int) $record->get_id() ] );

				return Either::left( $this->handleATEJobCreationError( $params, self::ATE_JOB_COULD_NOT_BE_CREATED, $record ) );
			},
			function ( $request ) use ( $record ) {
				JobLog::add( 'abandoned_request_recloned', [ 'record_job_id' => (int) $record->get_id(), 'request_job_id' => (int) $request->get_id() ] );

				return Either::of( $request );
			}
		);
	}

	private function discardAbandonedRequest( $request, $record ) {
		return $this->record->discardRequest( (int) $request->get_id(), (int) $record->get_id() );
	}

	private function historyRefusesClone( $jobObject ): bool {
		$send    = $jobObject instanceof \WPML_Post_Translation_Job ? $this->historySend() : null;
		$refuses = $send && $send->carriesFor(
			(int) $jobObject->get_resultant_element_id(),
			(string) $jobObject->get_language_code(),
			(int) $jobObject->get_original_element_id(),
			\WPML\EditHistory\HistorySend::JOB_EDITOR_OPEN
		);
		if ( $refuses ) {
			$this->cloneRefused[ (int) $jobObject->get_id() ] = true;
		}

		return (bool) $refuses;
	}

	protected function historySend() {
		return \WPML\EditHistory\SendFactory::send();
	}

	private function createATECounterpartForExistingWPMLJob( $params, $jobObject ) {
		$sentFrom = isset( $params['preview'] ) ? Jobs::SENT_FROM_REVIEW : Jobs::SENT_MANUALLY;

		if ( TranslationRecord::isCompleted( $jobObject ) ) {
			$request = $this->requestRowFor( $params, $jobObject );
			if ( ! $request ) {
				return Either::left( $this->handleATEJobCreationError( $params, self::ATE_JOB_COULD_NOT_BE_CREATED, $jobObject ) );
			}
			$jobObject = $request;
		}

		$cloneResult = $this->clone_jobs->cloneWPMLJob( $jobObject->get_id(), $sentFrom, (int) $jobObject->get_basic_data_property( 'editor_job_id' ) );

		if ( CloneJobs::RESULT_ATE_JOB_CREATED === $cloneResult ) {
			return $this->openedATECounterpart( $jobObject );
		}

		if ( CloneJobs::RESULT_COMPLETED_LOCALLY === $cloneResult ) {
			ATERetry::reset( $jobObject->get_id() );

			return Either::left( self::noEditor( self::REASON_NOTHING_TO_TRANSLATE ) );
		}

		return Either::left( $this->handleATEJobCreationError( $params, self::ATE_JOB_COULD_NOT_BE_CREATED, $jobObject ) );
	}

	private function requestRowFor( $params, $record ) {
		$sentFrom = is_array( $params ) && isset( $params['preview'] ) ? Jobs::SENT_FROM_REVIEW : Jobs::SENT_MANUALLY;

		return $this->record->openRequest( $record, $sentFrom );
	}

	private function openATE( $params = null, $jobObject = null ) {
		$fn = curryN( 2, function ( $params, $jobObject ) {
			$this->maybeSetReviewStatus( $jobObject );

			$editor_url = apply_filters( 'wpml_tm_ate_jobs_editor_url', '', $jobObject->get_id(), $this->getReturnUrl( $params ) );

			if ( $editor_url ) {
				$response['editor']    = \WPML_TM_Editors::ATE;
				$response['url']       = $editor_url;
				$response['jobObject'] = $jobObject;

				return $response;
			}

			return $this->handleATEJobCreationError( $params, self::ATE_EDITOR_URL_COULD_NOT_BE_FETCHED, $jobObject );
		} );

		return call_user_func_array( $fn, func_get_args() );
	}



	private function getReturnUrl( $params ) {
		$return_url = '';

		if ( array_key_exists( 'return_url', $params ) ) {
			$return_url = filter_var( $params['return_url'], FILTER_SANITIZE_URL );

			$return_url_parts = wp_parse_url( (string) $return_url );
			$return_url_parts = is_array( $return_url_parts ) ? $return_url_parts : array();

			$admin_url       = get_admin_url();
			$admin_url_parts = wp_parse_url( $admin_url );
			$admin_url_parts = is_array( $admin_url_parts ) ? $admin_url_parts : array();

			$return_url_path = isset( $return_url_parts['path'] ) ? $return_url_parts['path'] : '';
			$admin_url_path  = isset( $admin_url_parts['path'] ) ? $admin_url_parts['path'] : '';

			if ( strpos( $return_url_path, $admin_url_path ) === 0 ) {
				$admin_url_parts['path'] = $return_url_path;
			} else {
				$admin_url_parts = $return_url_parts;
			}

			$admin_url_parts['query'] = $this->prepareQueryParameters(
				Obj::propOr( '', 'query', $return_url_parts ),
				Obj::prop( 'lang', $params )
			);

			$return_url = UrlBuilder::build( $admin_url_parts );
		}

		return $return_url;
	}

	private function prepareQueryParameters( $query, $returnLanguage ) {
		$parameters = [];
		parse_str( $query, $parameters );

		unset( $parameters['ate_original_id'] );
		unset( $parameters['back'] );
		unset( $parameters['complete'] );
		unset( $parameters[ \WPML\TM\ATE\ReturnToken::PARAM ] );

		if ( $returnLanguage ) {
			$parameters['lang'] = $returnLanguage;
		}

		return http_build_query( $parameters );
	}

	private function isValidATEJob( \WPML_Element_Translation_Job $jobObject ) {
		return $this->isJobEditorEqualTo( \WPML_TM_Editors::ATE, $jobObject ) &&
		       (int) $jobObject->get_basic_data_property( 'editor_job_id' ) > 0;
	}

	private function isUnderAnOpenStatus( \WPML_Element_Translation_Job $jobObject ) {
		return in_array( (int) $jobObject->get_status_value(), [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ], true );
	}

	private function isStaleDeliveredAteJob( \WPML_Element_Translation_Job $jobObject ) {
		if ( ! $this->isValidATEJob( $jobObject ) ) {
			return false;
		}

		if ( 1 !== (int) $jobObject->get_basic_data_property( 'translated' ) ) {
			return false;
		}

		if ( ICL_TM_IN_PROGRESS !== (int) $jobObject->get_status_value() ) {
			return false;
		}

		$ateApi = make( ATE::class );

		if ( ! $ateApi ) {
			return false;
		}

		$record = $ateApi->checkJobStatus( (int) $jobObject->get_id() );

		return \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERED === (int) Obj::propOr( -1, 'status_id', $record ?: [] );
	}
}
