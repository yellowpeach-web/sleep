<?php

namespace WPML\StringTranslation\Infrastructure\TranslateEverything;

use WPML\API\PostTypes;
use WPML\Core\Component\Translation\Application\String\Repository\StringBatchRepositoryInterface;
use WPML\Core\SharedKernel\Component\Language\Domain\LanguageCode;
use WPML\Element\API\Languages;
use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\Setup\Option;
use WPML\ST\TranslationPauseScope;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\TranslateEverything\UntranslatedElementsInterface;
use WPML\TM\AutomaticTranslation\Actions\Actions;

class UntranslatedStrings implements UntranslatedElementsInterface {

	const ENGLISH_SOURCE_LANGUAGE = LanguageCode::ENGLISH;

	private $wpdb;

	private $stringBatchRepository;

	public function __construct( StringBatchRepositoryInterface $stringBatchRepository, ?\wpdb $wpdb = null ) {
		$this->stringBatchRepository = $stringBatchRepository;

		if ( ! $wpdb ) {
			global $wpdb;
		}
		$this->wpdb = $wpdb;
	}

	public function getTypeWithLanguagesToProcess() {
		$completed             = $this->getCompleted();
		$notCompletedLanguages = array_diff( $this->getEligibleLanguageCodes( true ), $completed );

		return [ 'string', $notCompletedLanguages ];
	}

	public function getElementsToProcess( $languages, $type, $queueSize ) {
		return $this->queryElementsToProcess( $languages, (int) $queueSize, [] );
	}

	public function getElementsToProcessForStrings( array $languages, array $stringIds ): array {
		$stringIds = array_values( array_unique( array_filter( array_map( 'intval', $stringIds ) ) ) );
		if ( ! $languages || ! $stringIds ) {
			return [];
		}

		return $this->queryElementsToProcess( $languages, count( $stringIds ) * count( $languages ), $stringIds );
	}

	private function queryElementsToProcess( $languages, int $queueSize, array $stringIds ): array {
		$languageSelect    = implode(
			' UNION ALL ',
			array_fill( 0, count( $languages ), 'SELECT %s AS code' )
		);
		$languageCrossJoin = "
			CROSS JOIN (
				$languageSelect	
			) as langs
		";

		$inFlight    = ActiveStringBatchJob::joinSql( $this->wpdb->prefix, 'strings', 'langs.code' );
		$notInFlight = ActiveStringBatchJob::whereSql();

		$discardedAutomaticBatch      = DiscardedAutomaticBatch::joinSql( $this->wpdb->prefix, 'strings', 'langs.code' );
		$notInDiscardedAutomaticBatch = DiscardedAutomaticBatch::whereSql();

		$stringIdsCondition = $stringIds
			? 'AND strings.id IN ( ' . implode( ', ', array_fill( 0, count( $stringIds ), '%d' ) ) . ' )'
			: '';

		$sql = "
			SELECT strings.id, langs.code AS language_code
			FROM {$this->wpdb->prefix}icl_strings strings
			{$languageCrossJoin}
			LEFT JOIN {$this->wpdb->prefix}icl_string_translations translations
				ON strings.id = translations.string_id AND translations.language = langs.code
			{$inFlight}
			{$discardedAutomaticBatch}
			WHERE (
					(
						strings.string_type = 1
						AND EXISTS (
							SELECT 1
							FROM {$this->wpdb->prefix}icl_string_positions positions
							WHERE positions.string_id = strings.id
								AND positions.kind = %d
						)
					)
					-- Besides the autoregistered frontend strings above, the scope admits
					-- two sets of programmatically registered strings (string_type 0, no
					-- position row), each by its exact identity, never every type-0
					-- string (widget, package and other programmatic strings keep their
					-- own paths):
					--
					-- 1. The site-identity pair, context 'WP', names 'Blog Title' and
					--    'Tagline', which WPML_String_Translation::
					--    initialize_wp_and_widget_strings() registers (wpmldev-7226).
					-- 2. Registered admin texts: every string whose context starts with
					--    WPML_Admin_Texts::DOMAIN_NAME_PREFIX ('admin_texts_'). Both ways
					--    of registering an admin text write that context and nothing else
					--    does: the Admin Texts screen (WPML_Admin_Texts::
					--    icl_register_admin_options()) and a wpml-config.xml
					--    <admin-texts> block (WPML_Admin_Text_Import). The Admin Texts
					--    screen reads the same rows as registered, and unticking a text
					--    deletes its row (icl_unregister_string()), so an unregistered
					--    text leaves the scope. Registering is the user's deliberate act,
					--    so a registered admin text is sent and billed like any other
					--    string (wpmldev-10707). The prefix is bound through esc_like(),
					--    so its underscores match only underscores.
					OR (
						strings.context = %s
						AND strings.name IN ( %s, %s )
					)
					OR strings.context LIKE %s
				)
				AND ( translations.status IS NULL OR translations.status = 0 )
				-- wpmldev-7665: the line above reads the ST status MIRROR, which
				-- is written after ATE has been called and billed, by a deferred
				-- closure on the far side of a hook chain that can fail in
				-- between. A mirror reading 0 or NULL is therefore not proof
				-- the string is idle -- in every reproducing run the mirror was the
				-- only thing that was wrong while the job tables were correct,
				-- so strings that had already been sent and paid for were picked
				-- again, re-batched byte-identically and re-billed.
				--
				-- Ask the job tables instead: a string carrying an ACTIVE
				-- st-batch job for this language is in flight, full stop. The
				-- pairs are the in_flight derived table joined above, built once
				-- per query (ActiveStringBatchJob, wpmldev-10878, wpmldev-10891).
				{$notInFlight}
				{$notInDiscardedAutomaticBatch}
				AND strings.language = %s
				-- Never target a string own source language. TEA appends the site
				-- default language to the target list (wpmldev-6406), so on an
				-- English-default site en appears as both source and target; without
				-- this guard each English string would be batched to translate into
				-- English, producing an empty-source ATE job (the Missing language
				-- mapping sync error, wpmldev-7222). Keyed on the string actual
				-- source, so a non-English string is still translated into English.
				AND langs.code != strings.language
				{$stringIdsCondition}
			ORDER BY langs.code, strings.id ASC
			LIMIT %d
		";

		$sql = $this->wpdb->prepare(
			$sql,
			array_merge(
				array_values( $languages ),
				ActiveStringBatchJob::args(),
				DiscardedAutomaticBatch::args(),
				[
					ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_FRONTEND,
					\WPML_ST_Blog_Name_And_Description_Hooks::STRING_DOMAIN,
					\WPML_ST_Blog_Name_And_Description_Hooks::STRING_NAME_BLOGNAME,
					\WPML_ST_Blog_Name_And_Description_Hooks::STRING_NAME_BLOGDESCRIPTION,
					$this->wpdb->esc_like( \WPML_Admin_Texts::DOMAIN_NAME_PREFIX ) . '%',
					$this->getSourceLanguage(),
				],
				$stringIds,
				[ $queueSize ]
			)
		);

		$rowset = $this->wpdb->get_results( $sql, ARRAY_N );

		return Fns::map(
			function ( $row ) {
				return [ (int) $row[0], $row[1] ];
			},
			$rowset
		);
	}

	public function createTranslationJobs( Actions $actions, array $elements, $type ) {
		$stringsGroupedByLanguages = \wpml_collect( $elements )
			->groupBy( 1 )
			->map( Lst::pluck( 0 ) )
			->map( Fns::map( Cast::toInt() ) )
			->toArray();

		$sourceLanguage = $this->getSourceLanguage();

		$batchElements = [];
		foreach ( $stringsGroupedByLanguages as $languageCode => $strings ) {
			$alreadyInFlight = $this->getStringIdsWithActiveJob( $strings, $languageCode );

			if ( $alreadyInFlight ) {
				$this->reportRefusedDuplicateJob( $alreadyInFlight, $languageCode );

				$strings = array_values( array_diff( $strings, $alreadyInFlight ) );

				$stringsGroupedByLanguages[ $languageCode ] = $strings;
			}

			if ( ! $strings ) {
				continue;
			}

			$batchId = $this->stringBatchRepository->create(
				'translate everything|string|' . $languageCode,
				$strings,
				$sourceLanguage
			);

			$this->markStringsAsWaiting( $strings, $languageCode );

			$batchElements[] = [ $batchId, $languageCode ];
		}

		if ( ! $batchElements ) {
			return [];
		}

		$jobs = $actions->createNewTranslationJobs( $sourceLanguage, $batchElements, 'st-batch', true );

		$jobsPerString = [];
		foreach ( $jobs as $job ) {
			$stringsInJobLanguages = $stringsGroupedByLanguages[ $job['lang'] ];

			foreach ( $stringsInJobLanguages as $string ) {
				$jobsPerString[] = [
					'elementId'   => $string,
					'lang'        => $job['lang'],
					'elementType' => 'string',
					'jobId'       => $job['jobId'],
				];
			}
		}

		return $jobsPerString;
	}

	private function getStringIdsWithActiveJob( array $stringIds, string $languageCode ): array {
		return ActiveStringBatchJob::stringIdsWithActiveJob( $this->wpdb, $stringIds, $languageCode );
	}

	private function reportRefusedDuplicateJob( array $stringIds, string $languageCode ) {
		ActiveStringBatchJob::reportRefused( $stringIds, $languageCode, 'tea_duplicate_string_job_refused' );
	}

	private function markStringsAsWaiting( array $stringIds, string $languageCode ) {
		ActiveStringBatchJob::markWaiting( $stringIds, $languageCode );
	}

	public function isEverythingProcessed( $cached = false ) {
		$completed = $this->getCompleted();

		return count( array_diff( $this->getEligibleLanguageCodes( $cached ), $completed ) ) === 0;
	}

	public function getQueueSize(): int {
		return 150;
	}

	public function getEligibleLanguageCodes( bool $cached = false ): array {
		$languageMapper = $cached ? CachedLanguageMappings::class : LanguageMappings::class;

		$targetLanguages = $languageMapper::geCodesEligibleForAutomaticTranslations();

		$targetLanguages = $this->maybeAppendDefaultLanguage( $languageMapper, $targetLanguages );

		$targetLanguages = $this->removeEnglishFromTargetLanguages( $targetLanguages );

		return TranslationPauseScope::translatable( array_values( $targetLanguages ) );
	}

	private function getTargetLanguages(): array {
		$targetLanguages = Languages::getSecondaryCodes();

		if ( Languages::getDefaultCode() !== $this->getSourceLanguage() ) {
			$primary         = [ Languages::getDefaultCode() ];
			$targetLanguages = array_merge( $targetLanguages, $primary );
		}

		$targetLanguages = $this->removeEnglishFromTargetLanguages( $targetLanguages );

		return $targetLanguages;
	}

	public function markTypeAsCompleted( string $type ) {
		$this->setCompleted( $this->getTargetLanguages() );
	}

	public function markEverythingAsCompleted() {
		$this->setCompleted( $this->getTargetLanguages() );
	}

	public function markEverythingAsUncompleted() {
		$this->setCompleted( [] );
	}

	public function markLanguagesAsCompleted( array $languages ) {
		$this->updateCompleted(
			function ( array $completed ) use ( $languages ) {
				return array_merge( $completed, $languages );
			}
		);
	}

	public function markLanguagesAsUncompleted( array $languages ) {
		$this->updateCompleted(
			function ( array $completed ) use ( $languages ) {
				return array_diff( $completed, $languages );
			}
		);
	}

	private function getCompleted() {
		return Option::getTranslateEverythingCompletedStrings();
	}

	private function setCompleted( array $completed ) {
		$this->updateCompleted(
			function () use ( $completed ) {
				return $completed;
			}
		);
	}

	private function updateCompleted( callable $change ) {
		CompletedList::update( Option::TRANSLATE_EVERYTHING_SRTINGS_COMPLETED, $change );
	}

	private function removeEnglishFromTargetLanguages( array $targetLanguages ): array {
		$sourceLanguage  = $this->getSourceLanguage();
		$targetLanguages = array_filter(
			$targetLanguages,
			function ( $languageCode ) use ( $sourceLanguage ) {
				return $sourceLanguage !== $languageCode;
			}
		);

		return $targetLanguages;
	}

	private function getSourceLanguage(): string {
		return EnglishSourceLanguage::resolveForSite();
	}

	private function maybeAppendDefaultLanguage( string $languageMapper, array $targetLanguages ): array {
		if ( Languages::getDefaultCode() !== $this->getSourceLanguage() && $languageMapper::doesDefaultLanguageSupportAutomaticTranslations() ) {
			$targetLanguages[] = Languages::getDefaultCode();
		}

		return $targetLanguages;
	}
}
