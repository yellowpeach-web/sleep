<?php

namespace WPML\Core\SharedKernel\Component\Support\Application\Repository;

interface RepairToolsPluginInterface {

  public function isInstalled(): bool;


  public function isActive(): bool;


  public function activate(): bool;
}
