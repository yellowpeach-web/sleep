<?php

namespace WPML\Core\Security\ExecutionContext;

final class Provenance {

  const REQUEST = 'request';

  const MACHINE_CALLBACK = 'machine_callback';

  const CLI = 'cli';

  const CRON = 'cron';

  const IMPORT = 'import';

  public static function all(): array {
    return [
      self::REQUEST,
      self::MACHINE_CALLBACK,
      self::CLI,
      self::CRON,
      self::IMPORT,
    ];
  }

  public static function trusted(): array {
    return [
      self::MACHINE_CALLBACK,
      self::CLI,
      self::CRON,
      self::IMPORT,
    ];
  }

  public static function isKnown( string $provenance ): bool {
    return in_array( $provenance, self::all(), true );
  }

  public static function isTrusted( string $provenance ): bool {
    return in_array( $provenance, self::trusted(), true );
  }
}
