<?php

namespace WPML\Infrastructure\WordPress\Component\StringPackage\Application\Query;

use WPML\Core\Component\StringPackage\Application\Query\Dto\PackageDefinitionDto;
use WPML\Core\Component\StringPackage\Application\Query\PackageDefinitionQueryInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\Core\SharedKernel\Component\Item\Application\Query\Dto\UntranslatedTypeCountDto;
use WPML\Core\SharedKernel\Component\Item\Application\Query\UntranslatedTypesCountQueryInterface;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class UntranslatedTypesCountQuery implements UntranslatedTypesCountQueryInterface {

  const IGNORED_IF_EMPTY = [ 'block' ];

  private $queryHandler;

  private $queryPrepare;

  private $languagesQuery;

  private $packageDefinitionRepository;


  public function __construct(
    QueryHandlerInterface $queryHandler,
    QueryPrepareInterface $queryPrepare,
    LanguagesQueryInterface $languagesQuery,
    PackageDefinitionQueryInterface $packageDefinitionRepository
  ) {
    $this->queryHandler                = $queryHandler;
    $this->queryPrepare                = $queryPrepare;
    $this->languagesQuery              = $languagesQuery;
    $this->packageDefinitionRepository = $packageDefinitionRepository;
  }


  public function forKind() {
    return UntranslatedTypesCountQueryInterface::KIND_PACKAGE;
  }


  public function get( array $queryData = [] ): array {
    $packageInfo = $this->packageDefinitionRepository->getInfoList();

    if ( ! $packageInfo ) {
      return [];
    }

    $translatablePackages = $this->buildTranslatablePackagesCondition(
      array_keys( $packageInfo )
    );

    $sql = "
      SELECT
        package.kind,
        package.kind_slug,
        COUNT( DISTINCT package.ID ) as count
      {$this->buildLeftToTranslateSelection( "package.kind_slug IN ({$translatablePackages})" )}
      GROUP BY package.kind
    ";

    try {
      $rowResult = $this->queryHandler->query( $sql )->getResults();

      $existingSlugs = array_map(
        function ( $slug ): string {
          return $this->kindKey( $slug );
        },
        array_column( $rowResult, 'kind_slug' )
      );
      $infoByKind    = $this->infoByKind( $packageInfo );

      foreach ( $packageInfo as $slug => $info ) {
        if (
          in_array( $this->kindKey( $slug ), $existingSlugs, true )
          || in_array( strtolower( $slug ), self::IGNORED_IF_EMPTY, true )
        ) {
          continue;
        }

        $rowResult[] = [
          'kind'      => $info->getTitle(),
          'kind_slug' => $slug,
          'count'     => 0,
        ];
      }

      return array_map(
        function ( $row ) use ( $infoByKind ) {
          $info = $infoByKind[ $this->kindKey( $row['kind_slug'] ) ] ?? null;

          $pluralName   = $info ? $info->getPlural() : $row['kind'];
          $singularName = $info ? $info->getTitle() : $row['kind'];

          return new UntranslatedTypeCountDto(
            $pluralName,
            $singularName,
            $row['count'],
            UntranslatedTypesCountQueryInterface::KIND_PACKAGE,
            $row['kind_slug']
          );
        },
        $rowResult
      );
    } catch ( DatabaseErrorException $e ) {
      return [];
    }
  }


  public function getSomeIds( $numberOfIdsToFetch, $offset, $type ) {
    $kindCondition = $this->queryPrepare->prepare( 'package.kind_slug = %s', $type );

    $sql = "
      SELECT DISTINCT
        package.ID
      {$this->buildLeftToTranslateSelection( $kindCondition )}
      ORDER BY package.ID ASC
      " . sprintf( 'LIMIT %d OFFSET %d', $numberOfIdsToFetch, $offset );

    try {
      $ids = $this->queryHandler->queryColumn( $sql );
      return $ids;
    } catch ( DatabaseErrorException $e ) {
      return [];
    }
  }


  private function buildLeftToTranslateSelection( string $kindCondition ): string {
    $prefix     = $this->queryPrepare->prefix();
    $statusesIn = implode(
      ',',
      [
        TranslationStatus::NOT_TRANSLATED,
        TranslationStatus::ATE_CANCELED,
      ]
    );

    return "
      FROM {$prefix}icl_string_packages package
      INNER JOIN {$prefix}icl_translations original_translation
        ON original_translation.element_id = package.ID
        AND original_translation.element_type = CONCAT('package_', package.kind_slug)
        AND original_translation.source_language_code IS NULL
      {$this->buildLanguageCrossJoin()}
      LEFT JOIN {$prefix}icl_translations translations
        ON translations.trid = original_translation.trid
        AND translations.language_code = langs.code
      LEFT JOIN {$prefix}icl_translation_status translation_status
        ON translation_status.translation_id = translations.translation_id
      WHERE {$kindCondition}
        AND langs.code <> original_translation.language_code
        AND (
          translation_status.status IS NULL
          OR translation_status.status IN ({$statusesIn})
          OR translation_status.needs_update = 1
        )
    ";
  }


  private function buildLanguageCrossJoin(): string {
    $codes = [ $this->languagesQuery->getDefaultCode() ];
    foreach ( $this->languagesQuery->getSecondaryTranslatable() as $language ) {
      $codes[] = $language->getCode();
    }

    $languageSelect = array_map(
      function ( string $code ) {
        return $this->queryPrepare->prepare( 'SELECT %s AS code', $code );
      },
      array_values( array_unique( $codes ) )
    );

    $languageSelect    = implode( ' UNION ALL ', $languageSelect );
    $languageCrossJoin = "
			CROSS JOIN (
				$languageSelect
			) as langs
		";

    return $languageCrossJoin;
  }


  private function kindKey( $kind ): string {
    return strtolower( (string) $kind );
  }


  private function infoByKind( array $packageInfo ): array {
    $byKind = [];
    foreach ( $packageInfo as $key => $info ) {
      $byKind[ $this->kindKey( $key ) ] = $byKind[ $this->kindKey( $key ) ] ?? $info;
    }

    return $byKind;
  }


  private function buildTranslatablePackagesCondition( array $slugs ): string {
    $translatablePackages = array_map(
      function ( string $packageName ): string {
        return $this->queryPrepare->prepare( '%s', $packageName );
      },
      $slugs
    );

    return implode( ',', $translatablePackages );
  }


}
