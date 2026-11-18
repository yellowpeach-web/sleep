<?php

namespace WPML\Core\SharedKernel\Component\ATE\Application\Query;

interface AutomaticTranslationAccountQueryInterface {


  public function needsAccountOnly(): bool;


  public function isUsable(): bool;


}
