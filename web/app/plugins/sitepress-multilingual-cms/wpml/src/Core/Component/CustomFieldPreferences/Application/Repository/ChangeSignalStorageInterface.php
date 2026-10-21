<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Repository;

interface ChangeSignalStorageInterface {


  public function readStamp(): int;


  public function saveStamp( int $stamp ): void;


  public function readLastCheck(): array;


  public function saveLastCheck( int $stamp, int $checkedAt ): void;


}
