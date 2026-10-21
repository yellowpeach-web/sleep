<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\Support;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\CoreSupportTools;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\SupportToolRegistry;

class SupportToolsEvent {

  use PluginFilesMatchLoadedVersionGate;

  const FILTER = SupportToolRegistry::FILTER;

  private $dic;

  private $coreTools = null;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  public function register() {
    add_filter( self::FILTER, [ $this, 'addCoreTools' ] );
  }


  public function addCoreTools( $tools ) {
    if ( ! $this->filesMatchLoadedVersion() ) {
      return $tools;
    }
    return $this->coreTools()->register( $tools );
  }


  private function coreTools(): CoreSupportTools {
    if ( $this->coreTools === null ) {
      $this->coreTools = $this->dic->make( CoreSupportTools::class );
    }

    return $this->coreTools;
  }


}
