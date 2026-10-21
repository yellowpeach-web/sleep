<?php

namespace WPML\Core\Component\Cdt\Application\Service;

use WPML\Core\Component\Cdt\Application\Query\HealthStatementsQueryInterface;
use WPML\Core\Component\Cdt\Application\Repository\CdtStoreInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;

class AutoTranslationStamps {

  const EDITOR_ATE = 'ate';

  private $store;

  private $statements;


  public function __construct( CdtStoreInterface $store, HealthStatementsQueryInterface $statements ) {
    $this->store      = $store;
    $this->statements = $statements;
  }


  public function onJobCompleted( bool $automatic, string $editor, int $now ): bool {
    if ( ! $automatic || strtolower( $editor ) !== self::EDITOR_ATE ) {
      return false;
    }

    if ( $this->store->getFirstAutoTranslationAt() === null ) {
      $this->store->saveFirstAutoTranslationAt( $now );
    }
    $this->store->saveLastAutoTranslationAt( $now );

    return true;
  }


  public function backfill(): void {
    $range = $this->statements->getAutoTranslationRange();

    if ( $range['first'] !== null && $this->store->getFirstAutoTranslationAt() === null ) {
      $this->store->saveFirstAutoTranslationAt( $range['first'] );
    }
    if ( $range['last'] !== null && $this->store->getLastAutoTranslationAt() === null ) {
      $this->store->saveLastAutoTranslationAt( $range['last'] );
    }
  }


}
