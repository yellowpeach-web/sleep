<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Service;

use WPML\Core\Component\CustomFieldPreferences\Application\Repository\ChangeSignalStorageInterface;

final class PreferenceChangeSignal {

  private $storage;

  private $observed;


  public function __construct( ChangeSignalStorageInterface $storage ) {
    $this->storage = $storage;
  }


  public function stamp(): int {
    return $this->storage->readStamp();
  }


  public function bump(): void {
    $this->storage->saveStamp( $this->storage->readStamp() + 1 );
  }


  public function isCheckDue( int $recheckSeconds ): bool {
    $stamp     = $this->stamp();
    $lastCheck = $this->storage->readLastCheck();

    $this->observed = $stamp;

    return $stamp !== $lastCheck['stamp'] || time() - $lastCheck['checked_at'] >= $recheckSeconds;
  }


  public function markChecked(): void {
    $this->storage->saveLastCheck( $this->observed ?? $this->stamp(), time() );
  }


}
