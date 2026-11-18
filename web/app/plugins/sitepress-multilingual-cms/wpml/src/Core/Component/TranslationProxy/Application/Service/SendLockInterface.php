<?php

namespace WPML\Core\Component\TranslationProxy\Application\Service;

interface SendLockInterface {

  public function acquire(): bool;


  public function release();

}
