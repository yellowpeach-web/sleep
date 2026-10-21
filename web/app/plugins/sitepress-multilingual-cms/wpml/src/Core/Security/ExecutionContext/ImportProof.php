<?php

namespace WPML\Core\Security\ExecutionContext;

final class ImportProof extends TrustProof {

  public static function provenance(): string {
    return Provenance::IMPORT;
  }

  public static function forLifecycle( string $importer ): self {
    return new self( self::minter(), $importer );
  }
}
