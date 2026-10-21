<?php

namespace WPML\Infrastructure;

trait PluginFilesMatchLoadedVersionGate {

  private $filesMatchLoadedVersion;


  private function filesMatchLoadedVersion(): bool {
    if ( $this->filesMatchLoadedVersion === null ) {
      $gate                          = PluginFilesMatchLoadedVersion::forSitepress();
      $this->filesMatchLoadedVersion = [ $gate, 'check' ];
    }

    return (bool) call_user_func( $this->filesMatchLoadedVersion );
  }


}
