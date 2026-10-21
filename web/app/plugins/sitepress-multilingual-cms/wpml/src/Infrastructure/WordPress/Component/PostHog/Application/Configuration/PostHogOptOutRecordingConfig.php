<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Application\Configuration;

use WPML\Core\Component\PostHog\Application\Configuration\PostHogOptOutRecordingConfigInterface;

class PostHogOptOutRecordingConfig implements PostHogOptOutRecordingConfigInterface {

  const DEFAULT_DASHBOARD_SESSION_LIMIT = 3;
  const DEFAULT_WINDOW_DAYS             = 30;
  const DEFAULT_EARLY_STOP_ON_ENABLE    = true;

  const CONST_DASHBOARD_SESSION_LIMIT = 'WPML_POSTHOG_OPTOUT_DASHBOARD_SESSION_LIMIT';
  const CONST_WINDOW_DAYS             = 'WPML_POSTHOG_OPTOUT_WINDOW_DAYS';
  const CONST_EARLY_STOP_ON_ENABLE    = 'WPML_POSTHOG_OPTOUT_EARLY_STOP_ON_ENABLE';

  const FILTER_DASHBOARD_SESSION_LIMIT = 'wpml_posthog_optout_dashboard_session_limit';
  const FILTER_WINDOW_DAYS             = 'wpml_posthog_optout_window_days';
  const FILTER_EARLY_STOP_ON_ENABLE    = 'wpml_posthog_optout_early_stop_on_enable';


  public function getDashboardSessionLimit(): int {
    return $this->toNonNegativeInt(
      $this->resolve(
        self::CONST_DASHBOARD_SESSION_LIMIT,
        self::FILTER_DASHBOARD_SESSION_LIMIT,
        self::DEFAULT_DASHBOARD_SESSION_LIMIT
      ),
      self::DEFAULT_DASHBOARD_SESSION_LIMIT
    );
  }


  public function getWindowDays(): int {
    return $this->toNonNegativeInt(
      $this->resolve(
        self::CONST_WINDOW_DAYS,
        self::FILTER_WINDOW_DAYS,
        self::DEFAULT_WINDOW_DAYS
      ),
      self::DEFAULT_WINDOW_DAYS
    );
  }


  public function isEarlyStopOnEnable(): bool {
    return (bool) $this->resolve(
      self::CONST_EARLY_STOP_ON_ENABLE,
      self::FILTER_EARLY_STOP_ON_ENABLE,
      self::DEFAULT_EARLY_STOP_ON_ENABLE
    );
  }


  private function resolve( string $constName, string $filterName, $default ) {
    $value = $default;

    try {
      if ( defined( $constName ) ) {
        $value = constant( $constName );
      }
    } catch ( \Throwable $e ) {
      $value = $default;
    }

    return apply_filters( $filterName, $value );
  }


  private function toNonNegativeInt( $value, int $default ): int {
    if ( ! is_numeric( $value ) ) {
      return $default;
    }

    return max( 0, (int) $value );
  }


}
