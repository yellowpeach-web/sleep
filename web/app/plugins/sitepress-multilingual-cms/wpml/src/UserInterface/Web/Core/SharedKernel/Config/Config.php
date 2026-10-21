<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Config;

use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\Endpoint;

class Config {

  private $adminPages = [];

  private $adminNotices = [];

  private $endpoints = [];

  private $scripts = [];

  private $scriptComponents = [];

  private $styles = [];


  public function adminPages() {
    return $this->adminPages;
  }


  public function addAdminPage( Page $page ) {
    $this->adminPages[] = $page;
  }


  public function adminNotices() {
    return $this->adminNotices;
  }


  public function addAdminNotice( Notice $notice ) {
    $this->adminNotices[] = $notice;
  }


  public function endpoints() {
    return $this->endpoints;

  }


  public function addEndpoint( Endpoint $endpoint ) {
    $this->endpoints[] = $endpoint;
    return $this;
  }


  public function setEndpoints( $endpoints ) {
    $this->endpoints = $endpoints;
    return $this;
  }


  public function scripts() {
    return $this->scripts;
  }


  public function addScript( Script $script ) {
    $this->scripts[] = $script;
    return $this;
  }


  public function scriptComponents( $filter = [] ) {
    if ( empty( $filter ) ) {
      return $this->scriptComponents;
    }

    $filteredScriptComponents = [];
    foreach ( $this->scriptComponents as $scriptComponent ) {
      if ( in_array( $scriptComponent->id(), $filter, true ) ) {
        $filteredScriptComponents[] = $scriptComponent;
      }
    }

    return $filteredScriptComponents;
  }


  public function addScriptComponent( ScriptComponent $scriptComponent ) {
    $this->scriptComponents[$scriptComponent->id()] = $scriptComponent;
    return $this;
  }


  public function styles() {
    return $this->styles;
  }


  public function addStyle( Style $style ) {
    $this->styles[] = $style;
    return $this;
  }


}
