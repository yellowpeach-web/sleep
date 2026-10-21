<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain\Job;

use WPML\Core\Component\ATE\Application\Service\PtcEngineStatus;
use WPML\Core\Component\WordsToTranslate\Domain\Job\Query\PtcEngineStatusQueryInterface;

class PtcEngineStatusQuery implements PtcEngineStatusQueryInterface {

  private $ptcEngineStatus;


  public function __construct( PtcEngineStatus $ptcEngineStatus ) {
    $this->ptcEngineStatus = $ptcEngineStatus;
  }


  public function isDefaultEngine(): bool {
    return $this->ptcEngineStatus->isDefaultEngine();
  }


}
