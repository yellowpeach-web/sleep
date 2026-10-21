<?php

namespace WPML\Infrastructure\WordPress\Component\Item\Application\Query;

use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\Core\SharedKernel\Component\Item\Application\Query\Dto\UntranslatedTypeCountDto;
use WPML\Core\SharedKernel\Component\Item\Application\Query\UntranslatedTypesCountQueryInterface;
use WPML\Core\SharedKernel\Component\Language\Application\Query\Dto\LanguageDto;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\Core\SharedKernel\Component\Post\Application\Query\Dto\PostTypeDto;
use WPML\Core\SharedKernel\Component\Post\Application\Query\TranslatableTypesQueryInterface;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslationEditorPreference;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class UntranslatedTypesCountQuery implements UntranslatedTypesCountQueryInterface {

  const IGNORED_TYPES = [ 'attachment', 'wp_template_part', 'wp_block' ];

  const IGNORED_IF_EMPTY = [ 'wp_template', 'wp_navigation' ];

  private $queryHandler;

  private $queryPrepare;

  private $translatableTypesQuery;

  private $languagesQuery;

  private $heldOutCondition;


  public function __construct(
    QueryHandlerInterface $queryHandler,
    QueryPrepareInterface $queryPrepare,
    TranslatableTypesQueryInterface $translatableTypesQuery,
    LanguagesQueryInterface $languagesQuery,
    HeldOutTranslationCondition $heldOutCondition
  ) {
    $this->queryHandler = $queryHandler;
    $this->queryPrepare = $queryPrepare;
    $this->translatableTypesQuery = $translatableTypesQuery;
    $this->languagesQuery = $languagesQuery;
    $this->heldOutCondition = $heldOutCondition;
  }


  public function forKind() {
    return UntranslatedTypesCountQueryInterface::KIND_POST;
  }


  public function get( array $queryData = [] ) : array {
    $translatableTypes = $this->translatableTypesQuery->getTranslatable();

    $extraPostTypes = $queryData['extraPostTypes'] ?? [];
    if ( $extraPostTypes ) {
      $known = array_map(
        function ( $dto ) {
          return $dto->getId();
        },
        $translatableTypes
      );
      foreach ( $extraPostTypes as $slug ) {
        if ( $slug === '' || in_array( $slug, $known, true ) ) {
          continue;
        }
        $postTypeObject = get_post_type_object( $slug );
        if ( ! $postTypeObject ) {
          continue;
        }
        $translatableTypes[] = new PostTypeDto(
          $slug,
          $postTypeObject->label ?: $slug,
          (string) ( isset( $postTypeObject->labels->singular_name ) ? $postTypeObject->labels->singular_name : $slug ),
          (string) ( isset( $postTypeObject->labels->name ) ? $postTypeObject->labels->name : $slug ),
          $postTypeObject->hierarchical,
          $postTypeObject->public,
          $postTypeObject->show_ui
        );
      }
    }

    $unregisteredLabels = $queryData['unregisteredPostTypeLabels'] ?? [];
    if ( $unregisteredLabels ) {
      $known = array_map(
        function ( $dto ) {
          return $dto->getId();
        },
        $translatableTypes
      );
      foreach ( $unregisteredLabels as $slug => $label ) {
        if ( $slug === '' || $label === '' || in_array( $slug, $known, true ) || get_post_type_object( $slug ) ) {
          continue;
        }
        $translatableTypes[] = new PostTypeDto( $slug, $label, $label, $label, false, false, false );
      }
    }

    $nativeEditorGlobalSetting = $queryData['nativeEditorGlobalSetting'] ?? false;
    $nativeEditorSettingPerType = $queryData['nativeEditorSettingPerType'] ?? [];

    $typesWithoutAttachmentType = array_filter(
      $translatableTypes,
      function ( $postTypeDto ) {
        return ! in_array( $postTypeDto->getId(), self::IGNORED_TYPES, true );
      }
    );

    $postTypes = array_map(
      function ( $postTypesDto ) {
        return 'post_' . $postTypesDto->getId();
      },
      $typesWithoutAttachmentType
    );

    $typesIn = $this->queryPrepare->prepareIn( $postTypes );

    $statusesIn = $this->getStatusesIn();

    $translatableCodes = $this->translatableSecondaryCodes();
    $languagesIn       = $this->queryPrepare->prepareIn( $translatableCodes );

    $postMetaKeyEditor    = TranslationEditorPreference::POST_META_KEY_EDITOR;
    $postMetaKeyUseNative = TranslationEditorPreference::POST_META_KEY_USE_NATIVE;
    $postMetaKeyUseWpml   = TranslationEditorPreference::POST_META_KEY_USE_WPML;

    $translationsTable = $this->queryPrepare->prefix() . 'icl_translations';
    $heldOutInSubquery = $this->heldOutCondition->sql( 'icl_translations_status' );
    $heldOutIsDone     = $heldOutInSubquery === '' ? '' : "OR ( $heldOutInSubquery )";

    $heldOutPerDocument = $this->heldOutCondition->existsSql( $translationsTable . '.trid' );
    $heldOutFlag        = $heldOutPerDocument === '' ? '0' : "( $heldOutPerDocument )";

    $sql = "
    SELECT translations.post_type,
            COALESCE(SUM(translations.is_untranslated), 0) count,
            COALESCE(SUM(translations.has_held_out), 0) heldOutCount
      FROM (
            SELECT RIGHT(element_type, LENGTH(element_type) - 5) as post_type, posts.ID,
                   (
                     SELECT COUNT(trid)
                     FROM {$this->queryPrepare->prefix()}icl_translations icl_translations_inner
                     INNER JOIN {$this->queryPrepare->prefix()}icl_translation_status icl_translations_status
                     ON icl_translations_inner.translation_id = icl_translations_status.translation_id
                     WHERE icl_translations_inner.trid = {$translationsTable}.trid
                       AND icl_translations_inner.language_code IN ({$languagesIn})
                       AND icl_translations_status.status NOT IN ({$statusesIn})
                       AND ( icl_translations_status.needs_update != 1 {$heldOutIsDone} )
                   ) < %d as is_untranslated,
                   {$heldOutFlag} as has_held_out
            FROM {$this->queryPrepare->prefix()}icl_translations
            INNER JOIN {$this->queryPrepare->prefix()}posts posts ON element_id = ID

            LEFT JOIN {$this->queryPrepare->prefix()}postmeta postmeta_editor
              ON postmeta_editor.post_id = posts.ID
              AND postmeta_editor.meta_key = '$postMetaKeyEditor'
            LEFT JOIN {$this->queryPrepare->prefix()}postmeta postmeta
              ON postmeta.post_id = posts.ID
              AND postmeta.meta_key = '$postMetaKeyUseNative'
            LEFT JOIN {$this->queryPrepare->prefix()}postmeta postmeta_wpml
              ON postmeta_wpml.post_id = posts.ID
              AND postmeta_wpml.meta_key = '$postMetaKeyUseWpml'

            WHERE element_type IN ($typesIn)
              AND post_status = 'publish'
              AND source_language_code IS NULL
              AND {$this->getPostMetaConditions( $nativeEditorGlobalSetting, $nativeEditorSettingPerType )}
         ) as translations
      WHERE translations.is_untranslated = 1 OR translations.has_held_out = 1
      GROUP BY translations.post_type;
    ";

    try {
      if ( $translatableCodes && $postTypes ) {
        $untranslatedTypesCount = $this->queryHandler->query(
          $this->queryPrepare->prepare( $sql, count( $translatableCodes ) )
        )->getResults();
      } else {
        $untranslatedTypesCount = [];
      }

      foreach ( $typesWithoutAttachmentType as $postTypeDto ) {
        $postTypeShort = $postTypeDto->getId();
        if (
          in_array( $postTypeShort, array_column( $untranslatedTypesCount, 'post_type' ), true )
          || in_array( $postTypeShort, self::IGNORED_IF_EMPTY, true )
        ) {
          continue;
        }

        $untranslatedTypesCount[] = [
          'post_type' => $postTypeShort,
          'count' => 0,
          'heldOutCount' => 0,
        ];
      }
    } catch ( DatabaseErrorException $e ) {
      $untranslatedTypesCount = [];
    }

    return $this->mapTypesToTypeWithCountDto( $untranslatedTypesCount, $translatableTypes );
  }


  public function getSomeIds( $numberOfIdsToFetch, $offset, $type ) {
    $statusesIn = $this->getStatusesIn();

    $translatableCodes = $this->translatableSecondaryCodes();
    if ( ! $translatableCodes ) {
      return [];
    }
    $languagesIn = $this->queryPrepare->prepareIn( $translatableCodes );

    $postMetaKeyEditor    = TranslationEditorPreference::POST_META_KEY_EDITOR;
    $postMetaKeyUseNative = TranslationEditorPreference::POST_META_KEY_USE_NATIVE;
    $postMetaKeyUseWpml   = TranslationEditorPreference::POST_META_KEY_USE_WPML;

    $heldOut       = $this->heldOutCondition->sql( 'its' );
    $heldOutIsDone = $heldOut === '' ? '' : "OR ( $heldOut )";

    $sql = "
      SELECT p.ID
      FROM {$this->queryPrepare->prefix()}icl_translations AS itr
      INNER JOIN {$this->queryPrepare->prefix()}posts AS p
              ON itr.element_id = p.ID
      LEFT JOIN {$this->queryPrepare->prefix()}postmeta AS pm_editor
             ON pm_editor.post_id = p.ID
            AND pm_editor.meta_key = '$postMetaKeyEditor'
      LEFT JOIN {$this->queryPrepare->prefix()}postmeta AS pm
             ON pm.post_id = p.ID
            AND pm.meta_key = '$postMetaKeyUseNative'
      LEFT JOIN {$this->queryPrepare->prefix()}postmeta AS pm_wpml
             ON pm_wpml.post_id = p.ID
            AND pm_wpml.meta_key = '$postMetaKeyUseWpml'
      WHERE itr.element_type = CONCAT('post_', %s)
        AND p.post_status = 'publish'
        AND itr.source_language_code IS NULL
        AND (
          SELECT COUNT(inner_itr.trid)
          FROM {$this->queryPrepare->prefix()}icl_translations AS inner_itr
          INNER JOIN {$this->queryPrepare->prefix()}icl_translation_status AS its
                  ON inner_itr.translation_id = its.translation_id
          WHERE inner_itr.trid = itr.trid
            AND inner_itr.language_code IN ({$languagesIn})
            AND its.status NOT IN ({$statusesIn})
            AND ( its.needs_update != 1 {$heldOutIsDone} )
        ) < %d
        AND COALESCE({$this->getPerPostEditorCase( 'pm_editor', 'pm', 'pm_wpml' )}, 0) = 0
      ORDER BY p.ID ASC
      LIMIT %d OFFSET %d
    ";

    try {
      $ids = $this->queryHandler->queryColumn(
        $this->queryPrepare->prepare(
          $sql,
          $type,
          count( $translatableCodes ),
          $numberOfIdsToFetch,
          $offset
        )
      );
      return $ids;
    } catch ( DatabaseErrorException $e ) {
      return [];
    }
  }


  private function translatableSecondaryCodes(): array {
    return array_values(
      array_map(
        function ( LanguageDto $language ) {
          return $language->getCode();
        },
        $this->languagesQuery->getSecondaryTranslatable()
      )
    );
  }


  private function getStatusesIn() : string {
    return implode(
      ',',
      [
        TranslationStatus::NOT_TRANSLATED,
        TranslationStatus::ATE_CANCELED
      ]
    );
  }


  private function mapTypesToTypeWithCountDto( array $untranslatedTypesCount, array $translatablePostTypesDto ): array {
    return array_map(
      function ( $typeWithCount ) use ( $translatablePostTypesDto ) {

        $postTypeDto = current(
          array_filter(
            $translatablePostTypesDto,
            function ( $dto ) use ( $typeWithCount ) {
              return $dto->getId() === $typeWithCount['post_type'];
            }
          )
        );

        return new UntranslatedTypeCountDto(
          $postTypeDto ? $postTypeDto->getPlural() : $typeWithCount['post_type'],
          $postTypeDto ? $postTypeDto->getSingular() : $typeWithCount['post_type'],
          (int) $typeWithCount['count'],
          UntranslatedTypesCountQueryInterface::KIND_POST,
          $typeWithCount['post_type'],
          (int) $typeWithCount['heldOutCount']
        );
      },
      $untranslatedTypesCount
    );
  }


  private function getPerPostEditorCase( string $consolidatedAlias, string $legacyAlias, string $legacyWpmlAlias ) : string {
    $editorNative = TranslationEditorPreference::EDITOR_NATIVE;

    return "CASE
      WHEN $consolidatedAlias.meta_value = '$editorNative' THEN 1
      WHEN $consolidatedAlias.meta_value IS NOT NULL THEN 0
      WHEN $legacyAlias.meta_value = 'yes' AND $legacyWpmlAlias.meta_value IS NULL THEN 1
      WHEN $legacyAlias.meta_value = 'no' THEN 0
      ELSE NULL
    END";
  }


  private function getPostMetaConditions(
    bool $nativeEditorGlobalSetting,
    array $nativeEditorSettingPerType
  ) : string {
    return $nativeEditorGlobalSetting
      ? $this->getGlobalNativeEditorCondition( $nativeEditorSettingPerType )
      : $this->getPerPostTypeNativeEditorCondition( $nativeEditorSettingPerType );
  }


  private function getGlobalNativeEditorCondition( array $nativeEditorSettingPerType ) : string {
    $typesNotUsingNativeEditor = $this->getPostTypes( $nativeEditorSettingPerType, false );

    $inheritFallback = $typesNotUsingNativeEditor
      ? 'CASE WHEN posts.post_type IN (' . $this->queryPrepare->prepareIn( $typesNotUsingNativeEditor ) . ') THEN 0 ELSE 1 END'
      : '1';

    return 'COALESCE(' . $this->getPerPostEditorCase( 'postmeta_editor', 'postmeta', 'postmeta_wpml' ) . ", $inheritFallback) = 0";
  }


  private function getPerPostTypeNativeEditorCondition( array $nativeEditorSettingPerType ) : string {
    $typesUsingNativeEditor = $this->getPostTypes( $nativeEditorSettingPerType, true );

    $inheritFallback = $typesUsingNativeEditor
      ? 'CASE WHEN posts.post_type NOT IN (' . $this->queryPrepare->prepareIn( $typesUsingNativeEditor ) . ') THEN 0 ELSE 1 END'
      : '0';

    return 'COALESCE(' . $this->getPerPostEditorCase( 'postmeta_editor', 'postmeta', 'postmeta_wpml' ) . ", $inheritFallback) = 0";
  }


  private function getPostTypes( array $nativeEditorSettingPerType, bool $usingWpEditor ) : array {
    return array_keys(
      array_filter(
        $nativeEditorSettingPerType,
        function ( $postTypeValue ) use ( $usingWpEditor ) {
          return $postTypeValue === $usingWpEditor;
        }
      )
    );
  }


}
