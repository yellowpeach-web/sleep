<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository;

use WPML\Core\Component\PostHog\Application\Repository\SetupWizardEventQueueLockInterface;
use WPML\Core\Port\Persistence\OptionsInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

class SetupWizardEventQueueLock implements SetupWizardEventQueueLockInterface {

  const LOCK_KEY = 'wpml_posthog_sw_queue_lock';

  private $wpdb;

  private $queryPreparer;

  private $options;


  public function __construct(
    $wpdb,
    QueryPrepareInterface $queryPreparer,
    OptionsInterface $options
  ) {
    $this->wpdb          = $wpdb;
    $this->queryPreparer = $queryPreparer;
    $this->options       = $options;
  }


  public function acquire(): bool {
    $query = "INSERT INTO {$this->wpdb->options} (option_name, option_value, autoload)
         VALUES (%s, %s, 'off')
         ON DUPLICATE KEY UPDATE option_value = option_value";

    $sqlPrepared = $this->queryPreparer->prepare(
      $query,
      self::LOCK_KEY,
      '1'
    );

    $result = $this->wpdb->query( $sqlPrepared );

    return $result === 1;
  }


  public function release() {
    $this->options->delete( self::LOCK_KEY );
  }

}
