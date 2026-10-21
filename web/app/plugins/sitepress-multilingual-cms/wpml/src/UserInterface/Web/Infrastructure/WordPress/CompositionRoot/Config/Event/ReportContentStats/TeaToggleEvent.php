<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\ReportContentStats;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\ReportContentStats\TeaToggleEventListener;

class TeaToggleEvent {

  use PluginFilesMatchLoadedVersionGate;

  const EVENT_NAME = 'wpml_set_translate_everything';

  private $dic;

  private $listener;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  public function register() {
    add_action(
      self::EVENT_NAME,
      function ( $enabled ) {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->getListener()->doActions( (bool) $enabled );
      }
    );
  }


  private function getListener(): TeaToggleEventListener {
    if ( $this->listener === null ) {
      $this->listener = $this->dic->make( TeaToggleEventListener::class );
    }

    return $this->listener;
  }


}
