<?php

namespace WPML\Infrastructure\WordPress\Component\SettingsStorage;

use WPML\Core\Component\SettingsStorage\Application\Repository\StorageQueryInterface;
use WPML\Core\Component\SettingsStorage\Application\Service\BlobDiffService;
use WPML\Core\Component\SettingsStorage\Application\Service\RowEnvelope;
use WPML\Core\Component\SettingsStorage\Application\Service\VirtualOptionService;
use WPML\Infrastructure\WordPress\Port\Persistence\Options;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryHandler;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryPrepare;

class VirtualSettingsOption {

  const CACHE_GENERATION_SENTINEL = 'wpml_settings_storage_generation';

  private static $service;

  private static $lostKeyDefaults = [];


  public static function restoreLostKeyTo( string $key, $default ) {
    self::$lostKeyDefaults[ $key ] = $default;
    if ( self::$service !== null ) {
      self::$service->restoreLostKeyTo( $key, $default );
    }
  }


  public static function recordSetterValue( string $key, $value ) {
    if ( ! class_exists( BlobDiffService::class ) ) {
      return;
    }

    self::service()->recordSetterValue( $key, $value, get_current_blog_id() );
  }


  public static function addHooks() {
    if ( ! class_exists( BlobDiffService::class ) ) {
      return;
    }

    $adapter = new self();
    add_filter( 'option_' . StorageQueryInterface::OPTION_NAME, [ $adapter, 'filterRead' ], 5 );
    add_filter( 'option_' . StorageQueryInterface::OPTION_NAME, [ $adapter, 'recordServedSnapshot' ], PHP_INT_MAX );
    add_filter( 'pre_update_option_' . StorageQueryInterface::OPTION_NAME, [ $adapter, 'filterWrite' ], 20, 2 );
    add_action( 'delete_option_' . StorageQueryInterface::OPTION_NAME, [ $adapter, 'onDeleteOption' ] );
  }


  public static function service(): VirtualOptionService {
    if ( self::$service === null ) {
      $wpdb = isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;

      self::$service = new VirtualOptionService(
        new Options(),
        new StorageQuery( new QueryHandler( $wpdb ), new QueryPrepare( $wpdb ) ),
        new BlobDiffService(),
        new RowEnvelope()
      );
      foreach ( self::$lostKeyDefaults as $key => $default ) {
        self::$service->restoreLostKeyTo( $key, $default );
      }
    }

    return self::$service;
  }


  public function filterRead( $value ) {
    if ( ! class_exists( BlobDiffService::class ) ) {
      return $value;
    }

    self::refreshAfterCacheFlush();

    return self::service()->read( $value, get_current_blog_id() );
  }


  private static function refreshAfterCacheFlush() {
    if ( wp_cache_get( self::CACHE_GENERATION_SENTINEL ) !== false ) {
      return;
    }
    self::service()->refreshFromPersistence();
    wp_cache_set( self::CACHE_GENERATION_SENTINEL, 1 );
  }


  public function recordServedSnapshot( $value ) {
    if ( ! class_exists( BlobDiffService::class ) ) {
      return $value;
    }

    return self::service()->recordSnapshot( $value, get_current_blog_id() );
  }


  public function filterWrite( $value, $oldValue = false ) {
    if ( ! class_exists( BlobDiffService::class ) ) {
      return $value;
    }

    self::refreshAfterCacheFlush();

    $context = get_current_blog_id();

    if ( self::service()->registryVanishedUnderneath( $context ) ) {
      return $oldValue;
    }

    return self::service()->write( $value, $context, $oldValue );
  }


  public function onDeleteOption() {
    if ( ! class_exists( BlobDiffService::class ) ) {
      return;
    }

    self::service()->delete( get_current_blog_id() );
  }


}
