<?php

namespace WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\Api\Endpoints;

interface PluginReportInterface {


  public function run( string $siteKey, array $debug, bool $consent = true ): array;


}
