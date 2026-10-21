<?php

namespace WPML\Infrastructure\WordPress\Component\CustomFieldPreferences;

use WPML\Core\Component\CustomFieldPreferences\Domain\IncidentSinkInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class IncidentStore implements IncidentSinkInterface {

  const OPTION_NAME = 'wpml_meta_settings_incidents';

  const MAX_ENTRIES = 20;

  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  public function record( string $code, string $type, array $context ) {
    $entries  = $this->all();
    $key      = $code . '|' . $type;
    $now      = time();
    $previous = $entries[ $key ] ?? [];

    $entries[ $key ] = [
      'code'    => $code,
      'type'    => $type,
      'context' => $context,
      'first'   => isset( $previous['first'] ) && is_numeric( $previous['first'] ) ? (int) $previous['first'] : $now,
      'last'    => $now,
      'seen'    => isset( $previous['seen'] ) && is_numeric( $previous['seen'] ) ? (int) $previous['seen'] + 1 : 1,
    ];

    if ( count( $entries ) > self::MAX_ENTRIES ) {
      $entries = array_slice( $entries, -self::MAX_ENTRIES, null, true );
    }

    $this->options->save( self::OPTION_NAME, $entries, false );
  }


  public function resolve( string $code, string $type ) {
    $entries = $this->all();
    $key     = $code . '|' . $type;

    if ( ! array_key_exists( $key, $entries ) ) {
      return;
    }

    unset( $entries[ $key ] );

    if ( $entries ) {
      $this->options->save( self::OPTION_NAME, $entries, false );
    } else {
      $this->options->delete( self::OPTION_NAME );
    }
  }


  public function all(): array {
    $entries = $this->options->get( self::OPTION_NAME, [] );

    return is_array( $entries ) ? $entries : [];
  }


  public function clear() {
    $this->options->delete( self::OPTION_NAME );
  }


}
