<?php

namespace WPML\Legacy\Component\ReportContentStats\Application\Query;

use WPML\Core\Component\Cdt\Domain\TranslationMode;
use WPML\Core\Component\ReportContentStats\Application\Query\ContentStatsTranslatableTypesQueryInterface;
use WPML\Core\SharedKernel\Component\Post\Application\Query\Dto\PostTypeDto;
use WPML\Legacy\Component\Post\Application\Query\PostTypeObjectCache;
use WPML\Legacy\Component\Post\Application\Query\TranslatableTypesQuery;
use WPML\Legacy\Component\ReportContentStats\Application\Hook\ContentStatsTranslatableTypesFilter;

class ContentStatsTranslatableTypesQuery
  extends TranslatableTypesQuery
  implements ContentStatsTranslatableTypesQueryInterface {

  const LEFT_OUT = [ 'attachment' ];


  public function __construct(
    \SitePress $sitepress,
    ContentStatsTranslatableTypesFilter $filter,
    ?PostTypeObjectCache $postTypeObjects = null
  ) {
    parent::__construct( $sitepress, $filter, $postTypeObjects );
  }


  public function getTranslationModes(): array {
    $translatable        = self::ids( $this->getTranslatable() );
    $displayAsTranslated = self::ids( $this->getDisplayAsTranslated() );

    $public = array_values( get_post_types( [ 'public' => true ] ) );
    $types  = array_unique( array_merge( $public, $translatable ) );

    $modes = [];
    foreach ( $types as $postType ) {
      if ( in_array( $postType, self::LEFT_OUT, true ) ) {
        continue;
      }
      $modes[ $postType ] = TranslationMode::of( $postType, $translatable, $displayAsTranslated );
    }

    return $modes;
  }


  private static function ids( array $postTypes ): array {
    return array_map(
      function ( PostTypeDto $postType ) {
        return $postType->getId();
      },
      $postTypes
    );
  }


}
