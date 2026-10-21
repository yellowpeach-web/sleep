<?php

namespace WPML\Core\Component\LanguageEditor\Application;

final class Result {

    private $ok;

    private $payload;


  private function __construct( bool $ok, array $payload ) {
      $this->ok      = $ok;
      $this->payload = $payload;
  }


  public static function ok( array $payload ): self {
      return new self( true, $payload );
  }


  public static function error( string $error, array $extra = [] ): self {
      return new self( false, array_merge( [ 'error' => $error ], $extra ) );
  }


  public function isOk(): bool {
      return $this->ok;
  }


  public function payload(): array {
      return $this->payload;
  }


}
