<?php

namespace WPML\Core\Security\ExecutionContext;

final class ExecutionContext {

  const UNESTABLISHED = 'unestablished';

  private $provenance;

  private $principalId;

  private $authorizedBy;

  private $proof;

  private function __construct( string $provenance, int $principalId, string $authorizedBy, ?TrustProof $proof ) {
    $this->provenance   = $provenance;
    $this->principalId  = $principalId;
    $this->authorizedBy = $authorizedBy;
    $this->proof        = $proof;
  }

  public static function request( int $principalId, string $authorizedBy = self::UNESTABLISHED ): self {
    return new self( Provenance::REQUEST, max( 0, $principalId ), $authorizedBy, null );
  }

  public static function trusted( TrustProof $proof ): self {
    return new self( $proof::provenance(), 0, $proof->boundary(), $proof );
  }

  public function provenance(): string {
    return $this->provenance;
  }

  public function is( string $provenance ): bool {
    return $this->provenance === $provenance;
  }

  public function isRequest(): bool {
    return Provenance::REQUEST === $this->provenance;
  }

  public function isTrusted(): bool {
    return null !== $this->proof && Provenance::isTrusted( $this->provenance );
  }

  public function isEstablished(): bool {
    return self::UNESTABLISHED !== $this->authorizedBy;
  }

  public function principalId(): int {
    return $this->principalId;
  }

  public function authorizedBy(): string {
    return $this->authorizedBy;
  }

  public function proof(): ?TrustProof {
    return $this->proof;
  }

  public function describe(): string {
    if ( $this->proof ) {
      return $this->proof->describe();
    }

    return sprintf( '%s(principal:%d) by %s', $this->provenance, $this->principalId, $this->authorizedBy );
  }
}
