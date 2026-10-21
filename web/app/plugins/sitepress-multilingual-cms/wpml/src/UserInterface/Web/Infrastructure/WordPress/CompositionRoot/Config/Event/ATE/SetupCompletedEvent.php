<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\ATE;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\ATE\SetupCompletedEventListener;

class SetupCompletedEvent {

  use PluginFilesMatchLoadedVersionGate;

  const EVENT_NAME = 'wpml_setup_completed';

  private $dic;

  private $isTmLoaded;

  private $listener;


  public function __construct( DicInterface $dic, ?callable $isTmLoaded = null, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->isTmLoaded              = $isTmLoaded ?: [ TranslationManagementLoaded::class, 'forRequest' ];
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  public function register() {
    add_action( self::EVENT_NAME, [ $this, 'onSetupCompleted' ] );
  }


  public function onSetupCompleted() {
    if ( ! $this->filesMatchLoadedVersion() ) {
      return;
    }
    if ( ! call_user_func( $this->isTmLoaded ) ) {
      return;
    }
    $this->getListener()->doActions();
  }


  private function getListener(): SetupCompletedEventListener {
    if ( $this->listener === null ) {
      $this->listener = $this->dic->make( SetupCompletedEventListener::class );
    }

    return $this->listener;
  }


}
