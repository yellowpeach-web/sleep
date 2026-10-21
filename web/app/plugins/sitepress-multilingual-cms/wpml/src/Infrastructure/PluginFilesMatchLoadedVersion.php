<?php

namespace WPML\Infrastructure;

final class PluginFilesMatchLoadedVersion {

  private $pluginFile;

  private $loadedVersion;

  private $matches;

  private static $sitepressGate;


  public function __construct( string $pluginFile, string $loadedVersion ) {
    $this->pluginFile    = $pluginFile;
    $this->loadedVersion = $loadedVersion;
  }


  public static function forSitepress(): self {
    if ( self::$sitepressGate === null ) {
      self::$sitepressGate = new self(
        defined( 'WPML_PLUGIN_PATH' ) ? WPML_PLUGIN_PATH . '/sitepress.php' : '',
        defined( 'ICL_SITEPRESS_VERSION' ) ? (string) ICL_SITEPRESS_VERSION : ''
      );
    }

    return self::$sitepressGate;
  }


  public function check(): bool {
    if ( $this->matches === null ) {
      $this->matches = $this->pluginFile === ''
        || $this->loadedVersion === ''
        || $this->versionOnDisk() === $this->loadedVersion;
    }

    return $this->matches;
  }


  private function versionOnDisk(): string {
    if ( ! function_exists( 'get_file_data' ) ) {
      return $this->loadedVersion;
    }

    $header = get_file_data( $this->pluginFile, [ 'Version' => 'Version' ] );

    return ! empty( $header['Version'] )
      ? $header['Version']
      : $this->loadedVersion;
  }


}
