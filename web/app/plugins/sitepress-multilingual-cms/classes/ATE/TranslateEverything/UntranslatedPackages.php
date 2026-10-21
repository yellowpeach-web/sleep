<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\API\PostTypes;
use WPML\Element\API\Languages;
use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\Infrastructure\WordPress\Component\StringPackage\Application\Query\PackageDefinitionQuery;
use WPML\Setup\Option;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\AutomaticTranslation\Actions\Actions;
use WPML\TM\Jobs\JobLog;
use WPML\WP\OptionManager;
use function WPML\FP\pipe;

class UntranslatedPackages extends AbstractUntranslatedElements {

	const UNBUILDABLE_KEY = 'translate-everything-unbuildable-packages';

	const UNBUILDABLE_REASON = 'no translation job could be built for the package';

	private $translatablePackages;

	public function __construct(
		\wpdb $wpdb,
		?\WPML_TM_Old_Jobs_Editor $oldJobsEditor = null,
		?PackageDefinitionQuery $translatablePackages = null
	) {
		parent::__construct( $wpdb, $oldJobsEditor );
		$this->translatablePackages = $translatablePackages ?: new PackageDefinitionQuery();
	}

	public function getTypeWithLanguagesToProcess() {
		$packageKinds = $this->getPackageKindToTranslate(
			$this->getTypes(),
			$this->getEligibleLanguageCodes( true )
		);

		return wpml_collect( $packageKinds )
			->first();
	}

	public function getElementsToProcess( $languages, $type, $queueSize ) {
		$wpdb = $this->wpdb;

		if ( empty( $languages ) ) {
			return [];
		}

		$sinceDate = Option::getTranslateEverythingPackageKindSinceDate( $type );

		if ( Cutoff::isSkip( $sinceDate ) ) {
			return [];
		}

		$ownerPostCondition = Cutoff::ownerPostSql( 'owner_post' );

		$unbuildable          = array_map( 'strval', array_keys( $this->getUnbuildablePackages() ) );
		$unbuildableCondition = $unbuildable
			? "AND CONCAT( original_element.element_id, ':', languages.code ) NOT IN ( " . implode( ', ', array_fill( 0, count( $unbuildable ), '%s' ) ) . ' )'
			: '';

		$language_parts      = $this->buildLanguagesUnion( $languages );
		$acceptable_statuses = ICL_TM_NOT_TRANSLATED . ', ' . ICL_TM_ATE_CANCELLED;

		$oldEditorCondition = $this->buildOldEditorCondition();

		$discardedJob     = DiscardedAutomaticJob::joinSql( $this->wpdb->prefix, 'translation_status' );
		$notADiscardedJob = DiscardedAutomaticJob::whereSql( 'translation_status' );

		$sql = "
			SELECT original_element.element_id, original_element.language_code, languages.code
			FROM {$this->wpdb->prefix}icl_translations original_element
			INNER JOIN ( {$language_parts} ) as languages
    		LEFT JOIN {$this->wpdb->prefix}icl_translations translations ON translations.trid = original_element.trid AND translations.language_code = languages.code
    		LEFT JOIN {$this->wpdb->prefix}icl_translation_status translation_status ON translation_status.translation_id = translations.translation_id
			{$discardedJob}

			INNER JOIN {$this->wpdb->prefix}icl_string_packages packages ON packages.ID = original_element.element_id
			LEFT JOIN {$this->wpdb->posts} owner_post ON owner_post.ID = packages.post_id

			WHERE original_element.element_type like %s
				AND {$ownerPostCondition}
				{$unbuildableCondition}
				AND original_element.source_language_code IS NULL
				AND original_element.language_code != languages.code
    		AND ( translation_status.status IS NULL OR translation_status.status IN ({$acceptable_statuses}) OR translation_status.needs_update = 1)
				{$notADiscardedJob}
				{$oldEditorCondition}
			ORDER BY original_element.element_id, languages.code
			LIMIT %d
		";

		$result = $this->wpdb->get_results(
			$this->wpdb->prepare(
				$sql,
				...array_merge( [ 'package_' . $type, $sinceDate ], $unbuildable, [ $queueSize ] )
			),
			ARRAY_N
		);

		return Fns::map( Obj::evolve( [ 0 => Cast::toInt() ] ), $result );
	}

	public function createTranslationJobs( Actions $actions, array $elements, $type ) {
		$built       = [];
		$unbuildable = [];
		foreach ( $this->createTranslationJobsFromAllLanguages( $actions, $elements, $type ) as $job ) {
			if ( (int) ( $job['jobId'] ?? 0 ) > 0 ) {
				$built[] = $job;
			} else {
				$unbuildable[] = $job;
			}
		}

		if ( $unbuildable ) {
			$this->recordUnbuildable( $unbuildable, (string) $type );
		}

		return $built;
	}

	public function getUnbuildablePackages(): array {
		$recorded = ( new OptionManager() )->get( Option::OPTION_GROUP, self::UNBUILDABLE_KEY, [] );

		return is_array( $recorded ) ? $recorded : [];
	}

	private function recordUnbuildable( array $jobs, string $kind ) {
		$recorded = $this->getUnbuildablePackages();
		$new      = [];
		foreach ( $jobs as $job ) {
			$packageId = (int) ( $job['elementId'] ?? 0 );
			$language  = (string) ( $job['lang'] ?? '' );
			$key       = $packageId . ':' . $language;
			if ( $packageId <= 0 || '' === $language || isset( $recorded[ $key ] ) ) {
				continue;
			}
			$recorded[ $key ] = [
				'package_id'  => $packageId,
				'language'    => $language,
				'kind'        => $kind,
				'reason'      => self::UNBUILDABLE_REASON,
				'recorded_at' => gmdate( 'Y-m-d H:i:s' ),
			];

			$new[] = $recorded[ $key ];
		}

		if ( ! $new ) {
			return;
		}

		$this->setUnbuildablePackages( $recorded );
		foreach ( $new as $entry ) {
			JobLog::add( 'tea_package_unbuildable_recorded', $entry );
		}
	}

	private function setUnbuildablePackages( array $recorded ) {
		( new OptionManager() )->set( Option::OPTION_GROUP, self::UNBUILDABLE_KEY, $recorded, false );
	}

	public function markEverythingAsUncompleted() {
		parent::markEverythingAsUncompleted();
		if ( $this->getUnbuildablePackages() ) {
			$this->setUnbuildablePackages( [] );
		}
	}


	protected function getElementTypePrefix(): string {
		return 'package_';
	}

	private function getPackageKindToTranslate( array $packageKinds, array $languages ) {
		$completed                           = $this->getCompleted();
		$getLanguageCodesNotCompletedForKind = pipe( Obj::propOr( [], Fns::__, $completed ), Lst::diff( $languages ) );

		$getPackageKindToTranslate = pipe(
			Fns::map(
				function ( $kind ) use ( $getLanguageCodesNotCompletedForKind ) {
					return [ $kind, $getLanguageCodesNotCompletedForKind( self::kindKey( (string) $kind ) ) ];
				}
			),
			Fns::filter( pipe( Obj::prop( 1 ), Lst::length() ) )
		);

		return $getPackageKindToTranslate( $packageKinds );
	}

	public function markKindAsUncompleted( string $kind ) {
		$this->updateCompleted(
			function ( array $completed ) use ( $kind ) {
				$completed[ self::kindKey( $kind ) ] = [];

				return $completed;
			}
		);

		$recorded = $this->getUnbuildablePackages();
		$kept     = array_filter(
			$recorded,
			function ( $entry ) use ( $kind ) {
				return 0 !== strcasecmp( (string) ( $entry['kind'] ?? '' ), $kind );
			}
		);
		if ( count( $kept ) !== count( $recorded ) ) {
			$this->setUnbuildablePackages( $kept );
		}
	}


	public static function kindKey( string $kind ): string {
		return strtolower( $kind );
	}

	public static function normalizeKindKeys( array $completed ): array {
		$normalized = [];
		foreach ( $completed as $kind => $languages ) {
			$key       = self::kindKey( (string) $kind );
			$languages = is_array( $languages ) ? array_values( $languages ) : [];

			$normalized[ $key ] = array_key_exists( $key, $normalized )
				? array_values( array_intersect( $normalized[ $key ], $languages ) )
				: $languages;
		}

		return $normalized;
	}

	protected function completedKey( string $type ): string {
		return self::kindKey( $type );
	}

	protected function getCompleted(): array {
		return self::normalizeKindKeys( Option::getTranslateEverythingCompletedPackages() );
	}

	protected function getCompletedOptionKey() {
		return Option::TRANSLATE_EVERYTHING_PACKAGES_COMPLETED;
	}

	protected function getTypes(): array {
		return $this->translatablePackages->getNamesList();
	}


}
