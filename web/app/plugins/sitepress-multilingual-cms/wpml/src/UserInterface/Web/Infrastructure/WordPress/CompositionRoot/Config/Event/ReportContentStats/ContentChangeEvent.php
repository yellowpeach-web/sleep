<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\ReportContentStats;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\ReportContentStats\ContentChangeEventListener;

class ContentChangeEvent {

  use PluginFilesMatchLoadedVersionGate;

  const EVENT_NAME = 'transition_post_status';

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
      function ( $newStatus, $oldStatus, $post ) {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->getListener()->onTransitionPostStatus( $newStatus, $oldStatus, $post );
      },
      10,
      3
    );
  }


  private function getListener(): ContentChangeEventListener {
    if ( $this->listener === null ) {
      $this->listener = $this->dic->make( ContentChangeEventListener::class );
    }

    return $this->listener;
  }


}
