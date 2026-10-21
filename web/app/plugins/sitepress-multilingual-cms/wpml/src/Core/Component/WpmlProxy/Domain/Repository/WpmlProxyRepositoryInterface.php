<?php

namespace WPML\Core\Component\WpmlProxy\Domain\Repository;

interface WpmlProxyRepositoryInterface {


  public function isEnabled(): bool;


  public function setIsEnabled( bool $isEnabled );


  public function secondsSinceLastAutomaticFlip();


  public function recordAutomaticFlip( bool $isEnabled );


  public function recordRefusedAutomaticFlip( bool $isEnabled, string $reason );


  public function clearAutomaticFlipRecord();


}
