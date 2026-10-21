<?php

namespace WPML\Core\Security\ExecutionContext;

abstract class TrustProof {

  private $boundary;

  private $subject;

  final protected function __construct( string $boundary, string $subject ) {
    TrustedBoundaries::assertMayMint( $boundary, static::provenance() );

    $this->boundary = $boundary;
    $this->subject  = $subject;
  }

  abstract public static function provenance(): string;

  final public function boundary(): string {
    return $this->boundary;
  }

  final public function subject(): string {
    return $this->subject;
  }

  final public function describe(): string {
    return sprintf( '%s(%s) by %s', static::provenance(), $this->subject, $this->boundary );
  }

  final protected static function minter(): string {
    $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );

    return isset( $trace[2]['class'] ) ? $trace[2]['class'] : '';
  }
}
