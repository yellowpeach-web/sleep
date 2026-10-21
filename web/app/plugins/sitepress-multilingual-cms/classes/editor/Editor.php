<?php

namespace WPML\TM\Editor;

use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Left;
use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\FP\Right;
use WPML\LIB\WP\User;
use WPML\Setup\Option as SetupOption;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Log\Entry;
use WPML\TM\ATE\Log\Storage;
use WPML\TM\API\ATE;
use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\ATE\ClonedSites\ReconnectState;
use WPML\TM\ATE\JobCreation\AteCreateReconciler;
use WPML\TM\ATE\PullDelivery\SingleJobCollect;
use WPML\TM\ATE\Receive\SingleJobDelivery;
use WPML\TM\ATE\Review\ReviewStatus;
use WPML\TM\ATE\Sync\Trigger;
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
	const REASON_NOT_ALLOWED = 'not-allowed';
	const REASON_EDITOR_CANNOT_OPEN = 'editor-cannot-open';
	const REASON_NOTHING_TO_TRANSLATE = 'nothing-to-translate';

	private $clone_jobs;

	private $manualJobs;

	private $record;

	private $singleJobCollect;

	const STALE_DELIVERY_COLLECTED     = 'collected';
	const STALE_DELIVERY_DEFERRED      = 'deferred_to_the_pull';
	const STALE_DELIVERY_NOT_COLLECTED = 'not_collected';

	private $cloneRefused = [];

	private $ateSaidDelivered = [];

	private $ateOpenFailed = false;

	public function __construct( CloneJobs $clone_jobs, Manual $manualJobs, ?TranslationRecord $record = null, ?SingleJobCollect $singleJobCollect = null ) {
		$this->clone_jobs       = $clone_jobs;
		$this->manualJobs       = $manualJobs;
		$this->record           = $record ?: new TranslationRecord();
		$this->singleJobCollect = $singleJobCollect;
	}

	public function open( $params ) {
		return ApiCommunication::duringUserSend(
			function () use ( $params ) {
				$lock = ( new OpenLock() )->acquire( is_array( $params ) ? $params : [] );

				try {
					return $this->doOpen( $params );
				} finally {
					if ( $lock ) {
						$lock->release();
					}
				}
			}
		);
	}

	private function doOpen( $params ) {
		$shouldOpenCTE = function ( $jobObject ) use ( $params ) {
			if ( ! \WPML_TM_ATE_Status::is_enabled() ) {
				return true;
			}

			$previousJob = $this->previousJob( $params, $jobObject );

			if ( ! $jobObject->get_basic_data_property( 'translated' ) && $previousJob ) {
				return wpml_tm_load_old_jobs_editor()->shouldStickToWPMLEditor( $jobObject->get_id(), $previousJob );
			}

			return wpml_tm_load_old_jobs_editor()->editorForTranslationsPreviouslyCreatedUsingCTE() === \WPML_TM_Editors::WPML &&
			       wpml_tm_load_old_jobs_editor()->get_current_editor( $jobObject->get_id() ) === \WPML_TM_Editors::WPML;
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

		$dataOfTranslationCreatedInNativeEditorViaConnection = $this->manualJobs->maybeGetDataIfTranslationCreatedInNativeEditorViaConnection( $params );
		if ( $dataOfTranslationCreatedInNativeEditorViaConnection
		     && ! $isLocalJobInProgress
		     && \WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( $dataOfTranslationCreatedInNativeEditorViaConnection['originalPostId'] ) ) {
			return $this->displayWPNative( $dataOfTranslationCreatedInNativeEditorViaConnection );
		}

		$dataForNewTranslationInNativeEditor = $this->manualJobs->maybeGetDataForNewTranslationInNativeEditor( $params );
		if ( $dataForNewTranslationInNativeEditor
		     && ! $isLocalJobInProgress
		     && \WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( $dataForNewTranslationInNativeEditor['originalPostId'] ) ) {
			return $this->displayWPNativeForNewTranslation( $dataForNewTranslationInNativeEditor );
		}

		$response = Either::of( $params )
		                  ->chain( function ( $params ) {
			                  $jobObject = $this->manualJobs->createOrReuse( $params );
			                  if ( $jobObject ) {
				                  return Either::of( $jobObject );
			                  }

			                  return Either::left( self::noEditor(
				                  (int) Obj::propOr( 0, 'job_id', $params ) > 0 ? self::REASON_JOB_NOT_FOUND : self::REASON_JOB_NOT_CREATED
			                  ) );
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
			     && \WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( $dataOfTranslationCreatedInNativeEditorViaConnection['originalPostId'] ) ) {
				return $this->displayWPNative( $dataOfTranslationCreatedInNativeEditorViaConnection );
			}

			if ( $dataForNewTranslationInNativeEditor
			     && \WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( $dataForNewTranslationInNativeEditor['originalPostId'] ) ) {
				return $this->displayWPNativeForNewTranslation( $dataForNewTranslationInNativeEditor );
			}
		}

		return $response;
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

			$cloneCompletedATEJob = function ( $jobObject ) use ( $params ) {
				if ( $this->isValidATEJob( $jobObject ) && TranslationRecord::isCompleted( $jobObject ) && ! $this->isUnderAnOpenStatus( $jobObject ) ) {
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

			$refuseStaleDeliveredJob = function ( $jobObject ) use ( $params ) {
				if ( Obj::prop( 'preview', $params ) ) {
					return Either::of( $jobObject );
				}

				if ( ! $this->isStaleDeliveredAteJob( $jobObject ) ) {
					return Either::of( $jobObject );
				}

				$collect = $this->collectStaleDelivery( (int) $jobObject->get_id() );

				if ( self::STALE_DELIVERY_COLLECTED === $collect ) {
					JobLog::add( 'stale_delivered_job_collected', [
						'job_id'     => (int) $jobObject->get_id(),
						'ate_job_id' => (int) $jobObject->get_basic_data_property( 'editor_job_id' ),
					] );

					return Either::of( $jobObject );
				}

				if ( self::STALE_DELIVERY_DEFERRED === $collect ) {
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

	private function displayWPNative( array $dataOfTranslationCreatedInNativeEditorViaConnection ) {
		$url = 'post.php?' . http_build_query(
				[
					'lang'      => $dataOfTranslationCreatedInNativeEditorViaConnection['targetLanguageCode'],
					'action'    => 'edit',
					'post_type' => str_replace( 'post_', '', $dataOfTranslationCreatedInNativeEditorViaConnection['postType'] ),
					'post'      => $dataOfTranslationCreatedInNativeEditorViaConnection['translatedPostId']
				]
			);


		return [ 'editor' => \WPML_TM_Editors::WP, 'jobObject' => null, 'url' => $url ];
	}

	private function displayWPNativeForNewTranslation( array $dataForNewTranslationInNativeEditor ) {
		$url = 'post-new.php?' . http_build_query(
				[
					'lang'        => $dataForNewTranslationInNativeEditor['targetLanguageCode'],
					'post_type'   => $dataForNewTranslationInNativeEditor['postType'],
					'trid'        => $dataForNewTranslationInNativeEditor['trid'],
					'source_lang' => $dataForNewTranslationInNativeEditor['sourceLanguageCode'],
				]
			);

		return [ 'editor' => \WPML_TM_Editors::WP, 'jobObject' => null, 'url' => $url ];
	}

	private static function noEditor( $reason ) {
		return [ 'editor' => \WPML_TM_Editors::NONE, 'jobObject' => null, 'reason' => $reason ];
	}

	public static function noEditorMessage( $reason ) {
		switch ( $reason ) {
			case self::REASON_JOB_NOT_FOUND:
				/* translators: Message shown when someone opens a translation job that WPML no longer has. */
				return __( 'This translation job no longer exists. It may have been cancelled or replaced by a newer job. Find the content in the list below, or ask a translation manager to send it to translation again.', 'sitepress' );
			case self::REASON_JOB_NOT_CREATED:
				/* translators: Message shown when WPML could not start a translation job for the content someone tried to translate. */
				return __( 'WPML couldn\'t start a translation job for this content. You may not be assigned to translate this language pair. Ask a translation manager to add the language pair to your translator profile, then open the translation again.', 'sitepress' );
			case self::REASON_NOT_ALLOWED:
				/* translators: Message shown when someone opens a translation job they are not allowed to translate. */
				return __( 'You can\'t translate this job. It is assigned to another translator, or you don\'t translate its language pair. Ask a translation manager to assign the job to you.', 'sitepress' );
			case self::REASON_EDITOR_CANNOT_OPEN:
				/* translators: Message shown when the translation editor could not open a completed translation for new edits. */
				return __( 'The translation editor couldn\'t open this translation, because WPML couldn\'t start a new round of edits on it. Open the translation again. If it still doesn\'t open, contact WPML support.', 'sitepress' );
			case self::REASON_NOTHING_TO_TRANSLATE:
				/* translators: Message shown when the content had nothing to translate, so no translation editor opened. */
				return __( 'This content has nothing to translate, so WPML completed the translation without opening an editor.', 'sitepress' );
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

			$return_url = http_build_url( $admin_url_parts );
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
		if ( ! empty( $this->ateSaidDelivered[ (int) $jobObject->get_id() ] ) ) {
			return false;
		}

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

		$record    = $ateApi->checkJobStatus( (int) $jobObject->get_id() );
		$delivered = \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERED === (int) Obj::propOr( -1, 'status_id', $record ?: [] );
		if ( $delivered ) {
			$this->ateSaidDelivered[ (int) $jobObject->get_id() ] = true;
		}

		return $delivered;
	}

	private function collectStaleDelivery( $jobId ) {
		$collect = $this->singleJobCollect();

		if ( ! $collect ) {
			return self::STALE_DELIVERY_NOT_COLLECTED;
		}

		$outcome = $collect->collect( $jobId );

		if ( SingleJobCollect::COLLECT_LOCK_BUSY === $outcome ) {
			return self::STALE_DELIVERY_DEFERRED;
		}

		if ( SingleJobDelivery::APPLIED !== $outcome ) {
			return self::STALE_DELIVERY_NOT_COLLECTED;
		}

		$job = Jobs::get( $jobId );

		return $job && ICL_TM_COMPLETE === (int) Obj::prop( 'status', $job )
			? self::STALE_DELIVERY_COLLECTED
			: self::STALE_DELIVERY_NOT_COLLECTED;
	}

	private function singleJobCollect() {
		if ( null === $this->singleJobCollect ) {
			$this->singleJobCollect = make( SingleJobCollect::class );
		}

		return $this->singleJobCollect instanceof SingleJobCollect ? $this->singleJobCollect : null;
	}
}
