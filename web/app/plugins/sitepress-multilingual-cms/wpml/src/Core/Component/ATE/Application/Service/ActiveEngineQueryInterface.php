<?php

namespace WPML\Core\Component\ATE\Application\Service;

use WPML\Core\Component\ATE\Application\Service\Dto\ActiveEngineResolutionDto;
use WPML\Core\Component\ATE\Application\Service\Dto\EngineDto;

interface ActiveEngineQueryInterface {


  public function resolve(): ActiveEngineResolutionDto;


  public function get(): ?EngineDto;


}
