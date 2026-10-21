<?php

namespace WPML\Core\Security\ExecutionContext;

final class TrustedBoundaries {

  public static function map(): array {
    return [
      'WPML\Security\Context\CliBoundary' => [
        'provenances' => [ Provenance::CLI ],
        'reason'      => 'Mints only inside a WP-CLI process; the process itself is the operator acting.',
      ],
      'WPML\Security\Context\CronBoundary' => [
        'provenances' => [ Provenance::CRON ],
        'reason'      => 'Mints only while WordPress runs its scheduled callbacks (wp-cron); which callbacks run is WordPress\'s own schedule, not request-chosen.',
      ],
      'WPML\TM\ATE\REST\PublicReceive' => [
        'provenances' => [ Provenance::MACHINE_CALLBACK ],
        'reason'      => 'ATE delivery callback; mints only after the job-bound HMAC token (wpmldev-7980) verified.',
      ],
      'WPML\TM\ATE\Hooks\ReturnCommand' => [
        'provenances' => [ Provenance::MACHINE_CALLBACK ],
        'reason'      => 'ATE editor return (wpmldev-8426); mints only after the job-bound return token (wpmldev-7980) verified under the command\'s policy, for the job that token names, and hands the proof to the delivery listener instead of making it ambient.',
      ],
      'WPML\Import\API\Hooks' => [
        'provenances' => [ Provenance::IMPORT ],
        'reason'      => 'WPML Import trigger endpoint; may mint only after the WPML_IMPORT_KEY secret verified (hash_equals, POST body only).',
      ],
      'WCML\Compatibility\WpAllImport\MultiCurrency' => [
        'provenances' => [ Provenance::IMPORT ],
        'reason'      => 'WP All Import lifecycle (pmxi_before_xml_import / pmxi_after_xml_import); opened by the importer itself, never by request shape.',
      ],
    ];
  }

  public static function minters(): array {
    return array_keys( self::map() );
  }

  public static function mayMint( string $minter, string $provenance ): bool {
    $map = self::map();

    return isset( $map[ $minter ] )
      && in_array( $provenance, $map[ $minter ]['provenances'], true );
  }

  public static function assertMayMint( string $minter, string $provenance ): void {
    if ( ! Provenance::isTrusted( $provenance ) ) {
      throw new ForgedContextException(
        esc_html( sprintf( '"%s" is not a trusted provenance; a proof cannot vouch for it.', $provenance ) )
      );
    }

    if ( ! self::mayMint( $minter, $provenance ) ) {
      throw new ForgedContextException(
        esc_html( sprintf(
          'Refusing to mint a trusted "%s" proof for "%s": not an allow-listed boundary (see TrustedBoundaries::map()).',
          $provenance,
          '' === $minter ? '(no class)' : $minter
        ) )
      );
    }
  }
}
