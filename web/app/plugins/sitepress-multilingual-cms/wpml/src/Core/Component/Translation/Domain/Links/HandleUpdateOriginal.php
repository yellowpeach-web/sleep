<?php

namespace WPML\Core\Component\Translation\Domain\Links;

use WPML\PHP\Exception\InvalidTypeException;

class HandleUpdateOriginal {
  const TYPE_POST = 'post';

  private $collector;

  private $repository;


  public function __construct(
    CollectorInterface $collector,
    RepositoryInterface $repository
  ) {
    $this->collector = $collector;
    $this->repository = $repository;
  }


  public function handle( Item $item ) {
    if (
      ! $item->isOriginal()
      || ! $item->canLinkToOtherItems()
      || ! $item->isPublishable()
    ) {
      return;
    }

    $this->collectRelationships( $item );
  }


  public function handleOriginalOf( Item $translation ) {
    $idOriginal = $translation->getIdOriginal();

    if (
      $idOriginal === null
      || self::TYPE_POST !== $translation->getType()
      || ! $translation->isPublishable()
      || $this->repository->isCollected( new Item( $idOriginal, self::TYPE_POST ) )
    ) {
      return [];
    }

    return $this->collectIfNotCollected(
      $this->repository->get( $idOriginal, self::TYPE_POST )
    );
  }


  public function collectIfNotCollected( Item $original ) {
    if (
      self::TYPE_POST !== $original->getType()
      || ! $original->isOriginal()
      || $original->isDeleted()
      || ! $original->isPublishable()
      || $this->repository->isCollected( $original )
    ) {
      return [];
    }

    $added = [];
    if (
      $original->canLinkToOtherItems()
      && ! $this->repository->hasRelationshipsFrom( $original )
    ) {
      $added = $this->collectRelationships( $original );
    }

    $this->repository->markAsCollected( $original );

    return $added;
  }


  private function collectRelationships( Item $item ) {
    if ( $item->contentLinksUnchanged() ) {
      return [];
    }

    $itemContent = $item->getContent() ?? '';
    $itemExcerpt = $item->getExcerpt() ?? '';

    $linksNow = $this->collector->getItemsLinkedInContent(
      $itemContent . $itemExcerpt
    );

    $linksBefore = $this->repository->getToItemsByFromItem(
      $item
    );

    if ( ! $linksNow && ! $linksBefore ) {
      return [];
    }

    if ( ! $linksNow ) {
      $this->repository->deleteAllRelationshipsFrom( $item );
      return [];
    }

    $diffItems = function( Item $a, Item $b ): int {
      return $a->getId().$a->getType() <=> $b->getId().$b->getType();
    };

    $linksToDelete = array_udiff( $linksBefore, $linksNow, $diffItems );

    foreach ( $linksToDelete as $itemTo ) {
      $this->repository->deleteRelationship( $item, $itemTo );
    }

    $linksToAdd = array_udiff( $linksNow, $linksBefore, $diffItems );
    $added = [];
    foreach ( $linksToAdd as $itemTo ) {
      if ( $itemTo->isDeleted() ) {
        continue;
      }

      $this->repository->addRelationship( $item, $itemTo );
      $added[] = $itemTo;
    }

    return $added;
  }


}
