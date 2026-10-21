<?php

namespace WPML\Core\Component\Translation\Domain;

use WPML\PHP\Exception\InvalidArgumentException;

class TranslationType {
  const POST = 'post';
  const PACKAGE = 'package';
  const STRING_BATCH = 'string-batch';
  const STRING = 'string';
  const TAXONOMY = 'taxonomy';

  private $value;


  public function __construct( string $value ) {
    if ( in_array( $value, self::getAll() ) ) {
      $this->value = $value;
    } else {
      throw new InvalidArgumentException( 'Invalid job type: ' . $value );
    }

  }


  public function get(): string {
    return $this->value;
  }


  public static function fromElementType( string $elementType ): self {
      $separator = strpos( $elementType, '_' );
      $prefix    = false === $separator ? '' : substr( $elementType, 0, $separator );

    switch ( $prefix ) {
      case 'package':
        return self::package();
      case 'st-batch':
        return self::stringBatch();
      case 'tax':
        return self::taxonomy();
      default:
        return self::post();
    }
  }


  public function getElementTypePrefix(): string {
    switch ( $this->value ) {
      case self::STRING_BATCH:
        return 'st-batch';
      case self::TAXONOMY:
        return 'tax';
      default:
        return $this->value;
    }
  }


  public static function getAll(): array {
    return [
      self::POST,
      self::PACKAGE,
      self::STRING_BATCH,
      self::STRING,
      self::TAXONOMY,
    ];
  }


  public static function post(): self {
    return new self( self::POST );
  }


  public static function package(): self {
    return new self( self::PACKAGE );
  }


  public static function stringBatch(): self {
    return new self( self::STRING_BATCH );
  }


  public static function string(): self {
    return new self( self::STRING );
  }


  public static function taxonomy(): self {
    return new self( self::TAXONOMY );
  }


}
