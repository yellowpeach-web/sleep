<?php

namespace WPML\Core\Component\Cdt\Domain;

class CdtEvent {

  const SCHEMA_VERSION = 1;

  const SETUP_COMPLETED  = 'setup_completed';
  const TEA_TOGGLED      = 'tea_toggled';
  const ENGINE_CHANGED   = 'engine_changed';
  const WPML_DEACTIVATED = 'wpml_deactivated';

  const TYPES = [ self::SETUP_COMPLETED, self::TEA_TOGGLED, self::ENGINE_CHANGED, self::WPML_DEACTIVATED ];

  const VALUE_MAX_LENGTH = 32;

  private $type;

  private $at;

  private $value;

  private $oldValue;

  private $dedupKey;


  public function __construct( string $type, int $at, $value, $oldValue, string $siteKey ) {
    $this->type     = $type;
    $this->at       = $at;
    $this->value    = self::fit( $value );
    $this->oldValue = self::fit( $oldValue );
    $this->dedupKey = hash( 'sha256', $siteKey . '|' . $type . '|' . $at );
  }


  public static function fromArray( $document ): ?self {
    if (
      ! is_array( $document )
      || ! in_array( $document['type'] ?? null, self::TYPES, true )
      || ! is_string( $document['at'] ?? null )
      || ! is_string( $document['dedup_key'] ?? null )
      || preg_match( '/^[0-9a-f]{64}$/', $document['dedup_key'] ) !== 1
    ) {
      return null;
    }

    $at = strtotime( $document['at'] );
    if ( $at === false ) {
      return null;
    }

    $event           = new self( (string) $document['type'], $at, $document['value'] ?? null, $document['old_value'] ?? null, '' );
    $event->dedupKey = $document['dedup_key'];

    return $event;
  }


  public function toArray(): array {
    return [
      'schema_version' => self::SCHEMA_VERSION,
      'type'           => $this->type,
      'at'             => (string) DateTimeFormat::fromUnix( $this->at ),
      'value'          => $this->value,
      'old_value'      => $this->oldValue,
      'dedup_key'      => $this->dedupKey,
    ];
  }


  public function getType(): string {
    return $this->type;
  }


  public function getDedupKey(): string {
    return $this->dedupKey;
  }


  private static function fit( $value ) {
    if ( is_bool( $value ) || $value === null ) {
      return $value;
    }

    return is_scalar( $value ) ? (string) substr( (string) $value, 0, self::VALUE_MAX_LENGTH ) : null;
  }


}
