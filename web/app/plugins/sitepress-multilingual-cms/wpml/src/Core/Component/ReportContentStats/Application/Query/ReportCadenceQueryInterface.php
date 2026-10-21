<?php

namespace WPML\Core\Component\ReportContentStats\Application\Query;

interface ReportCadenceQueryInterface {


  public function isBlocked(): bool;


  public function isForced(): bool;


}
