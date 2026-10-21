<?php

namespace WPML\Core\Component\Translation\Application\Service\Taxonomy;

final class TaxonomyTermDispatchResult {

  private $acceptedMappings;

  private $blockedMappings;

  private $rejectedJobIds;

  private $remainingJobIds;

  private $uncertainJobIds;

  private $errorCode;

  private $errorMessage;


  public function __construct(
    array $acceptedMappings,
    array $blockedMappings,
    array $rejectedJobIds,
    ?string $errorCode = null,
    ?string $errorMessage = null,
    array $remainingJobIds = [],
    array $uncertainJobIds = []
  ) {
    $this->acceptedMappings = $acceptedMappings;
    $this->blockedMappings  = $blockedMappings;
    $this->rejectedJobIds   = array_values( array_unique( array_map( 'intval', $rejectedJobIds ) ) );
    $this->remainingJobIds  = array_values( array_unique( array_map( 'intval', $remainingJobIds ) ) );
    $this->uncertainJobIds  = array_values( array_unique( array_map( 'intval', $uncertainJobIds ) ) );
    $this->errorCode        = $errorCode;
    $this->errorMessage     = $errorMessage;
  }


  public function getAcceptedMappings(): array {
    return $this->acceptedMappings;
  }


  public function getAcceptedJobIds(): array {
    return array_map( 'intval', array_keys( $this->acceptedMappings ) );
  }


  public function getBlockedMappings(): array {
    return $this->blockedMappings;
  }


  public function getBlockedJobIds(): array {
    return array_map( 'intval', array_keys( $this->blockedMappings ) );
  }


  public function getRejectedJobIds(): array {
    return $this->rejectedJobIds;
  }


  public function getRemainingJobIds(): array {
    return $this->remainingJobIds;
  }


  public function getUncertainJobIds(): array {
    return $this->uncertainJobIds;
  }


  public function getAcceptedCount(): int {
    return count( $this->acceptedMappings );
  }


  public function getErrorCode(): ?string {
    return $this->errorCode;
  }


  public function getErrorMessage(): ?string {
    return $this->errorMessage;
  }


  public function hasFailure(): bool {
    return null !== $this->errorCode
      || [] !== $this->blockedMappings
      || [] !== $this->rejectedJobIds
      || [] !== $this->remainingJobIds
      || [] !== $this->uncertainJobIds;
  }

}
