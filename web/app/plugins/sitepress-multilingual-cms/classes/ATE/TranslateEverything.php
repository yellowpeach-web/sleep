<?php

namespace WPML\TM\ATE;

use WPML\Collect\Support\Collection;
use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\BatchResult;
use WPML\Element\API\Languages;
use WPML\FP\Fns;
use WPML\FP\Left;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Right;
use WPML\LanguageEditor\TranslationPause;
use WPML\Media\Option as MediaOption;
use WPML\Setup\Option;
use WPML\TM\ATE\API\ClientRestrictionState;
use WPML\TM\ATE\API\SpendCapState;
use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\TM\ATE\TranslateEverything\AbstractUntranslatedElements;
use WPML\TM\ATE\TranslateEverything\CompletedTranslationsInterface;
use WPML\TM\ATE\TranslateEverything\CreatableElementsInterface;
use WPML\TM\ATE\TranslateEverything\UntranslatedElementsInterface;
use WPML\TM\ATE\TranslateEverything\UntranslatedPackages;
use WPML\TM\ATE\TranslateEverything\UntranslatedPosts;
use WPML\TM\ATE\TranslateEverything\UntranslatedTerms;
use WPML\TM\AutomaticTranslation\Actions\Actions;
use WPML\TM\Jobs\JobLog;
use WPML\Utilities\KeyedLock;
use function WPML\Container\make;

class TranslateEverything implements CompletedTranslationsInterface {

	private $untranslated_elements = [];

	const LOCK_RELEASE_TIMEOUT = 2 * MINUTE_IN_SECONDS;

	public function __construct( UntranslatedPosts $untranslated_posts, UntranslatedPackages $untranslated_packages ) {
		$this->untranslated_elements = [
			$untranslated_packages,
			$untranslated_posts,
		];

		$this->untranslated_elements = apply_filters(
			'wpml_translate_everything_untranslated_elements_strategies',
			$this->untranslated_elements
		);

		$this->untranslated_elements = Fns::filter( function ( $strategy ) {
			return $strategy instanceof UntranslatedElementsInterface;
		}, $this->untranslated_elements );

		$this->untranslated_elements = $this->sortStrategiesByTier( $this->untranslated_elements );
	}

	public function run(
		Collection $data,
		Actions $actions
	) {
		JobLog::maybeInitRequest();
		JobLog::createNewGroup(
			JobLog::GROUP_ID_TRANSLATE_EVERYTHING,
			'Translate Everything loop iteration',
			[ 'inputKey' => $data->get( 'key' ) ]
		);

		try {
			if ( ! MediaOption::isSetupFinished() ) {
				JobLog::addError( 'tea_run_aborted_media_setup', [] );
				return Left::of( [ 'key' => 'media-setup-not-finished' ] );
			}

			if ( ! AutomaticTranslationCapabilities::doesDefaultLanguageSupport() ) {
				JobLog::addError( 'tea_run_aborted_default_lang', [] );
				return Left::of( [ 'error' => 'default-language-does-not-support-automatic-translations' ] );
			}

			$lock = make( KeyedLock::class, [ ':name' => self::class ] );
			$key  = $lock->create( $data->get( 'key' ), self::LOCK_RELEASE_TIMEOUT );

			if ( ! $key ) {
				JobLog::add( 'tea_lock_busy_skipping', [] );
				return Left::of( [ 'key' => 'in-use' ] );
			}

			JobLog::add( 'tea_lock_acquired', [] );

			$createdJobs = [];
			if ( Option::shouldTranslateEverything() ) {
				$createdJobs = $this->translateEverything( $actions );
			} else {
				JobLog::add( 'tea_skipped_option_off', [] );
			}

			$everythingProcessed = $this->isEverythingProcessed( true );
			$isDisabled = ! Option::shouldTranslateEverything();
			JobLog::add( 'tea_iteration_done', [
				'jobs_created_count'   => is_array( $createdJobs ) ? count( $createdJobs ) : 0,
				'everything_processed' => $everythingProcessed,
				'disabled'             => $isDisabled,
			] );

			$teaStillEnabled = AutomaticTranslationCapabilities::shouldTranslateEverythingFresh();

			$madeProgress = is_array( $createdJobs ) && count( $createdJobs ) > 0;

			if ( $everythingProcessed || ! $teaStillEnabled || $isDisabled || ! $madeProgress ) {
				$lock->release();
				JobLog::add( 'tea_completed_or_disabled_releasing_lock', [
					'everything_processed' => $everythingProcessed,
					'made_progress'        => $madeProgress,
				] );
				$key = false;
			} elseif ( ! $data->get( 'holdLock', true ) ) {
				$lock->release();
				JobLog::add( 'tea_batch_done_releasing_lock', [] );
				$key = false;
			}

			if ( ! $teaStillEnabled && is_array( $createdJobs ) && ! empty( $createdJobs ) ) {
				JobLog::add( 'tea_cancelling_orphan_batch', [
					'count' => count( $createdJobs ),
				] );
				do_action( 'wpml_cancel_all_automatic_jobs', make( BatchResult::class ) );
				$createdJobs = [];
			}

			return Right::of( [ 'key' => $key, 'createdJobs' => $createdJobs, 'disabled' => $isDisabled ] );
		} finally {
			JobLog::finishCurrentGroup();
		}
	}

	private function translateEverything( Actions $actions ) {
		foreach ( $this->untranslated_elements as $untranslated ) {
			JobLog::addExtraLogData( 'strategy', get_class( $untranslated ) );

			try {
				$staleRefetched = [];

				while ( ! $untranslated->isEverythingProcessed( true ) ) {
					if ( ! AutomaticTranslationCapabilities::shouldTranslateEverythingFresh() ) {
						JobLog::add( 'tea_loop_aborted_option_off', [] );
						break;
					}

					if ( ClientRestrictionState::isRestricted() ) {
						JobLog::add( 'tea_loop_aborted_client_restricted', [] );
						break;
					}

					if ( SpendCapState::holds() ) {
						JobLog::add( 'tea_loop_aborted_spend_cap_reached', [] );
						break;
					}

					list( $types, $languages ) = $untranslated->getTypeWithLanguagesToProcess();
					if ( ! $types || ! $languages ) {
						JobLog::add( 'tea_strategy_skipped_no_types_or_langs', [] );
						break;
					}

					$defaultCode = Languages::getDefaultCode();
					if ( ! in_array( $defaultCode, $languages, true ) && ! TranslationPause::isPaused( $defaultCode ) ) {
						$languages[] = $defaultCode;
					}

					$queueSize = $untranslated->getQueueSize();
					$elements  = $untranslated->getElementsToProcess( $languages, $types, $queueSize + 1 );

					JobLog::add( 'tea_strategy_batch', [
						'types'            => $types,
						'languages'        => $languages,
						'queue_size'       => $queueSize,
						'elements_found'   => count( $elements ),
						'will_create_jobs' => count( $elements ) > 0,
					] );

					$created       = null;
					$deferred      = false;
					$typeCompleted = false;
					if ( count( $elements ) ) {
						if ( ! AutomaticTranslationCapabilities::shouldTranslateEverythingFresh() ) {
							JobLog::add( 'tea_jobs_creation_aborted_option_off', [
								'would_have_created_count' => count( $elements ),
							] );
							return [];
						}

						$created = $untranslated->createTranslationJobs( $actions, Lst::slice( 0, $queueSize, $elements ), $types );
						JobLog::add( 'tea_strategy_jobs_created', [
							'created_count' => is_array( $created ) ? count( $created ) : 0,
						] );
					}

					if ( count( $elements ) <= $queueSize ) {
						( new \WPML\WP\OptionManager() )->invalidateGroup( \WPML\Setup\Option::OPTION_GROUP );

						if ( ! $untranslated->getElementsToProcess( $languages, $types, $queueSize ) ) {
							if ( $untranslated instanceof AbstractUntranslatedElements ) {
								$untranslated->markTypeAsCompleted( $types, $languages, $queueSize );
							} else {
								$untranslated->markTypeAsCompleted( $types );
							}
							$typeCompleted = true;
							JobLog::add( 'tea_strategy_type_completed', [ 'types' => $types ] );
						} else {
							$deferred = true;
							JobLog::add( 'tea_strategy_completion_deferred', [ 'types' => $types ] );
						}
					}

					if ( ! empty( $created ) ) {
						return $created;
					}

					if ( null !== $created ) {
						if ( $typeCompleted ) {
							JobLog::add( 'tea_strategy_type_completed_locally', [
								'types'          => $types,
								'elements_found' => count( $elements ),
							] );
							continue;
						}

						if ( $untranslated instanceof CreatableElementsInterface
							&& ! $untranslated->filterCreatableElements( $elements ) ) {
							$untranslated->markTypeAsCompleted( $types );
							JobLog::add( 'tea_strategy_type_completed_nothing_creatable', [
								'types'          => $types,
								'elements_found' => count( $elements ),
							] );
							continue;
						}

						JobLog::add( 'tea_strategy_no_progress', [
							'types'          => $types,
							'elements_found' => count( $elements ),
							'reason'         => 'batch-created-no-jobs',
						] );
						break;
					}

					if ( $deferred ) {
						$typeKey = is_array( $types ) ? implode( ',', $types ) : (string) $types;
						if ( isset( $staleRefetched[ $typeKey ] ) ) {
							JobLog::add( 'tea_strategy_no_progress', [
								'types'  => $types,
								'reason' => 'stale-refetch-did-not-progress',
							] );
							break;
						}
						$staleRefetched[ $typeKey ] = true;
					}
				}
			} finally {
				JobLog::removeExtraLogData( 'strategy' );
			}
		}

		return [];
	}


	public function isEverythingProcessed( $cached = false ) {
		foreach ( $this->untranslated_elements as $untranslated ) {
			if ( ! $untranslated->isEverythingProcessed( $cached ) ) {
				return false;
			}
		}

		return true;
	}

	public function markEverythingAsCompleted() {
		foreach ( $this->untranslated_elements as $untranslated ) {
			$untranslated->markEverythingAsCompleted();
		}
	}


	public function markEverythingAsUncompleted() {
		foreach ( $this->untranslated_elements as $untranslated ) {
			$untranslated->markEverythingAsUncompleted();
		}
	}

	public function markSkippedPostTypesAsCompleted() {
		foreach ( $this->untranslated_elements as $untranslated ) {
			if ( $untranslated instanceof UntranslatedPosts ) {
				$untranslated->markSkippedTypesAsCompleted();
			}
		}
	}

	public function markLanguagesAsCompleted( array $languages ) {
		foreach ( $this->untranslated_elements as $untranslated ) {
			$untranslated->markLanguagesAsCompleted( $languages );
		}
	}

	public function markLanguagesAsUncompleted( array $languages ) {
		foreach ( $this->untranslated_elements as $untranslated ) {
			$untranslated->markLanguagesAsUncompleted( $languages );
		}
	}

	const STRATEGY_ORDER_STRINGS = 0;

	const STRATEGY_ORDER_TERMS = 1;

	const STRATEGY_ORDER_POSTS = 2;

	private function sortStrategiesByTier( array $strategies ): array {
		usort(
			$strategies,
			function ( UntranslatedElementsInterface $a, UntranslatedElementsInterface $b ): int {
				return self::getStrategyOrder( $a ) <=> self::getStrategyOrder( $b );
			}
		);
		return $strategies;
	}

	private static function getStrategyOrder( UntranslatedElementsInterface $strategy ): int {
		if ( $strategy instanceof UntranslatedPosts ) {
			return self::STRATEGY_ORDER_POSTS;
		}

		if ( $strategy instanceof UntranslatedTerms ) {
			return self::STRATEGY_ORDER_TERMS;
		}

		return self::STRATEGY_ORDER_STRINGS;
	}
}