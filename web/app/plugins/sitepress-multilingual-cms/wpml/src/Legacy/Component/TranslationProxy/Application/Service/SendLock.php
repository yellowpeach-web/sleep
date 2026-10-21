<?php

namespace WPML\Legacy\Component\TranslationProxy\Application\Service;

class SendLock {

  const LOCK_NAME = 'tp_send';

  const RELEASE_TIMEOUT_SECONDS = 120;

  private $keyedLock;

  private $lockKey = false;


  public function acquire() {
    $key = $this->keyedLock()->create( null, self::RELEASE_TIMEOUT_SECONDS );

    $this->lockKey = is_string( $key ) ? $key : false;

    return false !== $this->lockKey;
  }


  public function release() {
    if ( ! $this->lockKey ) {
      return;
    }

    $key           = $this->lockKey;
    $this->lockKey = false;

    if ( $this->currentOwnerKey() !== $key ) {
      return;
    }

    $this->keyedLock()->release();
  }


  private function currentOwnerKey() {
    $wpdb = $GLOBALS['wpdb'];

    $key = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
        'wpml.' . self::LOCK_NAME . '.lock.key'
      )
    );

    return $key;
  }


  private function keyedLock() {
    if ( ! $this->keyedLock ) {
      $this->keyedLock = new \WPML\Utilities\KeyedLock( $GLOBALS['wpdb'], self::LOCK_NAME );
    }

    return $this->keyedLock;
  }


}
