<?php

namespace WPML\Core\Component\Translation\Application\Repository;

use WPML\Core\Component\Translation\Domain\PendingTranslationGroup;

interface PendingTranslationGroupRepositoryInterface {


  public function remember( int $elementId, PendingTranslationGroup $group ): void;


  public function find( int $elementId ): ?PendingTranslationGroup;


  public function forget( int $elementId ): void;


}
