<?php

namespace WPML\Infrastructure\WordPress\Component\CustomFieldPreferences;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\PreferenceRepositoryInterface;
use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\Core\Component\CustomFieldPreferences\Domain\StorableKeys;

class CachedPreferenceRepository implements PreferenceRepositoryInterface {

  const CACHE_GROUP = 'wpml_meta_settings';

  private static $map = [];

  private static $modes = [];

  private $inner;


  public function __construct( PreferenceRepositoryInterface $inner ) {
    $this->inner = $inner;
  }


  private static function blog(): int {
    return \get_current_blog_id();
  }


  public function getMap( string $type ): array {
    $blog = self::blog();
    if ( ! isset( self::$map[ $blog ][ $type ] ) ) {
      $cached = \wp_cache_get( 'map_' . $type, self::CACHE_GROUP );
      if ( is_array( $cached ) ) {
        self::$map[ $blog ][ $type ] = $cached;
      } else {
        $map                         = $this->inner->getMap( $type );
        self::$map[ $blog ][ $type ] = $map;
        \wp_cache_set( 'map_' . $type, $map, self::CACHE_GROUP );
      }
    }
    return self::$map[ $blog ][ $type ];
  }


  public function getMaps(): array {
    $blog    = self::blog();
    $missing = [];
    foreach ( ElementType::all() as $type ) {
      if ( ! isset( self::$map[ $blog ][ $type ] ) ) {
        $cached = \wp_cache_get( 'map_' . $type, self::CACHE_GROUP );
        if ( is_array( $cached ) ) {
          self::$map[ $blog ][ $type ] = $cached;
        } else {
          $missing[] = $type;
        }
      }
    }

    if ( $missing ) {
      $fresh = $this->inner->getMaps();
      foreach ( $missing as $type ) {
        $map                         = isset( $fresh[ $type ] ) ? $fresh[ $type ] : [];
        self::$map[ $blog ][ $type ] = $map;
        \wp_cache_set( 'map_' . $type, $map, self::CACHE_GROUP );
      }
    }

    $maps = [];
    foreach ( ElementType::all() as $type ) {
      $maps[ $type ] = self::$map[ $blog ][ $type ];
    }
    return $maps;
  }


  public function getMode( string $type, string $name ) {
    $blog = self::blog();
    if ( isset( self::$map[ $blog ][ $type ] ) ) {
      $map = self::$map[ $blog ][ $type ];
      return isset( $map[ $name ] ) ? $map[ $name ] : null;
    }
    if ( ! isset( self::$modes[ $blog ][ $type ] ) || ! array_key_exists( $name, self::$modes[ $blog ][ $type ] ) ) {
      self::$modes[ $blog ][ $type ][ $name ] = $this->inner->getMode( $type, $name );
    }
    return self::$modes[ $blog ][ $type ][ $name ];
  }


  public function getModes( string $type, array $names ): array {
    $blog  = self::blog();
    $names = array_values( array_unique( array_map( 'strval', $names ) ) );

    if ( isset( self::$map[ $blog ][ $type ] ) ) {
      $map   = self::$map[ $blog ][ $type ];
      $modes = [];
      foreach ( $names as $name ) {
        $modes[ $name ] = isset( $map[ $name ] ) ? $map[ $name ] : null;
      }
      return $modes;
    }

    $known   = isset( self::$modes[ $blog ][ $type ] ) ? self::$modes[ $blog ][ $type ] : [];
    $unknown = [];
    foreach ( $names as $name ) {
      if ( ! array_key_exists( $name, $known ) ) {
        $unknown[] = $name;
      }
    }

    if ( $unknown ) {
      foreach ( $this->inner->getModes( $type, $unknown ) as $name => $mode ) {
        self::$modes[ $blog ][ $type ][ $name ] = $mode;
      }
    }

    $modes = [];
    foreach ( $names as $name ) {
      $modes[ $name ] = self::$modes[ $blog ][ $type ][ $name ];
    }
    return $modes;
  }


  public function upsertMany( string $type, array $nameToMode ): bool {
    if ( ! $nameToMode ) {
      return true;
    }
    $ok = $this->inner->upsertMany( $type, $nameToMode );

    $blog = self::blog();
    if ( ! $ok ) {
      unset( self::$map[ $blog ][ $type ] );
    } elseif ( isset( self::$map[ $blog ][ $type ] ) ) {
      foreach ( StorableKeys::filter( $nameToMode ) as $name => $mode ) {
        self::$map[ $blog ][ $type ][ $name ] = $mode;
      }
    }
    if ( $ok ) {
      foreach ( StorableKeys::filter( $nameToMode ) as $name => $mode ) {
        self::$modes[ $blog ][ $type ][ $name ] = $mode;
      }
    } else {
      unset( self::$modes[ $blog ][ $type ] );
    }
    \wp_cache_delete( 'map_' . $type, self::CACHE_GROUP );
    return $ok;
  }


  public function deleteMany( string $type, array $names ): bool {
    if ( ! $names ) {
      return true;
    }
    $ok = $this->inner->deleteMany( $type, $names );

    $blog = self::blog();
    if ( ! $ok ) {
      unset( self::$map[ $blog ][ $type ] );
      unset( self::$modes[ $blog ][ $type ] );
    } else {
      if ( isset( self::$map[ $blog ][ $type ] ) ) {
        foreach ( $names as $name ) {
          unset( self::$map[ $blog ][ $type ][ $name ] );
        }
      }
      foreach ( $names as $name ) {
        self::$modes[ $blog ][ $type ][ (string) $name ] = null;
      }
    }
    \wp_cache_delete( 'map_' . $type, self::CACHE_GROUP );
    return $ok;
  }


  public function countByType( string $type ): int {
    return $this->inner->countByType( $type );
  }


  public function resetRuntimeCache() {
    self::$map   = [];
    self::$modes = [];
  }


}
