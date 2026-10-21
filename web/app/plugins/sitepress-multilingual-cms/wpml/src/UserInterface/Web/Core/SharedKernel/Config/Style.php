<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Config;

class Style implements AssetInterface {

  private $id;

  private $src;

  private $dependencies = [];

  private $onlyRegister = false;


  public function __construct( string $id ) {
    $this->id = $id;
  }


  public function id(): string {
    return $this->id;
  }


  public function src() {
    return $this->src;
  }


  public function setSrc( string $src ) {
    $this->src = $src;
    return $this;
  }


  public function dependencies(): array {
    return $this->dependencies;
  }


  public function setDependencies( $dependencies ) {
    $this->dependencies = $dependencies;
    return $this;
  }


  public function onlyRegister(): bool {
    return $this->onlyRegister;
  }


  public function setOnlyRegister( bool $onlyRegister ) {
    $this->onlyRegister = $onlyRegister;
    return $this;
  }


  public function supportsHMR(): bool {
    return true;
  }


}
