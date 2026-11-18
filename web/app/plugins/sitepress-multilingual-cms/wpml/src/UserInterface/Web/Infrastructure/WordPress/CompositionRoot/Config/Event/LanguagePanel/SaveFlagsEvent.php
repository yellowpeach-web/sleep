<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\LanguagePanel;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\Infrastructure\WordPress\Component\LanguagePanel\Rest\SaveFlags;
use WPML\Infrastructure\WordPress\Component\LanguagePanel\Rest\SaveFlagsArgs;

class SaveFlagsEvent {

  use PluginFilesMatchLoadedVersionGate;

  private $dic;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  public function register() {
    add_action( 'rest_api_init', [ $this, 'onRestApiInit' ] );
  }


  public function onRestApiInit() {
    if ( ! $this->filesMatchLoadedVersion() ) {
      return;
    }

    $args = $this->dic->make( SaveFlagsArgs::class );
    $args->register();

    add_filter( 'rest_dispatch_request', [ SaveFlags::class, 'onDispatch' ], 10, 4 );
    add_filter( 'rest_request_after_callbacks', [ SaveFlags::class, 'onAfterCallbacks' ], 10, 3 );
  }


}
