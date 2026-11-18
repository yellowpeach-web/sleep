<?php

namespace WPML\Core\SharedKernel\Component\Server\Application\Query;

interface TemplateCacheStateQueryInterface {

  const STATE_ON = 'on';
  const STATE_ON_NOT_YET = 'on-not-yet';
  const STATE_ON_CANNOT_WRITE = 'on-cannot-write';
  const STATE_OFF_UNTIL = 'off-until';
  const STATE_OFF_UNRECORDED = 'off-unrecorded';
  const STATE_OFF_LEGACY = 'off-legacy';

  const REASON_NO_FOLDER = 'no-folder';
  const REASON_WRITE_FAILED = 'write-failed';


  public function get(): array;


}
