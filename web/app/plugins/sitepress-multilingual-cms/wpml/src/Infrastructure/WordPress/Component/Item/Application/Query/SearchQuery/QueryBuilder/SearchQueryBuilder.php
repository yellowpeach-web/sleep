<?php

namespace WPML\Infrastructure\WordPress\Component\Item\Application\Query\SearchQuery\QueryBuilder;

use WPML\Core\Component\Post\Application\Query\Criteria\SearchCriteria;
use WPML\Core\Port\Appearance\AppearanceReaderInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslationEditorPreference;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslatorNote;
use WPML\Infrastructure\WordPress\Component\Item\Application\Query\HeldOutTranslationCondition;
use WPML\Infrastructure\WordPress\Component\Item\Application\Query\SearchQuery\QueryBuilder\SearchCriteriaQueryBuilder\SortingCriteriaQueryBuilder;
use WPML\TranslationPolicy\Policy\Policy;

class SearchQueryBuilder {
  use SearchQueryBuilderTrait;

  private $queryPrepare;

  private $sortingQueryBuilder;

  private $heldOutCondition;

  private $appearanceReader;


  public function __construct(
    QueryPrepareInterface $queryPrepare,
    SortingCriteriaQueryBuilder $sortingQueryBuilder,
    HeldOutTranslationCondition $heldOutCondition,
    AppearanceReaderInterface $appearanceReader
  ) {
    $this->queryPrepare        = $queryPrepare;
    $this->sortingQueryBuilder = $sortingQueryBuilder;
    $this->heldOutCondition    = $heldOutCondition;
    $this->appearanceReader    = $appearanceReader;
  }


  public function build( SearchCriteria $criteria ): string {
    $orderBy      = $this->sortingQueryBuilder->build( $criteria->getSortingCriteria() );
    $editor       = $this->buildMetaValue( TranslationEditorPreference::POST_META_KEY_EDITOR );
    $useNative    = $this->buildMetaValue( TranslationEditorPreference::POST_META_KEY_USE_NATIVE );
    $useWpml      = $this->buildMetaValue( TranslationEditorPreference::POST_META_KEY_USE_WPML );
    $editorNative = TranslationEditorPreference::EDITOR_NATIVE;

    $useNativeEditor = Policy::editorSql()->nativeEditorFlag(
      "CASE
          WHEN {$editor} = '{$editorNative}' THEN 'yes'
          WHEN {$editor} IS NOT NULL THEN 'no'
          WHEN {$useNative} = 'yes' AND {$useWpml} IS NULL THEN 'yes'
          WHEN {$useNative} = 'no' THEN 'no'
          ELSE NULL
        END",
      'p.ID',
      'p.post_type'
    );

    return "
      SELECT
        p.ID,
        p.post_title,
        p.post_status,
        p.post_date,
        p.post_type,
        {$this->buildMetaValue( TranslatorNote::POST_META_KEY )} AS translator_note,
        {$useNativeEditor} AS use_native_editor
      FROM (
        SELECT p.ID, p.post_title, p.post_status, p.post_date, p.post_type
        {$this->buildFromAndWhere( $criteria )}
        {$orderBy}
        {$this->queryPrepare->prepare( 'LIMIT %d OFFSET %d', $criteria->getLimit(), $criteria->getOffset() )}
      ) p
      {$orderBy}
    ";
  }


  public function buildCount( SearchCriteria $criteria ): string {
    return "SELECT COUNT(*) {$this->buildFromAndWhere( $criteria )}";
  }


  private function buildFromAndWhere( SearchCriteria $criteria ): string {
    return "
      {$this->buildPostsWithSourceTranslation( $criteria->getSourceLanguageCode() )}
      WHERE p.post_type = {$this->queryPrepare->prepare( '%s', $criteria->getType() )}
        {$this->buildPostStatusCondition( $criteria->getPublicationStatus() )}
        {$this->buildExcludedByPostMetaCondition( $criteria->getType() )}
        {$this->buildPostTitleCondition( $criteria )}
        {$this->buildTaxonomyTermCondition( $criteria )}
        {$this->buildParentCondition( $criteria )}
        {$this->buildHeldOutCondition( $criteria )}
        {$this->buildAppearedCondition( $criteria )}
        AND {$this->buildTranslationStatusCondition( $criteria )}
    ";
  }


  private function buildMetaValue( string $metaKey ): string {
    return "(
      SELECT meta.meta_value
      FROM {$this->queryPrepare->prefix()}postmeta meta
      WHERE meta.post_id = p.ID AND meta.meta_key = {$this->queryPrepare->prepare( '%s', $metaKey )}
      ORDER BY meta.meta_id
      LIMIT 1
    )";
  }


  private function buildPostTitleCondition( SearchCriteria $criteria ): string {
    $title = $criteria->getTitle();
    if ( null === $title || '' === $title ) {
      return '';
    }

    return $this->queryPrepare->prepare(
      'AND p.post_title LIKE %s ESCAPE \'|\'',
      \WPML\Utils\LikeEscape::contains( $title )
    );
  }


  private function buildTaxonomyTermCondition( SearchCriteria $criteria ): string {
    if ( ! $criteria->getTaxonomyId() || ! $criteria->getTermId() ) {
      return '';
    }

    $prefix = $this->queryPrepare->prefix();
    $where  = $this->queryPrepare->prepare(
      'WHERE tax.taxonomy = %s AND tax.term_id = %d',
      $criteria->getTaxonomyId(),
      $criteria->getTermId()
    );

    return "AND p.ID IN (
      SELECT object_id
      FROM {$prefix}term_relationships rel
      JOIN {$prefix}term_taxonomy tax ON rel.term_taxonomy_id = tax.term_taxonomy_id
      {$where}
    )";
  }


  private function buildParentCondition( SearchCriteria $criteria ): string {
    if ( ! $criteria->getParentId() ) {
      return '';
    }

    return $this->queryPrepare->prepare( 'AND p.post_parent = %d', $criteria->getParentId() );
  }


  private function buildHeldOutCondition( SearchCriteria $criteria ): string {
    if ( ! $criteria->isHeldOut() ) {
      return '';
    }

    $exists = $this->heldOutCondition->existsSql( 'source_t.trid' );

    return $exists === '' ? 'AND 1 = 0' : "AND {$exists}";
  }


  private function buildAppearedCondition( SearchCriteria $criteria ): string {
    if ( ! $criteria->isAppearedOnly() ) {
      return '';
    }

    if ( ! in_array( 'post_' . $criteria->getType(), $this->appearanceReader->heldTypes(), true ) ) {
      return '';
    }

    $appeared = $this->appearanceReader->existsSql( 'source_t.element_type', 'p.ID' );

    return $appeared === '' ? '' : "AND {$appeared}";
  }


}
