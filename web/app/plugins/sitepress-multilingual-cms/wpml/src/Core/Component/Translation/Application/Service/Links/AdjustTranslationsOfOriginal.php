<?php

namespace WPML\Core\Component\Translation\Application\Service\Links;

use WPML\Core\Component\Translation\Application\Update\Database\Links\LinksBackfillQueryInterface;
use WPML\Core\Component\Translation\Domain\Links\AdjustLinksInterface;
use WPML\Core\Component\Translation\Domain\Links\Item;
use WPML\Core\Component\Translation\Domain\Links\RepositoryInterface;

class AdjustTranslationsOfOriginal {
  const TYPE_POST = 'post';

  private $query;

  private $repository;

  private $adjustLinks;


  public function __construct(
    LinksBackfillQueryInterface $query,
    RepositoryInterface $repository,
    AdjustLinksInterface $adjustLinks
  ) {
    $this->query       = $query;
    $this->repository  = $repository;
    $this->adjustLinks = $adjustLinks;
  }


  public function adjust( int $originalId, array $linkedItems, ?int $exceptTranslationId = null ): int {
    if ( ! $linkedItems ) {
      return 0;
    }

    $adjusted = 0;
    foreach ( $this->query->getPostTranslationIds( $originalId ) as $languageCode => $translationId ) {
      if ( $translationId === $exceptTranslationId ) {
        continue;
      }

      if ( ! $this->linksToATranslationIn( $linkedItems, $languageCode ) ) {
        continue;
      }

      $translation = $this->repository->get( $translationId, self::TYPE_POST );
      if ( $translation->isDeleted() || ! $translation->isPublishable() ) {
        continue;
      }

      $this->adjustLinks->adjust( $translation );
      $adjusted++;
    }

    return $adjusted;
  }


  private function linksToATranslationIn( array $linkedItems, string $languageCode ) {
    foreach ( $linkedItems as $linkedItem ) {
      if ( $this->query->hasTranslation( $linkedItem->getId(), $linkedItem->getType(), $languageCode ) ) {
        return true;
      }
    }

    return false;
  }


}
