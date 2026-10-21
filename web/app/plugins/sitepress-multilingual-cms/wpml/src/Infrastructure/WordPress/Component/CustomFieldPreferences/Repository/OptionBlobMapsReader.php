<?php

namespace WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\Repository;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\BlobMapsReaderInterface;
use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\Core\Port\Persistence\OptionsInterface;

class OptionBlobMapsReader implements BlobMapsReaderInterface {

  const OPTION_NAME = 'icl_sitepress_settings';

  private static $maps = [];

  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  public function getMaps(): array {
    $blog = \get_current_blog_id();
    if ( ! isset( self::$maps[ $blog ] ) ) {
      self::$maps[ $blog ] = $this->readMaps();
    }

    return self::$maps[ $blog ];
  }


  public function resetRuntimeCache(): void {
    self::$maps = [];
  }


  private function readMaps(): array {
    $settings = $this->options->get( self::OPTION_NAME, [] );
    $tm       = is_array( $settings )
      && isset( $settings[ ElementType::SETTINGS_KEY ] )
      && is_array( $settings[ ElementType::SETTINGS_KEY ] )
      ? $settings[ ElementType::SETTINGS_KEY ]
      : [];

    $maps = [];
    foreach ( ElementType::BLOB_KEYS as $type => $blobKey ) {
      $maps[ $type ] = isset( $tm[ $blobKey ] ) && is_array( $tm[ $blobKey ] ) ? $tm[ $blobKey ] : [];
    }

    return $maps;
  }


}
