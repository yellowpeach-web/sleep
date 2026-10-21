<?php

namespace WPML\Infrastructure\WordPress\Component\CustomFieldPreferences;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\ChangeSignalStorageInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class ChangeSignalStorage implements ChangeSignalStorageInterface {

  const WRITE_STAMP = 'wpml_meta_settings_write_stamp';

  const LAST_CHECK = 'wpml_meta_settings_last_check';

  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  public function readStamp(): int {
    $stamp = $this->options->get( self::WRITE_STAMP, 0 );

    return is_numeric( $stamp ) ? (int) $stamp : 0;
  }


  public function saveStamp( int $stamp ): void {
    $this->options->save( self::WRITE_STAMP, $stamp, true );
  }


  public function readLastCheck(): array {
    $lastCheck = $this->options->get( self::LAST_CHECK, [] );
    $lastCheck = is_array( $lastCheck ) ? $lastCheck : [];

    $stamp     = $lastCheck['stamp'] ?? null;
    $checkedAt = $lastCheck['checked_at'] ?? null;

    return [
      'stamp'      => is_numeric( $stamp ) ? (int) $stamp : -1,
      'checked_at' => is_numeric( $checkedAt ) ? (int) $checkedAt : 0,
    ];
  }


  public function saveLastCheck( int $stamp, int $checkedAt ): void {
    $this->options->save(
      self::LAST_CHECK,
      [
        'stamp'      => $stamp,
        'checked_at' => $checkedAt,
      ],
      true
    );
  }


}
