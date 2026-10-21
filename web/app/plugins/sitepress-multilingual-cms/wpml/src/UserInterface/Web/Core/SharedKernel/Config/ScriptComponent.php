<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Config;

use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\Endpoint;


class ScriptComponent {

  private static $loaded = [];

  private $id;

  private $dependencies = [];

  private $dataProvider;

  private $scriptVarName;

  private $scriptData = [];

  private $endpoints = [];


  public function __construct( string $id ) {
    $this->id = $id;
  }


  public function id(): string {
    return $this->id;
  }


  public function idCamelCase(): string {
    return lcfirst( str_replace( '-', '', ucwords( $this->id, '-' ) ) ) ?: $this->id;
  }


  public function dependencies(): array {
    return $this->dependencies;
  }


  public function setDependencies( $dependencies ) {
    $this->dependencies = $dependencies;
    return $this;
  }


  public function dataProvider() {
    return $this->dataProvider ?? null;
  }


  public function setDataProvider( $dataProvider ) {
    $this->dataProvider = $dataProvider;
    return $this;
  }


  public function scriptVarName() {
    return $this->scriptVarName ?? $this->id;
  }


  public function setScriptVarName( string $scriptVarName ): self {
    $this->scriptVarName = $scriptVarName;
    return $this;
  }


  public function scriptData(): array {
    return $this->scriptData;
  }


  public function setScriptData( array $scriptData ): self {
    $this->scriptData = $scriptData;
    return $this;
  }


  public function loaded( $id ): void {
    self::$loaded[$id] = true;
  }


  public function isLoaded(): bool {
    return array_key_exists( $this->id, self::$loaded );
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


}
