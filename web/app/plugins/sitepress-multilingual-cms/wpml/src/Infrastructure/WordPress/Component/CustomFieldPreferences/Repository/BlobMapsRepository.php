<?php

namespace WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\Repository;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\BlobMapsRepositoryInterface;
use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\Core\Port\Persistence\DatabaseWriteInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

class BlobMapsRepository implements BlobMapsRepositoryInterface {

  const OPTION_NAME = 'icl_sitepress_settings';

  private $queryHandler;

  private $queryPrepare;

  private $databaseWrite;


  public function __construct(
    QueryHandlerInterface $queryHandler,
    QueryPrepareInterface $queryPrepare,
    DatabaseWriteInterface $databaseWrite
  ) {
    $this->queryHandler  = $queryHandler;
    $this->queryPrepare  = $queryPrepare;
    $this->databaseWrite = $databaseWrite;
  }


  public function getMaps(): array {
    $tm   = $this->readTmSettings();
    $maps = [];
    foreach ( ElementType::BLOB_KEYS as $type => $blobKey ) {
      if ( isset( $tm[ $blobKey ] ) && is_array( $tm[ $blobKey ] ) ) {
        $maps[ $type ] = $tm[ $blobKey ];
      }
    }
    return $maps;
  }


  public function resaveForCutover() {
    $settings = $this->readRaw();
    if ( $settings !== [] ) {
      \update_option( self::OPTION_NAME, $settings );
    }
    $this->forgetMapsInRequestSettings();
  }


  private function forgetMapsInRequestSettings() {
    global $sitepress_settings;

    if ( ! isset( $sitepress_settings[ ElementType::SETTINGS_KEY ] ) || ! is_array( $sitepress_settings[ ElementType::SETTINGS_KEY ] ) ) {
      return;
    }
    foreach ( ElementType::BLOB_KEYS as $blobKey ) {
      unset( $sitepress_settings[ ElementType::SETTINGS_KEY ][ $blobKey ] );
    }
  }


  public function restore( array $mapsByType ): bool {
    $complete = [];
    foreach ( ElementType::BLOB_KEYS as $type => $blobKey ) {
      $complete[ $type ] = isset( $mapsByType[ $type ] ) ? $mapsByType[ $type ] : [];
    }

    return $this->writeMaps( $complete );
  }


  public function restoreMaps( array $mapsByType ): bool {
    return $mapsByType === [] ? true : $this->writeMaps( $mapsByType );
  }


  private function writeMaps( array $mapsByType ): bool {
    $settings = $this->readRaw();
    if ( $settings === [] ) {
      return false;
    }
    if (
      ! isset( $settings[ ElementType::SETTINGS_KEY ] )
      || ! is_array( $settings[ ElementType::SETTINGS_KEY ] )
    ) {
      $settings[ ElementType::SETTINGS_KEY ] = [];
    }
    foreach ( $mapsByType as $type => $map ) {
      if ( isset( ElementType::BLOB_KEYS[ $type ] ) ) {
        $settings[ ElementType::SETTINGS_KEY ][ ElementType::BLOB_KEYS[ $type ] ] = $map;
      }
    }

    try {
      $this->databaseWrite->update(
        'options',
        [ 'option_value' => serialize( $settings ) ],
        [ 'option_name' => self::OPTION_NAME ]
      );
    } catch ( DatabaseErrorException $e ) {
      return false;
    }
    \wp_cache_delete( self::OPTION_NAME, 'options' );
    \wp_cache_delete( 'alloptions', 'options' );

    $readBack = $this->getMaps();
    foreach ( $mapsByType as $type => $expected ) {
      if ( ! isset( $readBack[ $type ] ) || $readBack[ $type ] !== $expected ) {
        return false;
      }
    }
    return true;
  }


  private function readTmSettings(): array {
    $settings = $this->readRaw();
    return isset( $settings[ ElementType::SETTINGS_KEY ] ) && is_array( $settings[ ElementType::SETTINGS_KEY ] )
      ? $settings[ ElementType::SETTINGS_KEY ]
      : [];
  }


  private function readRaw(): array {
    try {
      $row = $this->queryHandler->querySingle(
        $this->queryPrepare->prepare(
          'SELECT option_value FROM ' . $this->queryPrepare->prefix() . 'options WHERE option_name = %s',
          self::OPTION_NAME
        )
      );
    } catch ( DatabaseErrorException $e ) {
      return [];
    }
    $settings = \maybe_unserialize( is_string( $row ) ? $row : '' );
    return is_array( $settings ) ? $settings : [];
  }


}
