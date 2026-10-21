<?php

namespace WPML\Infrastructure\WordPress\Component\Item\Application\Query\SearchQuery\QueryBuilder;

use WPML\Core\Component\Post\Application\Query\Criteria\SearchCriteria;
use WPML\Core\Component\Post\Application\Query\Criteria\SearchPopulatedTypesCriteria;
use WPML\Core\SharedKernel\Component\Translation\Domain\ReviewStatus;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

trait SearchQueryBuilderTrait {


  private function buildPostsWithSourceTranslation( string $sourceLanguageCode ): string {
    $prefix = $this->queryPrepare->prefix();

    return "
      FROM {$prefix}posts p
      INNER JOIN {$prefix}icl_translations source_t
        ON source_t.element_id = p.ID
          AND source_t.element_type = CONCAT('post_', p.post_type)
          AND source_t.language_code = {$this->queryPrepare->prepare( '%s', $sourceLanguageCode )}
    ";
  }


  private function buildExcludedByPostMetaCondition( string $postType ): string {
    $metaKeys = apply_filters( 'wpml_tm_dashboard_exclude_posts_with_meta_keys', [], $postType );
    if ( ! is_array( $metaKeys ) ) {
      return '';
    }

    $metaKeys = array_values(
      array_unique(
        array_filter(
          $metaKeys,
          function ( $metaKey ) {
            return is_string( $metaKey ) && $metaKey !== '';
          }
        )
      )
    );
    if ( ! $metaKeys ) {
      return '';
    }

    $prefix = $this->queryPrepare->prefix();

    return "AND NOT EXISTS (
      SELECT 1
      FROM {$prefix}postmeta excluded_meta
      WHERE excluded_meta.post_id = p.ID
        AND excluded_meta.meta_key IN ({$this->queryPrepare->prepareIn( $metaKeys )})
        AND excluded_meta.meta_value NOT IN ('', '0')
    )";
  }


  private function buildPostStatusCondition( $status ): string {
    $statusQuery  = "AND p.post_status NOT IN ('auto-draft', 'trash')";
    $statusQuery .= $status ? $this->queryPrepare->prepare( ' AND p.post_status = %s', $status ) : '';

    return $statusQuery;
  }


  private function buildTranslationStatusCondition( $criteria ): string {
    $statuses        = array_map( 'intval', $criteria->getTranslationStatuses() );
    $targetLanguages = array_values( array_unique( $criteria->getTargetLanguageCodes() ) );
    if ( ! $statuses || ! $targetLanguages ) {
      return '1';
    }

    $prefix      = $this->queryPrepare->prefix();
    $inLanguages = $this->queryPrepare->prepareIn( $targetLanguages );

    $conditions = [
      "(EXISTS (
        SELECT 1
        FROM {$prefix}icl_translations t
        LEFT JOIN {$prefix}icl_translation_status ts
          ON ts.translation_id = t.translation_id
        WHERE t.trid = source_t.trid
          AND t.language_code IN ({$inLanguages})
          AND {$this->buildDisplayStatus()} IN ({$this->queryPrepare->prepareIn( $statuses, '%d' )})
      )) = 1",
    ];

    if ( in_array( TranslationStatus::NOT_TRANSLATED, $statuses, true ) ) {
      $conditions[] = "(
        SELECT COUNT(*)
        FROM {$prefix}icl_translations t
        WHERE t.trid = source_t.trid
          AND t.language_code IN ({$inLanguages})
      ) < " . count( $targetLanguages );
    }

    return '(' . implode( ' OR ', $conditions ) . ')';
  }


  private function buildDisplayStatus(): string {
    $needsReview              = TranslationStatus::NEEDS_REVIEW;
    $needsUpdate              = TranslationStatus::NEEDS_UPDATE;
    $notTranslated            = TranslationStatus::NOT_TRANSLATED;
    $complete                 = TranslationStatus::COMPLETE;
    $resolvedByTheTranslation = implode( ', ', TranslationStatus::STATUSES_RESOLVED_BY_THE_TRANSLATION );
    $known                    = implode( ', ', TranslationStatus::getAll() );
    $reviewStatus             = ReviewStatus::NEEDS_REVIEW;

    return "(CASE
      WHEN ts.review_status = '{$reviewStatus}' THEN {$needsReview}
      WHEN ts.needs_update = 1 THEN {$needsUpdate}
      WHEN COALESCE(ts.status, {$notTranslated}) IN ({$resolvedByTheTranslation})
        THEN IF(COALESCE(t.element_id, 0) = 0, {$notTranslated}, {$complete})
      WHEN ts.status IN ({$known}) THEN ts.status
      ELSE {$notTranslated}
    END)";
  }


}
