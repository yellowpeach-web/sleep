<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\API\PostTypes;
use WPML\Element\API\Languages;
use WPML\FP\Lst;
use WPML\Infrastructure\WordPress\Component\Item\Application\Query\TranslationToSendCondition;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryPrepare;
use WPML\LanguageEditor\TranslationPause;
use WPML\Setup\Option;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\API\CachedATEAPI;
use WPML\TM\ATE\AutomaticPairSupport;
use WPML\TM\ATE\Review\ReviewRulesFactory;
use WPML\TM\ATE\Review\ReviewStatus;
use WPML\TM\AutomaticTranslation\Actions\Actions;
use WPML\TM\Jobs\JobLog;
use WPML\WP\OptionManager;

abstract class AbstractUntranslatedElements implements UntranslatedElementsInterface {

	protected $wpdb;

	private $toSend;

	private $oldJobsEditor;

	const COMPLETION_REFUSAL_NEW_ELEMENTS = 'new_elements';
	const COMPLETION_REFUSAL_CARRIED_PAIR = 'carried_pair';

	private $completionRefusalReasons = [];

	public function getCompletionRefusalReason( string $type ): ?string {
		return $this->completionRefusalReasons[ $type ] ?? null;
	}

	private $refusedSourcesMemo = [];

	private static $originLanguagesMemo = [];

	const ORIGIN_LANGUAGES_TRANSIENT_PREFIX = 'wpml_tea_origin_languages_';

	public function __construct( \wpdb $wpdb, ?\WPML_TM_Old_Jobs_Editor $oldJobsEditor = null ) {
		$this->wpdb          = $wpdb;
		$this->oldJobsEditor = $oldJobsEditor;
	}

	/**
	 * Resolved on first use, never in the constructor (wpmldev-8194).
	 *
	 * Auryn cannot autowire the nullable argument, so every make() of a
	 * strategy hands in null. The editor is read in exactly one place,
	 * {@see self::buildOldEditorCondition()}, on the Translate Everything query
	 * path. The strategies are also built on every licence by the bookkeeping
	 * hooks (edited_term, wpml_st_package_created, ...), and on a Blog licence
	 * tm.php is not loaded, so the loader function does not exist: resolving it
	 * eagerly turned "Update category" into a fatal error.
	 *
	 * @return \WPML_TM_Old_Jobs_Editor|null Null when Translation Management is not loaded for this request.
	 */
	private function getOldJobsEditor() {
		if ( ! $this->oldJobsEditor && function_exists( 'wpml_tm_load_old_jobs_editor' ) ) {
			$this->oldJobsEditor = \wpml_tm_load_old_jobs_editor();
		}

		return $this->oldJobsEditor;
	}

	public function getQueueSize(): int {
		return 15;
	}

	public function getEligibleLanguageCodes( bool $cached = false ): array {
		$mapper = $cached ? CachedLanguageMappings::class : LanguageMappings::class;

		return TranslationPause::filterTranslatable( $mapper::geCodesEligibleForAutomaticTranslations() );
	}

	public function isEverythingProcessed( $cached = false ) {
		$completed = $this->getCompleted();
		$languages = $this->getEligibleLanguageCodes( $cached );

		foreach ( $this->getTypes() as $type ) {
			$completedLanguages = $completed[ $this->completedKey( $type ) ] ?? [];
			$remainingLanguages = Lst::diff( $languages, $completedLanguages );
			if ( count( $remainingLanguages ) > 0 ) {
				return false;
			}
		}

		return true;
	}

	public function markTypeAsCompleted( string $type, ?array $probeLanguages = null, int $probeLimit = 1 ) {
		unset( $this->completionRefusalReasons[ $type ] );
		$languages    = $this->getCompletableLanguageCodes();
		$completedNow = true;

		$this->updateCompleted(
			function ( array $completed ) use ( $type, $languages, $probeLanguages, $probeLimit, &$completedNow ) {
				if ( null !== $probeLanguages && $this->hasElementsToProcessNow( $probeLanguages, $type, $probeLimit ) ) {
					$completedNow = false;
					$this->completionRefusalReasons[ $type ] = self::COMPLETION_REFUSAL_NEW_ELEMENTS;
					JobLog::add( 'tea_strategy_completion_refused_new_elements', [ 'types' => $type ] );

					return $completed;
				}
				if ( null !== $probeLanguages && $this->hasCarriedPairToProcessNow( $probeLanguages, $type ) ) {
					$completedNow = false;
					$this->completionRefusalReasons[ $type ] = self::COMPLETION_REFUSAL_CARRIED_PAIR;
					JobLog::add( 'tea_strategy_completion_refused_carried_pair', [ 'types' => $type ] );

					return $completed;
				}

				$key               = $this->completedKey( $type );
				$completed[ $key ] = array_merge( $completed[ $key ] ?? [], $languages );

				return $completed;
			}
		);

		return $completedNow;
	}

	private function hasElementsToProcessNow( array $languages, string $type, int $limit ): bool {
		( new OptionManager() )->invalidateGroup( Option::OPTION_GROUP );

		return (bool) $this->getElementsToProcess( $languages, $type, max( 1, $limit ) );
	}

	protected function hasCarriedPairToProcessNow( array $languages, string $type ): bool {
		return false;
	}

	protected function getCompletableLanguageCodes(): array {
		return Languages::getSecondaryCodes();
	}

	public function markEverythingAsCompleted() {
		$types     = $this->getTypes();
		$languages = $this->getCompletableLanguageCodes();

		$this->updateCompleted(
			function ( array $completed ) use ( $types, $languages ) {
				foreach ( $types as $type ) {
					$key               = $this->completedKey( $type );
					$completed[ $key ] = array_merge( $completed[ $key ] ?? [], $languages );
				}

				return $completed;
			}
		);
	}


	public function markEverythingAsUncompleted() {
		$this->updateCompleted(
			function () {
				return [];
			}
		);
	}

	public function markLanguagesAsCompleted( array $languages ) {
		$types = $this->getTypes();

		$this->updateCompleted(
			function ( array $completed ) use ( $types, $languages ) {
				foreach ( $types as $type ) {
					$key               = $this->completedKey( $type );
					$completed[ $key ] = array_merge( $completed[ $key ] ?? [], $languages );
				}

				return $completed;
			}
		);
	}

	public function markLanguagesAsUncompleted( array $languages ) {
		$types = $this->getTypes();

		$this->updateCompleted(
			function ( array $completed ) use ( $types, $languages ) {
				foreach ( $types as $type ) {
					$key               = $this->completedKey( $type );
					$typeValues        = Lst::diff( $completed[ $key ] ?? [], $languages );
					$completed[ $key ] = is_array( $typeValues ) ? array_values( $typeValues ) : $typeValues;
				}

				return $completed;
			}
		);
	}

	protected function updateCompleted( callable $change ) {
		$key = $this->getCompletedOptionKey();

		if ( null === $key ) {
			$this->setCompleted( CompletedMap::compact( $change( $this->getCompleted() ) ) );

			return;
		}

		CompletedMap::update( $key, $change );
	}

	protected function completedKey( string $type ): string {
		return $type;
	}

	protected function getCompletedOptionKey() {
		return null;
	}

	abstract protected function getCompleted(): array;

	protected function setCompleted( array $completed ) {
		throw new \LogicException( static::class . ' names no completed-map option key and does not override setCompleted().' );
	}

	abstract protected function getTypes(): array;

	protected function buildOldEditorCondition(): string {
		$oldEditorCondition = '';
		$oldJobsEditor      = $this->getOldJobsEditor();

		if ( $oldJobsEditor && $oldJobsEditor->editorForTranslationsPreviouslyCreatedUsingCTE() === \WPML_TM_Editors::WPML ) {
			$editor = \WPML_TM_Editors::WPML;
			$oldEditorCondition = "AND (
				translation_status.needs_update = 0 OR IFNULL(
					(
					  SELECT jobs.editor FROM {$this->wpdb->prefix}icl_translate_job jobs
					  WHERE jobs.rid = translation_status.rid
					  ORDER BY jobs.job_id DESC
					  LIMIT 1
					),
					'ate'
				) != '{$editor}'
			)";
		}

		return $oldEditorCondition;
	}

	protected function buildLanguagesUnion( array $languages ): string {
		$wpdb = $this->wpdb;

		$languages = array_values( array_unique( array_map( 'strval', $languages ) ) );

		$refusedSources = $this->refusedSourcesByTarget( $languages );

		return implode(
			' UNION ALL ',
			array_map(
				function ( $code ) use ( $wpdb, $refusedSources ) {
					if ( ! $refusedSources ) {
						return $wpdb->prepare( 'SELECT %s AS code', $code );
					}

					return $wpdb->prepare(
						'SELECT %s AS code, %s AS refused_sources',
						$code,
						implode( ',', $refusedSources[ $code ] ?? [] )
					);
				},
				$languages
			)
		);
	}

	protected function notOpenForReviewInAteSql( string $statusAlias ): string {
		if ( ! ReviewRulesFactory::create()->refusesSendOfReviewOpenInAte() ) {
			return '';
		}

		return "AND ( {$statusAlias}.review_status IS NULL OR {$statusAlias}.review_status <> '" . ReviewStatus::EDITING . "' )";
	}

	protected function toSend(): TranslationToSendCondition {
		if ( null === $this->toSend ) {
			$this->toSend = new TranslationToSendCondition( new QueryPrepare( $this->wpdb ) );
		}

		return $this->toSend;
	}

	protected function pairNotRefusedSql( string $sourceColumn, array $languages ): string {
		$languages = array_values( array_unique( array_map( 'strval', $languages ) ) );

		if ( ! $this->refusedSourcesByTarget( $languages ) ) {
			return '';
		}

		return "AND NOT FIND_IN_SET( {$sourceColumn}, languages.refused_sources )";
	}

	private function refusedSourcesByTarget( array $languages ): array {
		$key = implode( ',', $languages );
		if ( ! array_key_exists( $key, $this->refusedSourcesMemo ) ) {
			$this->refusedSourcesMemo[ $key ] = array_filter(
				AutomaticPairSupport::refusedSourcesByTarget( $this->originLanguages(), $languages )
			);
		}

		return $this->refusedSourcesMemo[ $key ];
	}

	protected function originLanguages(): array {
		$default   = (string) Languages::getDefaultCode();
		$prefix    = $this->getElementTypePrefix();
		$transient = self::ORIGIN_LANGUAGES_TRANSIENT_PREFIX . $prefix;

		if ( ! array_key_exists( $prefix, self::$originLanguagesMemo ) ) {
			$kept = get_transient( $transient );
			if ( is_array( $kept ) ) {
				self::$originLanguagesMemo[ $prefix ] = array_map( 'strval', $kept );
			}
		}

		if ( ! array_key_exists( $prefix, self::$originLanguagesMemo ) ) {
			$origins = [];
			try {
				$wpdb = $this->wpdb;
				$codes = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT language_code FROM {$wpdb->prefix}icl_translations WHERE source_language_code IS NULL AND element_type LIKE %s",
						str_replace( [ '\\', '%', '_' ], [ '\\\\', '\\%', '\\_' ], $prefix ) . '%'
					)
				);
				$read    = is_array( $codes ) && ( ! isset( $wpdb->last_error ) || '' === (string) $wpdb->last_error );
				$origins = $read ? array_map( 'strval', $codes ) : [];
				if ( $read ) {
					set_transient( $transient, $origins, CachedATEAPI::UNSUPPORTED_ANSWER_TTL );
				}
			} catch ( \Throwable $e ) {
				$origins = [];
			}
			self::$originLanguagesMemo[ $prefix ] = $origins;
		}

		$active = array_map( 'strval', array_keys( (array) Languages::getActive() ) );

		return array_values( array_unique( array_merge(
			'' === $default ? [] : [ $default ],
			array_values( array_intersect( self::$originLanguagesMemo[ $prefix ], $active ) )
		) ) );
	}

	public static function resetOriginLanguagesMemo() {
		self::$originLanguagesMemo = [];
	}

	abstract protected function getElementTypePrefix(): string;

	public function createTranslationJobsFromAllLanguages( Actions $actions, array $elements, $type ) {
		$sourceLanguageAndElements = [];
		foreach ( $elements as $element ) {
			$id = $element[0];

			$sourceLang = count( $element ) === 3 ? $element[1] : Languages::getDefaultCode();
			$targetLang = count( $element ) === 3 ? $element[2] : $element[1];

			if ( ! isset( $sourceLanguageAndElements[ $sourceLang ] ) ) {
				$sourceLanguageAndElements[ $sourceLang ] = [];
			}
			$sourceLanguageAndElements[ $sourceLang ][] = [ $id, $targetLang ];
		}

		$result       = [];
		$prefixedType = $this->getElementTypePrefix() . $type;
		foreach ( $sourceLanguageAndElements as $sourceLang => $elements ) {
			$jobs   = $actions->createNewTranslationJobs( $sourceLang, $elements, $prefixedType, true );
			$result = array_merge( $result, $jobs );
		}

		return $result;
	}
}
