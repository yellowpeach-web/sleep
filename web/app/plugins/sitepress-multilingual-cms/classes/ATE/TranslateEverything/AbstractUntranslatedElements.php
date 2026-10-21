<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\API\PostTypes;
use WPML\Element\API\Languages;
use WPML\FP\Lst;
use WPML\LanguageEditor\TranslationPause;
use WPML\Setup\Option;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\AutomaticTranslation\Actions\Actions;
use WPML\TM\Jobs\JobLog;
use WPML\WP\OptionManager;

abstract class AbstractUntranslatedElements implements UntranslatedElementsInterface {

	protected $wpdb;

	private $oldJobsEditor;

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
		$languages = $this->getCompletableLanguageCodes();

		$this->updateCompleted(
			function ( array $completed ) use ( $type, $languages, $probeLanguages, $probeLimit ) {
				if ( null !== $probeLanguages && $this->hasElementsToProcessNow( $probeLanguages, $type, $probeLimit ) ) {
					JobLog::add( 'tea_strategy_completion_refused_new_elements', [ 'types' => $type ] );

					return $completed;
				}

				$key               = $this->completedKey( $type );
				$completed[ $key ] = array_merge( $completed[ $key ] ?? [], $languages );

				return $completed;
			}
		);
	}

	private function hasElementsToProcessNow( array $languages, string $type, int $limit ): bool {
		( new OptionManager() )->invalidateGroup( Option::OPTION_GROUP );

		return (bool) $this->getElementsToProcess( $languages, $type, max( 1, $limit ) );
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

		return implode(
			' UNION ALL ',
			array_map(
				function ( $code ) use ( $wpdb ) {
					return $wpdb->prepare( 'SELECT %s AS code', $code );
				},
				$languages
			)
		);
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
