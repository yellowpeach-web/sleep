<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\SiteLock;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\SiteLock\SiteLockDetectedEventListener;

class SiteLockDetectedEvent {

  use PluginFilesMatchLoadedVersionGate;

  const EVENT_NAME = 'wpml_site_lock_detected';

  private $dic;

  private $siteLockDetectedEventListener;


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
        $this->getSiteLockDetectedListener()->doActions();
      }
    );
  }


  private function getSiteLockDetectedListener(): SiteLockDetectedEventListener {
    if ( $this->siteLockDetectedEventListener === null ) {
      $this->siteLockDetectedEventListener = $this->dic->make( SiteLockDetectedEventListener::class );
    }

    return $this->siteLockDetectedEventListener;
  }


}
