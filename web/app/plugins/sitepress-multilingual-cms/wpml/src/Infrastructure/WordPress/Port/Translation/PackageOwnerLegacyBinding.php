<?php

namespace WPML\Infrastructure\WordPress\Port\Translation;

use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\PackageOwnerInterface;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryHandler;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryPrepare;
use WPML\Infrastructure\WordPress\Port\Translation\PackageOwner;

class PackageOwnerLegacyBinding {

  public static function register(): void {
    if ( class_exists( \WPML\Container\Container::class ) ) {
      \WPML\Container\Container::alias( [
        PackageOwnerInterface::class  => PackageOwner::class,
        QueryHandlerInterface::class => QueryHandler::class,
        QueryPrepareInterface::class => QueryPrepare::class,
      ] );
    }
  }
}
