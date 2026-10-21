<?php

namespace WPML\UserInterface\Web\Core\Component\Support\Application;

interface SupportToolRegistryInterface {


  public function all(): array;


  public function byTier( int $tier ): array;


  /**
   * The tool `?tool=<slug>` opens, or null when no such tool is reachable:
   * unknown slug, missing capability, or a TM tool on a blog license.
   * `applicable` is NOT consulted here — a tool that is not offered on the
   * landing still answers a direct URL, as it always did.
   *
   * @return SupportTool|null
   */
  public function find( string $slug );


}
