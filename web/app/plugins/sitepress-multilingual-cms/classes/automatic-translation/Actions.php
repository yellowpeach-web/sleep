<?php

namespace WPML\TM\AutomaticTranslation\Actions;

use WPML\Element\API\Languages;
use WPML\FP\Cast;
use WPML\FP\Debug;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Str;
use WPML\LIB\WP\Hooks;
use WPML\LIB\WP\Post;
use WPML\Settings\PostType\Automatic;
use WPML\TM\ATE\TranslateEverything\Cutoff;
use WPML\TranslationPolicy\Policy\Policy;
use WPML\TM\API\Job\Map;
use function WPML\Container\make;
use function WPML\FP\invoke;
use WPML\LIB\WP\User;
use WPML\Setup\Option;
use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\Infrastructure\WordPress\Component\LanguagePanel\Rest\SaveFlags;
use WPML\Infrastructure\WordPress\Component\StringPackage\Application\Query\PackageDefinitionQuery;

use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\ReleaseLedger;
use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\ReleaseSummary;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslateEverythingScope;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Cancel\CancelSource;
use WPML\TM\ATE\Release\InFlightChargedJobs;
use WPML\TM\ATE\Release\ReleaseNotices;
use WPML\TM\Jobs\JobLog;
use WPML\TM\Jobs\TranslationRecord;
use WPML\Translation\PreviousStateServiceFactory;
use function WPML\FP\pipe;
use function WPML\FP\spreadArgs;

class Actions implements \IWPML_Action {

	private $duplicatesByOriginal = [];

	const PRIORITY_AFTER_PB_PROCESS = 100;

	const EDITOR_SAVE_BEFORE_RESPONSE = 'wpml_editor_save_before_response';

	const META_BOX_SAVE_FALLBACK = 'wpml_tea_meta_box_save_fallback';

	private $queuedDispatches = [];

	private $seenSaves = [];

	private $dispatchedBeforeResponse = [];

	private $queuedSendLog = [];

	private $appliedFirstJobIds = [];

	const SINCE_DATE_WITHIN        = 'within';
	const SINCE_DATE_BEFORE_CUTOFF = 'before-cutoff';
	const SINCE_DATE_SKIP_SENTINEL = 'skip-sentinel (type never offered)';

	const OPTION_SINCE_DATES_REPAIRED = 'wpml_tea_since_dates_repaired';

	private $translationElementFactory;

	private $packageDefinitionQuery;

	private const MAX_AUTOMATIC_SENDS_AFTER_GIVE_UP = 2;

	public function __construct(
		\WPML_Translation_Element_Factory $translationElementFactory,
		$packageDefinitionQuery = null
	) {
		$this->translationElementFactory = $translationElementFactory;
		$this->packageDefinitionQuery    = $packageDefinitionQuery ?: new PackageDefinitionQuery();
	}

	public function add_hooks() {
		Hooks::onAction( 'wpml_after_save_post', 100 )
		     ->then( spreadArgs( [ $this, 'onAfterSavePost' ] ) );
		Hooks::onAction( self::EDITOR_SAVE_BEFORE_RESPONSE, self::PRIORITY_AFTER_PB_PROCESS )
		     ->then( spreadArgs( [ $this, 'runQueuedBeforeResponse' ] ) );
		Hooks::onAction( self::META_BOX_SAVE_FALLBACK )
		     ->then( spreadArgs( [ $this, 'runMetaBoxSaveFallback' ] ) );
		Hooks::onAction( 'wpml_st_package_string_registered' )
			->then( spreadArgs( [ $this, 'sendPackageToTranslation' ] ) );
		add_action( 'post_updated', [ $this, 'rememberSaveSource' ], 10, 3 );

		\WPML\Request\Adapter\Ajax::register(
			DeferredDispatch::AJAX_ACTION,
			\WPML\Request\Policy\Policy::machine(
				function () {
					return DeferredDispatch::hasRecord( DeferredDispatch::requestToken() );
				},
				'Translate Everything deferred on-save dispatch: the site\'s own loopback, carrying the single-use random token of a record the save request stored (DeferredDispatch::flush)'
			),
			[ DeferredDispatch::class, 'handleRequest' ]
		);

		add_action( SaveOverFinished::ACTION_DISPATCH, [ $this, 'dispatchAfterApplyFirst' ], 10, 4 );
	}

	public static function isBlockEditorMetaBoxSave() {
		// re-review): the TM and Blog-licence readers ask it for the leg of a
		return \WPML\Posts\MetaBoxLoaderLeg::is_request();
	}

	public function sendToTranslation( $postId, $onComplete = null ) {
		$execOnComplete = function () use ( $postId, $onComplete ) {
			if ( is_callable( $onComplete ) ) {
				$onComplete( $postId );
			}
		};

		$isMetaBoxSave = self::isBlockEditorMetaBoxSave();

		if ( empty( $_POST['icl_minor_edit'] ) ) {
			if ( SaveFlags::isMinorEdit() || \WPML\Posts\MetaBoxLoaderLeg::is_minor_edit_leg( $postId ) ) {
				// helper the TM and Blog-licence readers ask.
				$execOnComplete();

				return;
			}
		} else {
			$execOnComplete();

			return;
		}

		$pending = $isMetaBoxSave ? PendingMetaBoxSave::consume( $postId ) : null;

		if ( $isMetaBoxSave && ! $pending && ! \WPML_Post_Translation::did_language_assignment_change( (int) $postId ) ) {
			$this->logSkippedMetaBoxLeg( $postId );
			$execOnComplete();

			return;
		}

		$postElement = $this->translationElementFactory->create_post( $postId );
		if ( ! $postElement->is_translatable() || ! Automatic::isAutomatic( $postElement->get_type() ) ) {
			$execOnComplete();

			return;
		}

		if ( ! $isMetaBoxSave && SaveFlags::isRestSave() && SaveFlags::metaBoxSaveFollows() ) {
			$this->deferToMetaBoxSave( $postId );
			$execOnComplete();

			return;
		}

		if ( $pending ) {
			$this->queuedSendLog[ (int) $postId ] = [
				'auto_translate_sent_by_meta_box_save',
				[
					'post_id'             => $postId,
					'deferred_by_user_id' => $pending['user'],
					'deferred_at'         => $pending['at'],
				],
			];
		}

		$this->queuedDispatches[ (int) $postId ] = function () use ( $postId, $execOnComplete ) {
			     JobLog::maybeInitRequest();
			     JobLog::createNewGroup(
				     JobLog::GROUP_ID_TRANSLATE_EVERYTHING,
				     'Auto-translate on save (TEA refill / per-post)',
				     [ 'post_id' => $postId ]
			     );
			     JobLog::addExtraLogData( 'trigger', current_action() ?: 'shutdown' );
			     JobLog::addExtraLogData( 'post_id', $postId );

			     if ( isset( $this->queuedSendLog[ (int) $postId ] ) ) {
				     list( $logId, $logData ) = $this->queuedSendLog[ (int) $postId ];
				     unset( $this->queuedSendLog[ (int) $postId ] );
				     JobLog::add( $logId, $logData );
			     }

			     try {
				     $this->maybeRepairEmptySinceDates();

				     $postElement           = $this->translationElementFactory->create_post( $postId );
				     if ( ! is_object( $postElement->get_wp_object() ) ) {
					     JobLog::add( 'auto_translate_skipped_post_deleted', [ 'post_id' => $postId ] );
					     $execOnComplete();

					     return;
				     }
				     $postStatus            = $postElement->get_wp_object()->post_status;
				     $sourceLang            = $postElement->get_source_language_code();
				     $isOriginal            = $sourceLang === null;
				     $hasAnchorRow          = $this->hasAnchorRow( $postElement );
				     $statusSends           = self::isStatusTranslateEverythingSends( $postStatus );
				     $isDisplayAsTranslated = $postElement->is_display_as_translated();
				     $sinceDateResolution   = $this->resolvePostSinceDate( $postElement );
				     $withinSinceDate       = self::SINCE_DATE_WITHIN === $sinceDateResolution;
				     $excluded              = self::isExcludedFromAutomaticTranslation( $postId );

				     if ( $isOriginal && ! $hasAnchorRow ) {
					     $this->logOrphanedElementSkipped( $postElement );
				     } elseif (
					     $statusSends
					     && $isOriginal
					     && ( ! $isDisplayAsTranslated || $this->hasCompletedAutomaticTranslation( $postElement ) )
					     && $withinSinceDate
					     && ! $excluded
				     ) {
					     $secondaryLanguageCodes = AutomaticTranslationCapabilities::getEligibleLanguageCodes(
						     $postElement->get_language_code()
					     );

					     JobLog::add( 'auto_translate_eligible', [
						     'post_status'      => $postStatus,
						     'target_languages' => $secondaryLanguageCodes,
						     'lang_count'       => Lst::length( $secondaryLanguageCodes ),
					     ] );

					     if ( ! Lst::length( $secondaryLanguageCodes ) ) {
						     JobLog::addError( 'auto_translate_no_eligible_languages', [ 'post_id' => $postId ] );
						     do_action( 'wpml_update_failed_jobs_notice', $postElement );
					     }

					     $secondaryLanguageCodes = $this->rejectDuplicates(
						     $postElement,
						     $secondaryLanguageCodes
					     );
					     $secondaryLanguageCodes = $this->rejectLanguagesWithUnchangedInFlightJob(
						     $postElement,
						     $secondaryLanguageCodes
					     );

					     $isDeferred = DeferredDispatch::open( $postId );
					     try {
						     $saveSource = SaveSource::consume( $postId );
						     $secondaryLanguageCodes = $this->applyFinishedFirst( $postElement, $secondaryLanguageCodes, $saveSource, $isDeferred );
						     $this->cancelExistingTranslationJobs( $postElement, $secondaryLanguageCodes, $saveSource );
						     $this->createTranslationJobs( $postElement, $secondaryLanguageCodes );
					     } finally {
						     DeferredDispatch::close();
					     }
					     JobLog::add( 'auto_translate_jobs_dispatched', [
						     'languages' => $secondaryLanguageCodes,
					     ] );
				     } else {
					     JobLog::add( 'auto_translate_skipped_not_eligible', [
						     'post_status'              => $postStatus,
						     'is_original'              => $isOriginal,
						     'source_lang_code'         => $sourceLang,
						     'is_display_as_translated' => $isDisplayAsTranslated,
						     'within_since_date'        => $withinSinceDate,
						     'since_date_resolution'    => $sinceDateResolution,
						     'excluded'                 => (bool) $excluded,
					     ] );
				     }

				     $execOnComplete();
			     } finally {
				     JobLog::removeExtraLogData( 'trigger' );
				     JobLog::removeExtraLogData( 'post_id' );
				     JobLog::finishCurrentGroup();
			     }
		     };
		Hooks::onAction( 'shutdown', self::PRIORITY_AFTER_PB_PROCESS )
		     ->then( function () use ( $postId ) {
			     $this->runQueuedNow( $postId );
		     } );
	}

	private function logSkippedMetaBoxLeg( $postId ) {
		JobLog::maybeInitRequest();
		JobLog::createNewGroup(
			JobLog::GROUP_ID_TRANSLATE_EVERYTHING,
			'Auto-translate on save skipped (block editor meta-box leg)',
			[ 'post_id' => $postId ]
		);
		JobLog::add( 'auto_translate_skipped_meta_box_leg', [ 'post_id' => $postId ] );
		JobLog::finishCurrentGroup();
	}

	private function deferToMetaBoxSave( $postId ) {
		$userId = (int) User::getCurrentId();
		PendingMetaBoxSave::defer( (int) $postId, $userId );

		JobLog::maybeInitRequest();
		JobLog::createNewGroup(
			JobLog::GROUP_ID_TRANSLATE_EVERYTHING,
			'Auto-translate on save deferred to the meta-box request',
			[ 'post_id' => $postId ]
		);
		JobLog::add( 'auto_translate_deferred_to_meta_box_save', [
			'post_id'             => $postId,
			'user_id'             => $userId,
			'fallback_in_seconds' => PendingMetaBoxSave::FALLBACK_DELAY,
		] );
		JobLog::finishCurrentGroup();
	}

	public function runMetaBoxSaveFallback( $postId ) {
		$postId = (int) $postId;
		$marker = PendingMetaBoxSave::consumeForFallback( $postId );
		if ( ! $marker ) {
			return;
		}

		$ownsLogRequest = JobLog::maybeInitBackgroundRequest( 'wpml-tea-meta-box-save-fallback' );

		$userId   = $this->fallbackUserId( $marker['user'], $postId );
		$previous = (int) User::getCurrentId();

		wp_set_current_user( $userId );
		try {
			$this->queuedSendLog[ $postId ] = [
				'auto_translate_sent_by_fallback',
				[
					'post_id'             => $postId,
					'user_id'             => $userId,
					'deferred_by_user_id' => $marker['user'],
					'deferred_at'         => $marker['at'],
				],
			];
			$this->sendToTranslation( $postId );
			$this->runQueuedNow( $postId );
		} finally {
			unset( $this->queuedSendLog[ $postId ] );
			wp_set_current_user( $previous );
			if ( $ownsLogRequest ) {
				JobLog::flushBackgroundRequest();
			}
		}
	}

	private function fallbackUserId( $userId, $postId ) {
		$userId = (int) $userId;
		if ( $userId <= 0 ) {
			return 0;
		}

		$user = get_userdata( $userId );
		if ( ! $user || ! user_can( $user, 'edit_post', (int) $postId ) ) {
			return 0;
		}

		return $userId;
	}

	public function onAfterSavePost( $postId ) {
		$postId = (int) $postId;

		if ( isset( $this->dispatchedBeforeResponse[ $postId ] ) ) {
			unset( $this->dispatchedBeforeResponse[ $postId ], $this->seenSaves[ $postId ] );
		}

		if ( isset( $this->seenSaves[ $postId ] ) ) {
			return;
		}

		$this->seenSaves[ $postId ] = true;
		$this->sendToTranslation( $postId );
	}

	public function rememberSaveSource( $postId, $after, $before ) {
		if ( Option::shouldTranslateEverything() ) {
			SaveSource::remember( $postId, $after, $before );
		}
	}

	public function runQueuedBeforeResponse( $postId ) {
		$postId = (int) $postId;

		if ( $this->runQueuedNow( $postId ) ) {
			$this->dispatchedBeforeResponse[ $postId ] = true;
		}
	}

	public function runQueuedNow( $postId ) {
		$postId = (int) $postId;

		if ( ! isset( $this->queuedDispatches[ $postId ] ) ) {
			return false;
		}

		$dispatch = $this->queuedDispatches[ $postId ];
		$dispatch();
		unset( $this->queuedDispatches[ $postId ] );

		return true;
	}

	public function passesTranslateEverythingPostGates( $postId ) {
		$postElement = $this->translationElementFactory->create_post( (int) $postId );
		if ( ! $postElement || ! $postElement->is_translatable() || ! Automatic::isAutomatic( $postElement->get_type() ) ) {
			return false;
		}

		$post = $postElement->get_wp_object();

		return self::isStatusTranslateEverythingSends( $post ? (string) $post->post_status : '' )
			&& ! self::isExcludedFromAutomaticTranslation( (int) $postId );
	}

	public static function isStatusTranslateEverythingSends( $postStatus ) {
		return in_array( $postStatus, make( PublishedPostStatuses::class )->get(), true )
			|| ( 'draft' === $postStatus && (bool) \WPML\Setup\Option::getTranslateEverythingDrafts() );
	}

	public static function isExcludedFromAutomaticTranslation( $postId ) {
		return (bool) apply_filters( 'wpml_exclude_post_from_auto_translate', false, $postId );
	}

	public function sendPackageToTranslation( $package ) {
		static $updatedPackages = [];

		if ( ! $package || ! Obj::prop( 'ID', $package ) ) {
			return;
		}

		if ( isset( $updatedPackages[ $package->ID ] ) ) {
			return;
		}

		$updatedPackages[ $package->ID ] = true;

    $shouldTranslate = $this->packageDefinitionQuery->isPackageOnTheList( $package->kind_slug );

		$afterFilter = apply_filters( 'wpml_auto_translate_string_package', $shouldTranslate, (array) $package );

		if ( $afterFilter ) {
			Hooks::onAction( 'shutdown' )
				->then( $this->getPackageHandler( $package ) );
			return;
		}

		JobLog::maybeInitRequest();
		JobLog::createNewGroup(
			JobLog::GROUP_ID_TRANSLATE_EVERYTHING,
			'String package auto-translate skipped',
			[ 'package_id' => $package->ID, 'kind_slug' => $package->kind_slug ?? null ]
		);
		JobLog::add( 'auto_translate_package_skipped_by_decision', [
			'on_list'      => (bool) $shouldTranslate,
			'after_filter' => (bool) $afterFilter,
		] );
		JobLog::finishCurrentGroup();
	}

	private function getPackageHandler( \WPML_Package $package ) {
		return function() use ( $package ) {
			JobLog::maybeInitRequest();
			JobLog::createNewGroup(
				JobLog::GROUP_ID_TRANSLATE_EVERYTHING,
				'Auto-translate on save (string package)',
				[
					'package_id' => JobLog::safeProp( $package, 'ID' ),
					'kind_slug'  => JobLog::safeProp( $package, 'kind_slug' ),
				]
			);
			JobLog::addExtraLogData( 'trigger', current_action() ?: 'shutdown' );
			JobLog::addExtraLogData( 'package_id', JobLog::safeProp( $package, 'ID' ) );

			try {
				if ( apply_filters( 'wpml_string_package_rides_owner', false, $package->ID ) ) {
					JobLog::add( 'package_rides_owner', [
						'owner' => apply_filters( 'wpml_string_package_owner', null, $package->ID ),
					] );
					return;
				}

				$packageElement  = $this->translationElementFactory->create_package( $package->ID, $package->kind_slug );
				$sourceLang      = JobLog::safeCall( $packageElement, 'get_source_language_code' );
				$withinSinceDate = $this->isPackageWithinSinceDate( $package );

				if ( $sourceLang === null && ! $this->hasAnchorRow( $packageElement ) ) {
					$this->logOrphanedElementSkipped( $packageElement );
				} elseif ( $sourceLang === null && $withinSinceDate ) {
					$secondaryLanguageCodes = AutomaticTranslationCapabilities::getEligibleLanguageCodes(
						$packageElement->get_language_code()
					);

					JobLog::add( 'auto_translate_package_eligible', [
						'target_languages' => $secondaryLanguageCodes,
						'lang_count'       => Lst::length( $secondaryLanguageCodes ),
					] );

					if ( ! Lst::length( $secondaryLanguageCodes ) ) {
						JobLog::addError(
							'auto_translate_no_eligible_languages',
							[
								'package_id' => JobLog::safeProp( $package, 'ID' ),
								'kind_slug'  => JobLog::safeProp( $package, 'kind_slug' ),
							]
						);
						do_action( 'wpml_update_failed_jobs_notice', $packageElement );

						return;
					}

					$this->cancelExistingTranslationJobs( $packageElement, $secondaryLanguageCodes, CancelSource::SAVE_CONTENT );
					$this->createTranslationJobs( $packageElement, $secondaryLanguageCodes );
					JobLog::add( 'auto_translate_package_jobs_dispatched', [
						'languages' => $secondaryLanguageCodes,
					] );
				} else {
					JobLog::add( 'auto_translate_package_skipped_not_original', [
						'source_lang_code'  => $sourceLang,
						'within_since_date' => $withinSinceDate,
					] );
				}
			} finally {
				JobLog::removeExtraLogData( 'trigger' );
				JobLog::removeExtraLogData( 'package_id' );
				JobLog::finishCurrentGroup();
			}
		};
	}

	private function rejectLanguagesWithUnchangedInFlightJob( \WPML_Translation_Element $postElement, array $languages ) {
		if ( ! $languages ) {
			return $languages;
		}

		$inFlight = $this->getUnchangedInFlightJobs(
			(int) $postElement->get_element_id(),
			$postElement->get_wpml_element_type()
		);

		if ( ! $inFlight ) {
			return $languages;
		}

		$kept = [];
		foreach ( $languages as $language ) {
			if ( isset( $inFlight[ $language ] ) ) {
				$row = $inFlight[ $language ];
				$log = [
					'target_lang' => $language,
					'rid'         => $row['rid'],
					'job_id'      => $row['job_id'],
					'status'      => $row['status'],
				];

				if ( ICL_TM_ATE_UNSOLVABLE === $row['status'] ) {
					if ( $row['sends_since_delivery'] < self::MAX_AUTOMATIC_SENDS_AFTER_GIVE_UP ) {
						$kept[] = $language;
						continue;
					}
					$log['sends_since_delivery'] = $row['sends_since_delivery'];
					JobLog::add( 'auto_translate_skipped_unchanged_unsolvable', $log );
					continue;
				}

				JobLog::add( 'auto_translate_skipped_unchanged_in_flight', $log );

				continue;
			}

			$kept[] = $language;
		}

		return $kept;
	}

	private function getUnchangedInFlightJobs( $elementId, $elementType ) {
		global $wpdb, $sitepress;

		$trid = $sitepress->get_element_trid( $elementId, $elementType );

		if ( ! $trid ) {
			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.language_code AS language_code,
				        s.rid           AS rid,
				        s.status        AS status,
				        j.job_id        AS job_id,
				        ( SELECT COUNT(*)
				            FROM {$wpdb->prefix}icl_translate_job j3
				           WHERE j3.rid = s.rid
				             AND j3.job_id > COALESCE( ( SELECT MAX( j4.job_id )
				                                           FROM {$wpdb->prefix}icl_translate_job j4
				                                          WHERE j4.rid = s.rid
				                                            AND j4.translated = 1 ), 0 ) ) AS sends_since_delivery
				   FROM {$wpdb->prefix}icl_translations t
				   INNER JOIN {$wpdb->prefix}icl_translation_status s
				           ON s.translation_id = t.translation_id
				   INNER JOIN {$wpdb->prefix}icl_translate_job j
				           ON j.rid = s.rid
				          AND j.job_id = ( SELECT MAX( j2.job_id )
				                             FROM {$wpdb->prefix}icl_translate_job j2
				                            WHERE j2.rid = s.rid )
				  WHERE t.trid = %d
				    AND t.source_language_code IS NOT NULL
				    AND s.needs_update = 0
				    AND j.translated = 0
				    AND s.status IN ( %d, %d, %d, %d )",
				(int) $trid,
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS,
				ICL_TM_ATE_NEEDS_RETRY,
				ICL_TM_ATE_UNSOLVABLE
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$inFlight = [];
		foreach ( $rows as $row ) {
			if ( empty( $row['language_code'] ) ) {
				continue;
			}

			$inFlight[ $row['language_code'] ] = [
				'rid'                  => (int) $row['rid'],
				'job_id'               => (int) $row['job_id'],
				'status'               => (int) $row['status'],
				'sends_since_delivery' => (int) $row['sends_since_delivery'],
			];
		}

		return $inFlight;
	}

	private function rejectDuplicates( \WPML_Translation_Element $element, $languages ) {
		$languages = wpml_collect( $languages )->values()->all();

		if ( ! $languages || ! $element instanceof \WPML_Post_Element ) {
			return $languages;
		}

		$duplicates = Policy::translateEverythingGate()->copiesOf(
			(int) $element->get_element_id(),
			function () use ( $element ) {
				return $this->getDuplicatesOf( $element );
			}
		);

		if ( ! $duplicates ) {
			return $languages;
		}

		$kept = [];
		foreach ( $languages as $language ) {
			if ( isset( $duplicates[ $language ] ) ) {
				JobLog::add( 'auto_translate_skipped_duplicate', [
					'target_lang'  => $language,
					'duplicate_of' => $duplicates[ $language ],
				] );

				continue;
			}

			$kept[] = $language;
		}

		return $kept;
	}

	private function getDuplicatesOf( \WPML_Post_Element $original ) {
		global $sitepress;

		$postId = (int) $original->get_element_id();

		if ( ! array_key_exists( $postId, $this->duplicatesByOriginal ) ) {
			$this->duplicatesByOriginal[ $postId ] = Fns::map(
				Cast::toInt(),
				(array) $sitepress->get_duplicates( $postId )
			);
		}

		return $this->duplicatesByOriginal[ $postId ];
	}

	private function applyFinishedFirst( \WPML_Translation_Element $postElement, array $languages, $source, $isDeferred ) {
		$candidates = SaveOverFinished::candidates( $postElement, $languages );

		if ( ! $candidates ) {
			return $languages;
		}

		if ( ! $isDeferred ) {
			foreach ( SaveOverFinished::applyInline( $candidates ) as $language => $outcome ) {
				if ( \WPML\TM\ATE\Handshake\ApplyFinished::APPLIED === $outcome && isset( $candidates[ $language ] ) ) {
					$this->appliedFirstJobIds[ (int) $candidates[ $language ]['job_id'] ] = true;
				}
			}

			return $languages;
		}

		$deferred = SaveOverFinished::completedOnly( $candidates );

		foreach ( $deferred as $language => $item ) {
			DeferredDispatch::deferApplyFirst( $language, $item['job_id'], $item['ate_job_id'], $source );
		}

		return array_values( array_diff( $languages, array_keys( $deferred ) ) );
	}

	public function dispatchAfterApplyFirst( $postId, $languages, $source = '', $appliedJobIds = [] ) {
		$languages = array_values( array_map( 'strval', (array) $languages ) );

		if ( ! $languages ) {
			return;
		}

		foreach ( (array) $appliedJobIds as $jobId ) {
			$this->appliedFirstJobIds[ (int) $jobId ] = true;
		}

		JobLog::runInGroup(
			JobLog::GROUP_ID_TRANSLATE_EVERYTHING,
			'Auto-translate on save (after applying finished translations)',
			function () use ( $postId, $languages, $source ) {
				$postElement = $this->translationElementFactory->create_post( (int) $postId );

				if ( ! $postElement->is_translatable() || ! Automatic::isAutomatic( $postElement->get_type() ) ) {
					JobLog::add( 'auto_translate_skipped_not_eligible', [ 'post_id' => (int) $postId, 'after_apply_first' => true ] );

					return;
				}

				$this->cancelExistingTranslationJobs( $postElement, $languages, (string) $source );
				$this->createTranslationJobs( $postElement, $languages );

				JobLog::add( 'auto_translate_jobs_dispatched', [
					'languages'         => $languages,
					'after_apply_first' => true,
				] );
			},
			[ 'post_id' => (int) $postId ]
		);
	}

	private function cancelExistingTranslationJobs( \WPML_Translation_Element $translationElement, $languages, $source = '' ) {
		$releaseMark = ReleaseLedger::instance()->mark();

		$getJobEntity = function ( $jobId ) use ( $translationElement ) {
			return wpml_tm_get_jobs_repository()->get_job( Map::ridFromJobId( $jobId ), $translationElement->get_element_type() );
		};

		$cancelJob = function ( $job ) use ( $getJobEntity, $translationElement, $source ) {
			$jobId = Obj::prop( 'job_id', $job );

			Jobs::clearReviewStatus( $jobId );

			if ( ! self::isDeliveredJob( $job ) && ! TranslationRecord::isCompleted( $job ) ) {
				$translationId = (int) Obj::prop( 'translation_id', $job );
				$previousState = $translationId ? PreviousStateServiceFactory::create()->get( $translationId ) : null;
				if ( $previousState ) {
					PreviousStateServiceFactory::create()->revertToPreviousState( $translationId );
					Jobs::setNeedsUpdate( $jobId );
				} else {
					Jobs::setNotTranslatedStatus( $jobId );
					Jobs::clearTranslated( $jobId );
				}
			}

			if ( isset( $this->appliedFirstJobIds[ (int) $jobId ] ) ) {
				JobLog::add( 'auto_translate_cancel_skipped_applied_first', [ 'wpml_job_id' => (int) $jobId ] );

				return;
			}

			$jobEntity = $getJobEntity( $jobId );

			if ( null === $jobEntity ) {
				JobLog::addError( 'supersede_job_entity_unresolved', [
					'wpml_job_id'  => (int) $jobId,
					'element_type' => $translationElement->get_element_type(),
				] );

				return;
			}

			do_action( 'wpml_tm_job_cancelled', $jobEntity, \WPML_TM_ATE_API::CANCEL_REASON_SUPERSEDED, $source );
		};

		$jobsToCancel = wpml_collect( $languages )
			->map( Jobs::getElementJob( $translationElement->get_element_id(), $translationElement->get_wpml_element_type() ) )
			->filter()
			->reject( self::isCompleteAndUpToDateJob() );

		$isDeferredPostSave = $translationElement instanceof \WPML_Post_Element && DeferredDispatch::isCollecting();
		$replacedChargedJobs = $isDeferredPostSave
			? InFlightChargedJobs::countChargedJobRows( $jobsToCancel->map( Obj::prop( 'job_id' ) )->toArray() )
			: 0;

		$jobsToCancel->map( $cancelJob );

		if ( $translationElement instanceof \WPML_Post_Element ) {
			ReleaseNotices::queueUpdateNotice(
				(int) $translationElement->get_element_id(),
				$isDeferredPostSave
					? new ReleaseSummary( 0, 0, $replacedChargedJobs )
					: ReleaseLedger::instance()->summaryFrom( $releaseMark )
			);
		}
	}

	private static function isCompleteAndUpToDateJob() {
		return function ( $job ) {
			return Cast::toInt( $job->needs_update ) !== 1 && Cast::toInt( $job->status ) === ICL_TM_COMPLETE;
		};
	}

	private static function isDeliveredJob( $job ) {
		return Cast::toInt( Obj::prop( 'status', $job ) ) === ICL_TM_COMPLETE;
	}

	private function hasCompletedAutomaticTranslation( \WPML_Translation_Element $postElement ) {
		$languages = AutomaticTranslationCapabilities::getEligibleLanguageCodes( $postElement->get_language_code() );

		foreach ( $languages as $language ) {
			$job = Jobs::getElementJob( $postElement->get_element_id(), $postElement->get_wpml_element_type(), $language );
			if ( $job && self::isDeliveredJob( $job ) && Jobs::wasCreatedByTEA( (int) $job->job_id ) ) {
				return true;
			}
		}

		return false;
	}

	protected function hasAnchorRow( \WPML_Translation_Element $element ) {
		$languageCode = $element->get_language_code();

		return is_string( $languageCode ) && '' !== $languageCode;
	}

	protected function logOrphanedGroupSkipped( array $context ) {
		JobLog::addError(
			'tea_skipped_orphaned_group',
			array_merge(
				$context,
				[ 'reason' => 'the translation group has no anchor row, so it has no source language' ]
			)
		);
	}

	private function logOrphanedElementSkipped( \WPML_Translation_Element $element ) {
		$this->logOrphanedGroupSkipped(
			[
				'element_id'   => (int) $element->get_element_id(),
				'element_type' => $element->get_wpml_element_type(),
				'trid'         => $element->get_trid(),
			]
		);
	}

	public function createTranslationJobs( \WPML_Translation_Element $translationElement, $targetLanguages ) {
		if ( ! AutomaticTranslationCapabilities::shouldTranslateEverythingFresh() ) {
			return;
		}

		if ( ! $this->hasAnchorRow( $translationElement ) ) {
			$this->logOrphanedElementSkipped( $translationElement );

			return;
		}

		$targetLanguages = $this->rejectDuplicates( $translationElement, $targetLanguages );

		$isNotCompleteAndUpToDate      = Logic::complement( self::isCompleteAndUpToDateJob() );
		$isPostElementAndUsingTmEditor = $this->isPostElementAndUsingNativeEditor( $translationElement );

		$sendToTranslation = function ( $language ) use (
			$translationElement,
			$isNotCompleteAndUpToDate,
			$isPostElementAndUsingTmEditor
		) {
			$job = Jobs::getElementJob( $translationElement->get_element_id(), $translationElement->get_wpml_element_type(), $language );

			if (
				$isPostElementAndUsingTmEditor
				&& (
					! $job
					|| (
						$isNotCompleteAndUpToDate( $job )
						&& $this->canJobBeReTranslatedAutomaticallyFor( $translationElement, $job->job_id )
					)
				)
			) {
				$this->createJob( $translationElement, $language );
			}
		};

		Fns::map( $sendToTranslation, $targetLanguages );
	}

	private function canJobBeReTranslatedAutomatically( $jobId ) {
		$wpmlTmLoadOldJobsEditor = wpml_tm_load_old_jobs_editor();
		$editorForOldJobs        = $wpmlTmLoadOldJobsEditor->get( $jobId );
		$currentJobEditor        = $wpmlTmLoadOldJobsEditor->get_current_editor( $jobId );

		return $editorForOldJobs === \WPML_TM_Editors::ATE || $currentJobEditor === \WPML_TM_Editors::WP;
	}

	private function canJobBeReTranslatedAutomaticallyFor( \WPML_Translation_Element $translationElement, $jobId ) {
		$todays = function () use ( $jobId ) {
			return $this->canJobBeReTranslatedAutomatically( $jobId );
		};

		return $translationElement->get_element_type() === 'post'
			? Policy::translateEverythingGate()->sendsOverJob( (int) $translationElement->get_element_id(), $todays )
			: $todays();
	}

	private function createJob( \WPML_Translation_Element $translationElement, $language ) {
		if ( ! $this->hasAnchorRow( $translationElement ) ) {
			return;
		}

		$batch = new \WPML_TM_Translation_Batch(
			[
				new \WPML_TM_Translation_Batch_Element(
					$translationElement->get_element_id(),
					$translationElement->get_element_type(),
					$translationElement->get_language_code(),
					[ $language => 1 ]
				),
			],
			\TranslationProxy_Batch::get_generic_batch_name( true ),
			[ $language => User::getCurrentId() ]
		);

		wpml_load_core_tm()->send_jobs( $batch, $translationElement->get_element_type(), Jobs::SENT_AUTOMATICALLY );
	}


	public function createNewTranslationJobs( $sourceLanguage, array $elements, $elementType, $createdByTranslateEverything = false ) {
		if ( ! is_string( $sourceLanguage ) || '' === $sourceLanguage ) {
			$this->logOrphanedGroupSkipped(
				[
					'element_type' => $elementType,
					'element_ids'  => array_map( 'intval', array_column( $elements, 0 ) ),
				]
			);

			return [];
		}

		$getTargetLang      = Lst::nth( 1 );
		$setTranslateAction = Obj::objOf( Fns::__, \TranslationManagement::TRANSLATE_ELEMENT_ACTION );
		$setTranslatorId    = Obj::objOf( Fns::__, User::getCurrentId() );

		$wpmlType = 'post';
		if ( $elementType === 'st-batch' ) {
			$wpmlType = 'st-batch';
		} else if ( Str::startsWith( 'package_', $elementType ) ) {
			$wpmlType = 'package';
		}

		if ( 'post' === $wpmlType ) {
			$postIds   = array_unique( array_map( 'intval', array_column( $elements, 0 ) ) );
			$ordering  = \WPML\Translation\AteSyncOrderingServiceFactory::create()
				->getOrderingPayloadArrayForPosts( $postIds );
			$positions = $ordering['positions'] ?? [];
			if ( ! empty( $positions ) ) {
				usort(
					$elements,
					function ( $a, $b ) use ( $positions ) {
						$posA = $positions[ (string) $a[0] ] ?? PHP_INT_MAX;
						$posB = $positions[ (string) $b[0] ] ?? PHP_INT_MAX;
						return $posA - $posB;
					}
				);
			}
		}

		$targetLanguages = \wpml_collect( $elements )
			->map( $getTargetLang )
			->unique()
			->mapWithKeys( $setTranslatorId )
			->toArray();

		$makeBatchElement = function ( $targetLanguages, $postId ) use ( $sourceLanguage, $wpmlType ) {
			return new \WPML_TM_Translation_Batch_Element(
				$postId,
				$wpmlType,
				$sourceLanguage,
				$targetLanguages->toArray()
			);
		};

		$batchElements = \wpml_collect( $elements )
			->groupBy( 0 )
			->map( Fns::map( $getTargetLang ) )
			->map( invoke( 'mapWithKeys' )->with( $setTranslateAction ) )
			->map( $makeBatchElement )
			->values()
			->toArray();

		$batch = new \WPML_TM_Translation_Batch(
			$batchElements,
			\TranslationProxy_Batch::get_generic_batch_name( true ),
			$targetLanguages
		);
		$batch->setTranslationMode( 'auto' );
		$batch->setCreatedByTranslateEverything( $createdByTranslateEverything );
		$batch->setReviewMode( \WPML\Setup\Option::getReviewMode() );

		wpml_load_core_tm()->send_jobs( $batch, $wpmlType, Jobs::SENT_AUTOMATICALLY );

		$getJobId = pipe(
			Fns::converge( Jobs::getElementJob(), [
				Obj::prop( 'elementId' ),
				Obj::prop( 'elementType' ),
				Obj::prop( 'lang' )
			] ),
			Obj::prop( 'job_id' ),
			Fns::unary( 'intval' )
		);

		return \wpml_collect( $elements )
			->map( Lst::zipObj( [ 'elementId', 'lang' ] ) )
			->map( Obj::addProp( 'elementType', Fns::always( $elementType === 'st-batch' ? 'st-batch_strings' : $elementType ) ) )
			->map( Obj::addProp( 'jobId', $getJobId ) )
			->toArray();
	}

	protected function resolvePostSinceDate( \WPML_Translation_Element $postElement ): string {
		$type = $postElement->get_type();
		if ( ! TranslateEverythingScope::isOffered( $type ) ) {
			return self::SINCE_DATE_SKIP_SENTINEL;
		}

		$sinceDate = Option::getTranslateEverythingPostSinceDate( $type );

		if ( Option::SINCE_DATE_SKIP_TYPE === $sinceDate ) {
			return self::SINCE_DATE_SKIP_SENTINEL;
		}

		return Cutoff::isPostWithin( $postElement->get_wp_object(), (string) $sinceDate )
			? self::SINCE_DATE_WITHIN
			: self::SINCE_DATE_BEFORE_CUTOFF;
	}

	protected function maybeRepairEmptySinceDates() {
		if ( get_option( self::OPTION_SINCE_DATES_REPAIRED ) ) {
			return;
		}

		if ( ! Option::shouldTranslateEverything() ) {
			return;
		}

		if ( ! empty( Option::getTranslateEverythingPostsSinceDates() ) ) {
			return;
		}

		$this->getOfferedTypeDefaults()->persist();
		update_option( self::OPTION_SINCE_DATES_REPAIRED, 1, false );

		JobLog::add(
			'auto_translate_since_dates_repaired',
			[
				'reason'      => 'since-dates map was wholly empty while TEA is on',
				'types_after' => array_keys( Option::getTranslateEverythingPostsSinceDates() ),
			]
		);
	}

	public function repairEmptySinceDatesIfNeeded() {
		$this->maybeRepairEmptySinceDates();
	}

	protected function getOfferedTypeDefaults() {
		return new \WPML\TM\ATE\TranslateEverything\OfferedTypeDefaults();
	}

	private function isPackageWithinSinceDate( \WPML_Package $package ): bool {
		if ( ! $package->kind ) {
			return false;
		}

		return Cutoff::isPackageWithin( (string) $package->kind_slug, $package->post_id );
	}

	private function isPostElementAndUsingNativeEditor( \WPML_Translation_Element $translationElement ): bool {
		if ( $translationElement->get_element_type() !== 'post' ) {
			return true;
		}

		return Policy::translateEverythingGate()->sendsPost(
			(int) $translationElement->get_element_id(),
			function () use ( $translationElement ) {
				return \WPML_TM_Post_Edit_TM_Editor_Mode::is_using_tm_editor( null, $translationElement->get_element_id(), false );
			}
		);
	}
}
