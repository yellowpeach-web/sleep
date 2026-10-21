<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint;

class Endpoint {
  const NAMESPACE = 'wpml';

  private $id;

  private $path;

  private $method;

  private $handler;

  private $capabilities;

  private $version = 1;

  private $isAjax;

  private $args = [];


  public function __construct( string $id, string $path, bool $isAjax = false ) {
    $this->id         = $id;
    $this->path       = $path;
    $this->method     = MethodType::GET;
    $this->isAjax     = $isAjax;
  }


  public function id(): string {
    return $this->id;
  }


  public function path(): string {
    return $this->path;
  }


  public function isAjax(): bool {
    return $this->isAjax;
  }


  public function args(): array {
    return $this->args;
  }


  public function setArgs( array $args ) {
    $this->args = $args;

    return $this;
  }


  public function namespaceWithVersion(): string {
    return self::NAMESPACE . '/v' . $this->version();
  }


  public function route(): string {
    return $this->namespaceWithVersion() . $this->path();
  }


  public function method() {
    return $this->method;
  }


  public function setMethod( $method ) {
    $this->method = $method;

    return $this;
  }


  public function handler() {
    return $this->handler;
  }


  public function setHandler( $handler ) {
    $this->handler = $handler;

    return $this;
  }


  public function capability(): string {
    return $this->capabilities()[0];
  }


  public function capabilities(): array {
    return $this->capabilities ?? [ WPML_CAP_MANAGE_TRANSLATIONS ];
  }


  public function setCapability( $capability ) {
    if ( null === $capability ) {
      $this->capabilities = null;

      return $this;
    }

    $raw  = array_map( 'strval', (array) $capability );
    $list = array_values( array_filter( $raw, 'strlen' ) );

    if ( ! $list ) {
      $list = [ '' ];
    }

    if ( count( $list ) > 1 && in_array( '__return_true', $list, true ) ) {
      throw new \InvalidArgumentException(
        '__return_true cannot be one of several capabilities; a public endpoint declares it alone.'
      );
    }

    $this->capabilities = $list;

    return $this;
  }


  public function version(): int {
    return $this->version;
  }


  public function setVersion( int $version ) {
    $this->version = $version;

    return $this;
  }


}
