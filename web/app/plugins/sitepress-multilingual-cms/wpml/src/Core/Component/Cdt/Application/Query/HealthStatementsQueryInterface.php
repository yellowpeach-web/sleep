<?php

namespace WPML\Core\Component\Cdt\Application\Query;

use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;

interface HealthStatementsQueryInterface {


  public function getNewestError(): array;


  public function getStuckAndDeliveries( int $since ): array;


  public function getLastManualTranslationAt(): ?int;


  public function getAutoTranslationRange(): array;


}
