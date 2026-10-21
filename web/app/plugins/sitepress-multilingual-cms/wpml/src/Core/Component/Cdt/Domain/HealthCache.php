<?php

namespace WPML\Core\Component\Cdt\Domain;

class HealthCache {

  const MAX_AGE_SECONDS = 86400;

  const ERROR_TYPE_MAX_LENGTH = 64;

  private $computedAt;

  private $durationMs;

  private $failedJobs;

  private $lastErrorType;

  private $lastErrorAt;

  private $stuckAtAte;

  private $medianDeliveryHours30d;

  private $lastManualTranslationAt;


  public function __construct(
    int $computedAt,
    int $durationMs,
    int $failedJobs,
    ?string $lastErrorType,
    ?int $lastErrorAt,
    int $stuckAtAte,
    ?float $medianDeliveryHours30d,
    ?int $lastManualTranslationAt
  ) {
    $this->computedAt              = $computedAt;
    $this->durationMs              = max( 0, $durationMs );
    $this->failedJobs              = max( 0, $failedJobs );
    $this->lastErrorType           = $lastErrorType === null || $lastErrorType === ''
      ? null
      : substr( $lastErrorType, 0, self::ERROR_TYPE_MAX_LENGTH );
    $this->lastErrorAt             = $lastErrorAt !== null && $lastErrorAt > 0 ? $lastErrorAt : null;
    $this->stuckAtAte              = max( 0, $stuckAtAte );
    $this->medianDeliveryHours30d  = $medianDeliveryHours30d === null ? null : max( 0.0, $medianDeliveryHours30d );
    $this->lastManualTranslationAt = $lastManualTranslationAt !== null && $lastManualTranslationAt > 0 ? $lastManualTranslationAt : null;
  }


  public static function fromArray( $stored ): ?self {
    if ( ! is_array( $stored ) || ! isset( $stored['computed_at'] ) || ! is_numeric( $stored['computed_at'] ) ) {
      return null;
    }

    $computedAt = (int) $stored['computed_at'];
    if ( $computedAt <= 0 ) {
      return null;
    }

    return new self(
      $computedAt,
      self::int( $stored, 'duration_ms' ),
      self::int( $stored, 'failed_jobs' ),
      isset( $stored['last_error_type'] ) && is_scalar( $stored['last_error_type'] ) ? (string) $stored['last_error_type'] : null,
      self::intOrNull( $stored, 'last_error_at' ),
      self::int( $stored, 'stuck_at_ate' ),
      isset( $stored['median_delivery_hours_30d'] ) && is_numeric( $stored['median_delivery_hours_30d'] )
        ? (float) $stored['median_delivery_hours_30d']
        : null,
      self::intOrNull( $stored, 'last_manual_translation_at' )
    );
  }


  public function toArray(): array {
    return [
      'computed_at'                => $this->computedAt,
      'duration_ms'                => $this->durationMs,
      'failed_jobs'                => $this->failedJobs,
      'last_error_type'            => $this->lastErrorType,
      'last_error_at'              => $this->lastErrorAt,
      'stuck_at_ate'               => $this->stuckAtAte,
      'median_delivery_hours_30d'  => $this->medianDeliveryHours30d,
      'last_manual_translation_at' => $this->lastManualTranslationAt,
    ];
  }


  public function isStale( int $now ): bool {
    return $now - $this->computedAt >= self::MAX_AGE_SECONDS;
  }


  public function getComputedAt(): int {
    return $this->computedAt;
  }


  public function getDurationMs(): int {
    return $this->durationMs;
  }


  public function getFailedJobs(): int {
    return $this->failedJobs;
  }


  public function getLastErrorType(): ?string {
    return $this->lastErrorType;
  }


  public function getLastErrorAt(): ?int {
    return $this->lastErrorAt;
  }


  public function getStuckAtAte(): int {
    return $this->stuckAtAte;
  }


  public function getMedianDeliveryHours30d(): ?float {
    return $this->medianDeliveryHours30d;
  }


  public function getLastManualTranslationAt(): ?int {
    return $this->lastManualTranslationAt;
  }


  private static function int( array $stored, string $key ): int {
    return isset( $stored[ $key ] ) && is_numeric( $stored[ $key ] ) ? (int) $stored[ $key ] : 0;
  }


  private static function intOrNull( array $stored, string $key ): ?int {
    $value = self::int( $stored, $key );

    return $value > 0 ? $value : null;
  }


}
