<?php

namespace WPML\Core\Security\ExecutionContext;

final class CronProof extends TrustProof {

  public static function provenance(): string {
    return Provenance::CRON;
  }

  public static function forScheduledRun( string $runner ): self {
    return new self( self::minter(), $runner );
  }
}
