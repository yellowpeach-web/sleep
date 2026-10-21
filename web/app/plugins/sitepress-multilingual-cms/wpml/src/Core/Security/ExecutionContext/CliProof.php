<?php

namespace WPML\Core\Security\ExecutionContext;

final class CliProof extends TrustProof {

  public static function provenance(): string {
    return Provenance::CLI;
  }

  public static function forProcess( string $command ): self {
    return new self( self::minter(), $command );
  }
}
