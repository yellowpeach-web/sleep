<?php

namespace WPML\Core\Component\Translation\Domain\Priority;

class ItemType {

    const POST = 'post';
    const STRING = 'string';
    const PACKAGE = 'package';
    const TERM = 'term';

    private $value;


  public function __construct( string $value ) {
      $this->value = $value;
  }


  public function getValue(): string {
      return $this->value;
  }


  public function isPost(): bool {
      return $this->value === self::POST;
  }


  public function isString(): bool {
      return $this->value === self::STRING;
  }


  public function isTerm(): bool {
      return $this->value === self::TERM;
  }


  public function isPackage(): bool {
      return $this->value === self::PACKAGE;
  }


  public static function post(): self {
      return new self( self::POST );
  }


  public static function string(): self {
      return new self( self::STRING );
  }


  public static function package(): self {
      return new self( self::PACKAGE );
  }


  public static function term(): self {
      return new self( self::TERM );
  }


}
