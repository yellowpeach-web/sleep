<?php

namespace WPML\Infrastructure\WordPress\Component\CustomFieldPreferences;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\MigrationStateStorageInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Repository\PreferenceRepositoryInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Repository\StoreReadinessInterface;
use WPML\Core\Component\CustomFieldPreferences\Application\Service\DeletionBrake;
use WPML\Core\Component\CustomFieldPreferences\Application\Service\MigrateService;
use WPML\Core\Component\CustomFieldPreferences\Application\Service\PreferenceChangeSignal;
use WPML\Core\Component\CustomFieldPreferences\Application\Service\PreferenceMapsService;
use WPML\Core\Component\CustomFieldPreferences\Application\Service\PreferenceWriteService;
use WPML\Core\Component\CustomFieldPreferences\Application\Service\ReadInjectionService;
use WPML\Core\Component\CustomFieldPreferences\Application\Service\RestoreService;
use WPML\Core\Component\CustomFieldPreferences\Application\Service\UnknownPreferenceResolver;
use WPML\Core\Component\CustomFieldPreferences\Application\Service\WriteBoundaryService;
use WPML\Core\Component\CustomFieldPreferences\Domain\StoreIncidents;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\Repository\BlobMapsRepository;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\Repository\OptionBlobMapsReader;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\Repository\PreferenceRepository;
use WPML\Infrastructure\WordPress\Port\Persistence\DatabaseWrite;
use WPML\Infrastructure\WordPress\Port\Persistence\Options;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryHandler;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryPrepare;

class ContainerFreeServices {


  public static function preferences(): PreferenceRepositoryInterface {
    static $instance = null;
    if ( $instance === null ) {
      $instance = new CachedPreferenceRepository( self::preferenceRepository() );
    }

    return $instance;
  }


  public static function incidents(): IncidentStore {
    static $instance = null;
    if ( $instance === null ) {
      $instance = new IncidentStore( new Options() );
      StoreIncidents::load( $instance );
    }

    return $instance;
  }


  public static function state(): MigrationStateStorageInterface {
    static $instance = null;
    if ( $instance === null ) {
      $instance = new MigrationStateStorage( new Options() );
    }

    return $instance;
  }


  public static function reader(): ReadInjectionService {
    static $instance = null;
    if ( $instance === null ) {
      $instance = new ReadInjectionService( self::preferences(), self::state() );
    }

    return $instance;
  }


  public static function maps(): PreferenceMapsService {
    static $instance = null;
    if ( $instance === null ) {
      $instance = new PreferenceMapsService(
        self::preferences(),
        new OptionBlobMapsReader( new Options() ),
        self::state()
      );
    }

    return $instance;
  }


  public static function changeSignal(): PreferenceChangeSignal {
    static $instance = null;
    if ( $instance === null ) {
      $instance = new PreferenceChangeSignal( new ChangeSignalStorage( new Options() ) );
    }

    return $instance;
  }


  public static function storeReadiness(): StoreReadinessInterface {
    static $instance = null;
    if ( $instance === null ) {
      $instance = new StoreReadiness( self::queryHandler(), self::queryPrepare() );
    }

    return $instance;
  }


  public static function writeBoundary(): WriteBoundaryService {
    static $instance = null;
    if ( $instance === null ) {
      $instance = new WriteBoundaryService(
        self::preferences(),
        self::state(),
        self::changeSignal(),
        new DeletionBrake(),
        new OptionBlobMapsReader( new Options() ),
        self::storeReadiness()
      );
    }

    return $instance;
  }


  public static function preferenceWriter(): PreferenceWriteService {
    return new PreferenceWriteService( self::preferences(), self::state(), self::changeSignal() );
  }


  public static function migrate(): MigrateService {
    return new MigrateService(
      self::blobMapsRepository(),
      self::preferences(),
      self::state(),
      new DeletionBrake()
    );
  }


  public static function restore(): RestoreService {
    return new RestoreService( self::blobMapsRepository(), self::preferenceRepository(), self::state() );
  }


  private static function preferenceRepository(): PreferenceRepository {
    self::incidents();

    return new PreferenceRepository( self::queryHandler(), self::queryPrepare(), self::databaseWrite() );
  }


  private static function blobMapsRepository(): BlobMapsRepository {
    self::incidents();

    return new BlobMapsRepository( self::queryHandler(), self::queryPrepare(), self::databaseWrite() );
  }




  private static function queryHandler(): QueryHandler {
    static $instance = null;
    if ( $instance === null ) {
      $wpdb     = isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;
      $instance = new QueryHandler( $wpdb );
    }

    return $instance;
  }


  private static function queryPrepare(): QueryPrepare {
    static $instance = null;
    if ( $instance === null ) {
      $wpdb     = isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;
      $instance = new QueryPrepare( $wpdb );
    }

    return $instance;
  }


  private static function databaseWrite(): DatabaseWrite {
    static $instance = null;
    if ( $instance === null ) {
      $wpdb     = isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;
      $instance = new DatabaseWrite( $wpdb );
    }

    return $instance;
  }


  public static function unknownPreferenceResolver(): UnknownPreferenceResolver {
    static $resolver;
    if ( $resolver === null ) {
      $resolver = new UnknownPreferenceResolver(
        new ExactEntryLookup( self::maps() ),
        new UnknownPreferenceFilterSource()
      );
    }

    return $resolver;
  }


}
