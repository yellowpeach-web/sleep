<?php

namespace WPML\Core\Component\Translation\Application\Service\Taxonomy;

use WPML\Core\Component\Translation\Application\Service\TranslationService\Dto\ResultDto;
use WPML\PHP\Exception\InvalidArgumentException;

interface TaxonomySendOperationCoordinatorInterface {

  public function prepare( array $requestData );


  public function mayContinueInRequest(
    array $requestData,
    array $settled,
    int $roundsSent,
    float $elapsedSeconds,
    float $slowestRoundSeconds
  ): bool;


  public function nextRoundRequest( array $requestData, array $settled ): array;


  public function mergeRounds( array $before, array $round ): array;


  public function settle( array $context, ResultDto $result ): array;

}
