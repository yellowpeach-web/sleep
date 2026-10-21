<?php

namespace WPML\Infrastructure\WordPress\Component\ATE\Application\Repository;

use WPML\Core\SharedKernel\Component\ATE\Application\Repository\SpendCapRepositoryInterface;
use WPML\Infrastructure\WordPress\Port\Persistence\Options;

class SpendCapRepository implements SpendCapRepositoryInterface {

  const OPTION_KEY = 'wpml_ate_spend_cap';

  private $options;


  public function __construct( Options $options ) {
    $this->options = $options;
  }


  public function get(): ?array {
    $value = $this->options->get( self::OPTION_KEY, null );

    if ( ! is_array( $value ) || count( $value ) === 0 || ! $this->holds( $value ) ) {
      return null;
    }

    return [
      'capWords'   => (int) ( $value['capWords'] ?? 0 ),
      'usedWords'  => (int) ( $value['usedWords'] ?? 0 ),
      'resetsOn'   => (string) ( $value['resetsOn'] ?? '' ),
      'reached'    => ! empty( $value['reached'] ),
      'detectedAt' => isset( $value['detectedAt'] ) && is_numeric( $value['detectedAt'] )
        ? (int) $value['detectedAt']
        : null,
    ];
  }


  private function holds( array $value ): bool {
    if ( ! empty( $value['resumedAt'] ) ) {
      return false;
    }

    $detectedAt = isset( $value['detectedAt'] ) && is_numeric( $value['detectedAt'] ) ? (int) $value['detectedAt'] : null;
    if ( $detectedAt !== null && gmdate( 'Y-m', $detectedAt ) !== gmdate( 'Y-m', $this->now() ) ) {
      return false;
    }

    $atPause  = $value['atPause'] ?? null;
    $pauseCap = is_array( $atPause ) && isset( $atPause['cap'] ) && is_numeric( $atPause['cap'] )
      ? (int) $atPause['cap']
      : null;
    $capWords = isset( $value['capWords'] ) && is_numeric( $value['capWords'] ) ? (int) $value['capWords'] : 0;

    return ! ( $pauseCap !== null && $capWords > $pauseCap );
  }


  protected function now(): int {
    return time();
  }


}
