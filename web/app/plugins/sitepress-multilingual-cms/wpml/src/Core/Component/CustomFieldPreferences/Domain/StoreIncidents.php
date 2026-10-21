<?php

namespace WPML\Core\Component\CustomFieldPreferences\Domain;

class StoreIncidents {

  const DELETION_REFUSED = 'deletion-refused';

  const BLOB_HEALED = 'blob-healed';

  const BLOB_HEAL_FAILED = 'blob-heal-failed';

  const MODE_QUARANTINED = 'mode-quarantined';

  const WRITE_FAILED = 'write-failed';

  const UPGRADE_PENDING = 'upgrade-pending';

  private static $sink;


  public static function load( IncidentSinkInterface $sink ) {
    self::$sink = $sink;
  }


  public static function unload() {
    self::$sink = null;
  }


  public static function record( string $code, string $type, array $context = [] ) {
    if ( self::$sink !== null ) {
      self::$sink->record( $code, $type, $context );
    }
  }


  public static function resolve( string $code, string $type ) {
    if ( self::$sink !== null ) {
      self::$sink->resolve( $code, $type );
    }
  }


}
