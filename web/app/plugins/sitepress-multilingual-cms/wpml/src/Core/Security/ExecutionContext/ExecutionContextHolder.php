<?php

namespace WPML\Core\Security\ExecutionContext;

final class ExecutionContextHolder {

  private static $stack = [];

  private static $processResolvers = [];

  private static $principalResolver = null;

  public static function within( ExecutionContext $context, callable $callback ) {
    self::establish( $context );
    try {
      return $callback();
    } finally {
      self::release();
    }
  }

  public static function coverDeferredWork( ExecutionContext $context, ?callable $isDeferredPhase = null ): void {
    if ( ! $context->isTrusted() ) {
      return;
    }

    self::registerProcessResolver( function () use ( $context, $isDeferredPhase ) {
      $inDeferredPhase = $isDeferredPhase ? $isDeferredPhase() : self::isShutdownPhase();

      return $inDeferredPhase ? $context : null;
    } );
  }

  private static function isShutdownPhase(): bool {
    if ( function_exists( 'doing_action' ) && doing_action( 'shutdown' ) ) {
      return true;
    }

    return function_exists( 'did_action' ) && did_action( 'shutdown' ) > 0;
  }

  public static function establish( ExecutionContext $context ): void {
    self::$stack[] = $context;
  }

  public static function release(): void {
    array_pop( self::$stack );
  }

  public static function hasEstablished(): bool {
    return count( self::$stack ) > 0;
  }

  public static function current(): ExecutionContext {
    $count = count( self::$stack );
    if ( $count > 0 ) {
      return self::$stack[ $count - 1 ];
    }

    foreach ( self::$processResolvers as $resolver ) {
      $resolved = $resolver();
      if ( $resolved instanceof ExecutionContext && $resolved->isTrusted() ) {
        return $resolved;
      }
    }

    return ExecutionContext::request( self::currentPrincipalId() );
  }

  public static function isTrusted(): bool {
    return self::current()->isTrusted();
  }

  public static function registerProcessResolver( callable $resolver ): void {
    self::$processResolvers[] = $resolver;
  }

  public static function setPrincipalResolver( callable $resolver ): void {
    self::$principalResolver = $resolver;
  }

  public static function currentPrincipalId(): int {
    if ( null === self::$principalResolver ) {
      return 0;
    }

    return max( 0, (int) call_user_func( self::$principalResolver ) );
  }

  public static function reset(): void {
    self::$stack             = [];
    self::$processResolvers  = [];
    self::$principalResolver = null;
  }
}
