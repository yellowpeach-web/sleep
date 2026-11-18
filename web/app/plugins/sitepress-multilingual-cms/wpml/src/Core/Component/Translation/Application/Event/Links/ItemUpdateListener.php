<?php

namespace WPML\Core\Component\Translation\Application\Event\Links;

use WPML\Core\Component\Translation\Application\Service\Links\AdjustTranslationsOfOriginal;
use WPML\Core\Component\Translation\Domain\Links\HandleUpdateOriginal;
use WPML\Core\Component\Translation\Domain\Links\HandleUpdateTranslation;
use WPML\Core\Component\Translation\Domain\Links\Item;
use WPML\Core\Port\Event\EventListenerInterface;
use WPML\PHP\Exception\InvalidTypeException;
use function WPML\PHP\Logger\error as logError;

class ItemUpdateListener implements EventListenerInterface {

  private $handleOriginal;

  private $handleTranslation;

  private $adjustTranslationsOfOriginal;


  public function __construct(
    HandleUpdateOriginal $handleOriginal,
    HandleUpdateTranslation $handleTranslation,
    ?AdjustTranslationsOfOriginal $adjustTranslationsOfOriginal = null
  ) {
    $this->handleOriginal = $handleOriginal;
    $this->handleTranslation = $handleTranslation;
    $this->adjustTranslationsOfOriginal = $adjustTranslationsOfOriginal;
  }


  public function onItemSave( Item $item ) {
    try {
      $this->handleOriginal->handle( $item );
      try {
        $collected = $this->handleOriginal->handleOriginalOf( $item );
        $idOriginal = $item->getIdOriginal();
        if ( $collected && $idOriginal !== null ) {
          if ( $this->adjustTranslationsOfOriginal ) {
            $this->adjustTranslationsOfOriginal->adjust( $idOriginal, $collected, $item->getId() );
          } else {
            logError(
              'WPML links: the other translations of post ' . $idOriginal . ' were not adjusted'
              . ' (AdjustTranslationsOfOriginal not available, a half-updated plugin tree).'
            );
          }
        }
      } catch ( \Throwable $e ) {
        logError(
          'WPML links: collecting the links of the original of '
          . $item->getType() . ' ' . $item->getId() . ' failed: '
          . get_class( $e ) . ': ' . $e->getMessage()
        );
      }
      $this->handleTranslation->handle( $item );
    } catch ( InvalidTypeException $e ) {
      return;
    }
  }


}
