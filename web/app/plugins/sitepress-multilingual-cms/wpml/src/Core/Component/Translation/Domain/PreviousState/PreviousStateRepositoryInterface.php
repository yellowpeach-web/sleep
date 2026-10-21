<?php

namespace WPML\Core\Component\Translation\Domain\PreviousState;

use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\PHP\Exception\InvalidItemIdException;

interface PreviousStateRepositoryInterface {


  public function update( int $translationId, ?PreviousState $previousState = null );


  public function resetStatus( int $translationId, int $status = TranslationStatus::NOT_TRANSLATED );


  public function restoreState( int $translationId, PreviousState $previousState );


  public function resetStatusesWithoutPreviousState(
    array $rids,
    array $fromStatuses,
    int $statusWithElement,
    int $statusWithoutElement
  ): int;


}
