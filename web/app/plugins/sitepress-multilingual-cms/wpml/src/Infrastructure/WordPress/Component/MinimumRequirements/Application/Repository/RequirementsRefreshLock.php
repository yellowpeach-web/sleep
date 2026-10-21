<?php

namespace WPML\Infrastructure\WordPress\Component\MinimumRequirements\Application\Repository;

use WPML\Core\Component\MinimumRequirements\Application\Repository\RequirementsRefreshLockInterface;

class RequirementsRefreshLock implements RequirementsRefreshLockInterface {

  const LOCK_KEY = 'wpml_requirements_refresh_lock';

  const STALE_AFTER = 60;

  private $wpdb;


  public function __construct( $wpdb ) {
    $this->wpdb = $wpdb;
  }


  public function acquire(): bool {
    $wpdb = $this->wpdb;
    $now  = time();

    if ( ! $this->hasPlainOptionsTableName() ) {
      return false;
    }

    $inserted = $wpdb->query(
      $wpdb->prepare(
        "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
         VALUES (%s, %s, 'off')
         ON DUPLICATE KEY UPDATE option_value = option_value",
        self::LOCK_KEY,
        (string) $now
      )
    );
    if ( $inserted === 1 ) {
      return true;
    }

    $since = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
        self::LOCK_KEY
      )
    );
    if ( ! is_string( $since ) || (int) $since > $now - self::STALE_AFTER ) {
      return false;
    }

    $updated = $wpdb->query(
      $wpdb->prepare(
        "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
        (string) $now,
        self::LOCK_KEY,
        $since
      )
    );

    return $updated === 1;
  }


  public function release() {
    $wpdb = $this->wpdb;

    if ( ! $this->hasPlainOptionsTableName() ) {
      return;
    }

    $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name = %s",
        self::LOCK_KEY
      )
    );
  }


  private function hasPlainOptionsTableName(): bool {
    return 1 === preg_match( '/^[A-Za-z0-9_]+$/D', $this->wpdb->options );
  }


}
