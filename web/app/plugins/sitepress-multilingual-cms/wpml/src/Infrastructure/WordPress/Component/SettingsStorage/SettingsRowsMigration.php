<?php

namespace WPML\Infrastructure\WordPress\Component\SettingsStorage;

use WPML\Core\Component\SettingsStorage\Application\Repository\StorageQueryInterface;
use WPML\Core\Component\SettingsStorage\Application\Service\RowsMigrationService;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\ContainerFreeServices;
use WPML\Infrastructure\WordPress\Port\Persistence\Options;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryHandler;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryPrepare;

class SettingsRowsMigration {

  const DEFERRED_STEP = 'settings-rows-migration';


  public static function addHooks() {
    add_action( 'admin_init', [ self::class, 'runIfNeeded' ], 20 );
  }


  public static function runIfNeeded() {
    if ( ! class_exists( RowsMigrationService::class ) || ! class_exists( ContainerFreeServices::class ) ) {
      return;
    }

    if ( class_exists( \WPML\ST\Upgrade\Deferred\Runner::class ) ) {
      if ( self::canRunNow() ) {
        \WPML\ST\Upgrade\Deferred\Runner::queue( self::DEFERRED_STEP );
      }
      return;
    }

    self::runNow();
  }


  public static function canRunNow(): bool {
    return ! self::isSettled() && ! self::service()->isWaitingForOffload();
  }

  public static function runNow() {
    if ( ! class_exists( RowsMigrationService::class ) || ! class_exists( ContainerFreeServices::class ) ) {
      return;
    }

    self::service()->runIfNeeded( defined( 'ICL_SITEPRESS_VERSION' ) ? ICL_SITEPRESS_VERSION : '' );
  }


  public static function isSettled(): bool {
    if ( ! class_exists( RowsMigrationService::class ) || ! class_exists( ContainerFreeServices::class ) ) {
      return true;
    }

    $registry = ( new Options() )->get( StorageQueryInterface::REGISTRY_ROW );
    if ( ! is_array( $registry ) ) {
      return false;
    }

    if ( VirtualSettingsOption::service()->registryKeysFrom( $registry ) === null ) {
      return true;
    }

    $version = defined( 'ICL_SITEPRESS_VERSION' ) ? ICL_SITEPRESS_VERSION : '';

    return ( $registry['version'] ?? null ) === $version;
  }


  private static function service(): RowsMigrationService {
    $wpdb = isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;

    return new RowsMigrationService(
      VirtualSettingsOption::service(),
      new StorageQuery( new QueryHandler( $wpdb ), new QueryPrepare( $wpdb ) ),
      new Options(),
      ContainerFreeServices::state()
    );
  }


}
