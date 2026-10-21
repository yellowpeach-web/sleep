<?php

namespace WPML\Infrastructure\WordPress\Component\Taxonomy\Application\Query;

use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\Core\SharedKernel\Component\Item\Application\Query\Dto\UntranslatedTypeCountDto;
use WPML\Core\SharedKernel\Component\Item\Application\Query\UntranslatedTypesCountQueryInterface;
use WPML\Core\SharedKernel\Component\Language\Application\Query\Dto\LanguageDto;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\Core\SharedKernel\Component\Taxonomy\Application\Query\Dto\TaxonomyDto;
use WPML\Core\SharedKernel\Component\Taxonomy\Application\Query\TranslatableTaxonomiesQueryInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class UntranslatedTypesCountQuery implements UntranslatedTypesCountQueryInterface {

  const ELEMENT_TYPE_PREFIX = 'tax_';

  private $queryHandler;

  private $queryPrepare;

  private $translatableTaxonomiesQuery;

  private $languagesQuery;


  public function __construct(
    QueryHandlerInterface $queryHandler,
    QueryPrepareInterface $queryPrepare,
    TranslatableTaxonomiesQueryInterface $translatableTaxonomiesQuery,
    LanguagesQueryInterface $languagesQuery
  ) {
    $this->queryHandler                = $queryHandler;
    $this->queryPrepare                = $queryPrepare;
    $this->translatableTaxonomiesQuery = $translatableTaxonomiesQuery;
    $this->languagesQuery              = $languagesQuery;
  }


  public function forKind() {
    return UntranslatedTypesCountQueryInterface::KIND_TAXONOMY;
  }


  public function get( array $queryData = [] ): array {
    $taxonomies = $this->translatableTaxonomiesQuery->getTranslatable();
    if ( ! $taxonomies ) {
      return [];
    }

    $counts = $this->countUntranslatedTermsPerElementType( $taxonomies );

    return array_map(
      function ( TaxonomyDto $taxonomy ) use ( $counts ) {
        $elementType = self::ELEMENT_TYPE_PREFIX . $taxonomy->getId();

        return new UntranslatedTypeCountDto(
          $taxonomy->getPlural(),
          $taxonomy->getSingular(),
          $counts[ $elementType ] ?? 0,
          UntranslatedTypesCountQueryInterface::KIND_TAXONOMY,
          $elementType
        );
      },
      $taxonomies
    );
  }


  public function getSomeIds( $numberOfIdsToFetch, $offset, $type = '' ) {
    $languageCrossJoin = $this->buildLanguageCrossJoin();
    if ( $languageCrossJoin === '' ) {
      return [];
    }

    $elementTypeCondition = $this->buildElementTypeCondition( $type );
    if ( $elementTypeCondition === '' ) {
      return [];
    }

    $sql = "
      SELECT DISTINCT original.element_id
      FROM {$this->queryPrepare->prefix()}icl_translations original
      {$languageCrossJoin}
      LEFT JOIN {$this->queryPrepare->prefix()}icl_translations translations
        ON translations.trid = original.trid
        AND translations.language_code = langs.code
      LEFT JOIN {$this->queryPrepare->prefix()}icl_translation_status translation_status
        ON translation_status.translation_id = translations.translation_id
      WHERE {$elementTypeCondition}
        AND original.source_language_code IS NULL
        AND original.language_code = %s
        AND langs.code <> original.language_code
        AND {$this->buildNeedsTranslationCondition()}
      ORDER BY original.element_id ASC
      LIMIT %d OFFSET %d
    ";

    try {
      $ids = $this->queryHandler->queryColumn(
        $this->queryPrepare->prepare(
          $sql,
          $this->languagesQuery->getDefaultCode(),
          $numberOfIdsToFetch,
          $offset
        )
      );

      return array_map( 'intval', $ids );
    } catch ( DatabaseErrorException $e ) {
      return [];
    }
  }


  private function buildElementTypeCondition( string $type ): string {
    if ( $type === '' || $type === UntranslatedTypesCountQueryInterface::KIND_TAXONOMY ) {
      $taxonomies = $this->translatableTaxonomiesQuery->getTranslatable();
      if ( ! $taxonomies ) {
        return '';
      }

      return 'original.element_type IN (' . $this->queryPrepare->prepareIn( $this->elementTypesOf( $taxonomies ) ) . ')';
    }

    $elementType = strpos( $type, self::ELEMENT_TYPE_PREFIX ) === 0
      ? $type
      : self::ELEMENT_TYPE_PREFIX . $type;

    return $this->queryPrepare->prepare( 'original.element_type = %s', $elementType );
  }


  private function elementTypesOf( array $taxonomies ): array {
    return array_map(
      function ( TaxonomyDto $taxonomy ) {
        return self::ELEMENT_TYPE_PREFIX . $taxonomy->getId();
      },
      $taxonomies
    );
  }


  private function countUntranslatedTermsPerElementType( array $taxonomies ): array {
    $languageCrossJoin = $this->buildLanguageCrossJoin();
    if ( $languageCrossJoin === '' ) {
      return [];
    }

    $elementTypesIn = $this->queryPrepare->prepareIn( $this->elementTypesOf( $taxonomies ) );

    $sql = "
      SELECT
        original.element_type,
        COUNT( DISTINCT original.element_id ) AS count
      FROM {$this->queryPrepare->prefix()}icl_translations original
      {$languageCrossJoin}
      LEFT JOIN {$this->queryPrepare->prefix()}icl_translations translations
        ON translations.trid = original.trid
        AND translations.language_code = langs.code
      LEFT JOIN {$this->queryPrepare->prefix()}icl_translation_status translation_status
        ON translation_status.translation_id = translations.translation_id
      WHERE original.element_type IN ({$elementTypesIn})
        AND original.source_language_code IS NULL
        AND original.language_code = %s
        AND langs.code <> original.language_code
        AND {$this->buildNeedsTranslationCondition()}
      GROUP BY original.element_type
    ";

    $sql = $this->queryPrepare->prepare( $sql, $this->languagesQuery->getDefaultCode() );

    try {
      $rows = $this->queryHandler->query( $sql )->getResults();
    } catch ( DatabaseErrorException $e ) {
      return [];
    }

    $counts = [];
    foreach ( $rows as $row ) {
      $counts[ $row['element_type'] ] = $row['count'];
    }

    return $counts;
  }


  private function buildNeedsTranslationCondition(): string {
    $statusesIn = implode(
      ',',
      [
        TranslationStatus::NOT_TRANSLATED,
        TranslationStatus::ATE_CANCELED,
      ]
    );

    return "(
          translations.translation_id IS NULL
          OR translation_status.status IN ({$statusesIn})
          OR translation_status.needs_update = 1
        )";
  }


  private function buildLanguageCrossJoin(): string {
    $languageSelect = array_map(
      function ( LanguageDto $language ) {
        return $this->queryPrepare->prepare( 'SELECT %s AS code', $language->getCode() );
      },
      $this->languagesQuery->getSecondaryTranslatable()
    );

    if ( ! $languageSelect ) {
      return '';
    }

    return '
      CROSS JOIN (
        ' . implode( ' UNION ALL ', $languageSelect ) . '
      ) AS langs
    ';
  }


}
