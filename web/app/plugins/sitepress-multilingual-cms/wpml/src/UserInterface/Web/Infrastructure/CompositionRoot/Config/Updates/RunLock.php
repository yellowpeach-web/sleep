<?php

namespace WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\Updates;

class RunLock {
  const OPTION_PREFIX = 'wpml_update_run_lock_';

  private $wpdb;


  public function __construct( $wpdb ) {
    $this->wpdb = $wpdb;
  }


  public function acquire( $update ) {
    $wpdb = $this->wpdb;
    $now  = $this->now();
    $name = $this->name( $update );

    if ( ! $this->hasPlainOptionsTableName() ) {
      return true;
    }

    $inserted = $wpdb->query(
      $wpdb->prepare(
        "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
         VALUES (%s, %s, 'off')
         ON DUPLICATE KEY UPDATE option_value = option_value",
        $name,
        (string) $now
      )
    );
    if ( $inserted === 1 ) {
      $this->readLive();
      return true;
    }

    $since = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
        $name
      )
    );
    if ( ! is_string( $since ) || (int) $since > $now - Repository::IN_PROGRESS_EXPIRY_SECONDS ) {
      return false;
    }

    $updated = $wpdb->query(
      $wpdb->prepare(
        "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
        (string) $now,
        $name,
        $since
      )
    );
    if ( $updated !== 1 ) {
      return false;
    }

    $this->readLive();
    return true;
  }


  public function release( $update ) {
    $wpdb = $this->wpdb;

    if ( ! $this->hasPlainOptionsTableName() ) {
      return;
    }

    $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name = %s",
        $this->name( $update )
      )
    );
  }


  protected function readLive() {
    if ( ! wp_using_ext_object_cache() ) {
      wp_cache_delete( 'alloptions', 'options' );
    }
  }


  protected function now() {
    return time();
  }


  private function name( $update ) {
    return self::OPTION_PREFIX . $update->id();
  }


  private function hasPlainOptionsTableName(): bool {
    return 1 === preg_match( '/^[A-Za-z0-9_]+$/D', $this->wpdb->options );
  }


}
