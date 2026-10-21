<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\WpmlPosthog;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\WpmlPosthog\PostHogRecording\PostHogShouldRecordListener;

class PostHogShouldRecordEvent {

  use PluginFilesMatchLoadedVersionGate;

  const EVENT_NAME = 'check_posthog_should_record';

  private $dic;

  private $posthogShouldRecordListener;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  public function register() {
    add_action(
      self::EVENT_NAME,
      function () {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->getPosthogShouldRecordListener()->check();
      }
    );

    if ( wp_next_scheduled( self::EVENT_NAME ) ) {
      wp_clear_scheduled_hook( self::EVENT_NAME );
    }
  }


  private function getPosthogShouldRecordListener(): PostHogShouldRecordListener {
    if ( $this->posthogShouldRecordListener === null ) {
      $this->posthogShouldRecordListener = $this->dic->make( PostHogShouldRecordListener::class );
    }

    return $this->posthogShouldRecordListener;
  }


}
