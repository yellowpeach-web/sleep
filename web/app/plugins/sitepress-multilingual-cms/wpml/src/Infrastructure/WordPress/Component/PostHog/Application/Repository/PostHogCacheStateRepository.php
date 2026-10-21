<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository;

use WPML\Core\Component\PostHog\Application\Repository\PostHogCacheStateRepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class PostHogCacheStateRepository implements PostHogCacheStateRepositoryInterface {

    const LAST_CHECKED_KEY    = 'wpml_posthog_cache_last_checked';
    const PROCESSING_LOCK_KEY = 'wpml_posthog_check_in_progress';

    private $options;

    private $wpdb;

  public function __construct(
        $wpdb,
        OptionsInterface $options
    ) {
      $this->wpdb    = $wpdb;
      $this->options = $options;
  }


  public function getLastChecked(): ?int {
      $value = $this->options->get( self::LAST_CHECKED_KEY, null );
      return $value !== null ? (int) $value : null;
  }


  public function setLastChecked( int $timestamp ) {
      $this->options->save( self::LAST_CHECKED_KEY, $timestamp );
  }


  public function isStale( int $ttlSeconds ): bool {
      $lastChecked = $this->getLastChecked();

    if ( $lastChecked === null ) {
        return true;
    }

      return time() - $lastChecked > $ttlSeconds;
  }


  public function acquireProcessingLock(): bool {
      $wpdb    = $this->wpdb;
      $lockKey = self::PROCESSING_LOCK_KEY;

      if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/D', $wpdb->options ) ) {
          return false;
      }

      $result = $wpdb->query(
        $wpdb->prepare(
          "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
		       VALUES (%s, %s, 'off')
		       ON DUPLICATE KEY UPDATE option_value = option_value",
          $lockKey,
          '1'
        )
      );

      return $result === 1;
  }


  public function releaseProcessingLock() {
      $this->options->delete( self::PROCESSING_LOCK_KEY );
  }


}
