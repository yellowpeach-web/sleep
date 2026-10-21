<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository;

use WPML\Core\Component\PostHog\Application\Repository\PostHogDefaultRequestSentRepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class PostHogDefaultRequestSentRepository implements PostHogDefaultRequestSentRepositoryInterface {

  const OPTION_KEY = 'wpml_posthog_default_request_sent';

  private $options;

  private $wpdb;

  public function __construct(
    $wpdb,
    OptionsInterface $options
  ) {
    $this->wpdb    = $wpdb;
    $this->options = $options;
  }


  public function isSent(): bool {
    return boolval(
      $this->options->get( self::OPTION_KEY, false )
    );
  }


  public function tryAcquireLock(): bool {
    $wpdb      = $this->wpdb;
    $optionKey = self::OPTION_KEY;

    if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/D', $wpdb->options ) ) {
      return false;
    }

    $result = $wpdb->query(
      $wpdb->prepare(
        "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
         VALUES (%s, %s, 'off')
         ON DUPLICATE KEY UPDATE option_value = option_value",
        $optionKey,
        '1'
      )
    );

    return $result === 1;
  }


  public function setIsSent( bool $isSent ) {
    $this->options->save( self::OPTION_KEY, $isSent );
  }


  public function delete() {
    $this->options->delete( self::OPTION_KEY );
  }


}
