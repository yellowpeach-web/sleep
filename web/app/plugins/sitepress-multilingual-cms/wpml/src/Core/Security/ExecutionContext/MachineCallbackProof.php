<?php

namespace WPML\Core\Security\ExecutionContext;

final class MachineCallbackProof extends TrustProof {

  public static function provenance(): string {
    return Provenance::MACHINE_CALLBACK;
  }

  public static function forVerifiedCallback( int $jobId, string $verifier ): self {
    return new self( self::minter(), sprintf( 'job:%d via %s', $jobId, $verifier ) );
  }
}
