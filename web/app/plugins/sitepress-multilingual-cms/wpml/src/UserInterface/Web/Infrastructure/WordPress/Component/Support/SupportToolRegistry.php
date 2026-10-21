<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support;

use WPML\UserInterface\Web\Core\Component\Support\Application\SupportTool;
use WPML\UserInterface\Web\Core\Component\Support\Application\SupportToolRegistryInterface;

class SupportToolRegistry implements SupportToolRegistryInterface {

  const FILTER = 'wpml_support_tools';

  const OVERRIDABLE_SLUG = 'fix-translations';
  const OVERRIDABLE_PROVIDER = SupportLandingController::REPAIR_TOOLS_PROVIDER;

  private $tools = null;


  public function all(): array {
    if ( $this->tools === null ) {
      $this->tools = $this->load();
    }

    return $this->tools;
  }


  public function byTier( int $tier ): array {
    $tools = [];
    foreach ( $this->all() as $tool ) {
      if ( $tool->tier() === $tier && $this->isReachable( $tool ) && $tool->isApplicable() ) {
        $tools[] = $tool;
      }
    }

    return $tools;
  }


  public function find( string $slug ) {
    foreach ( $this->all() as $tool ) {
      if ( $tool->slug() === $slug ) {
        return $this->isReachable( $tool ) ? $tool : null;
      }
    }

    return null;
  }


  /**
   * Whether the current user may open the tool on this license.
   *
   * An administrator opens every tool whatever its capability says - the rule
   * every WPML endpoint already applies (`Api::capabilityPlusAdmin()`,
   * wpmldev-4629). Without it an administrator whose `wpml_manage_support`
   * was taken away (a role created after activation, a role editor) could
   * still USE the job-log actions while the tool they belong to was hidden
   * (wpmldev-9950, e2e registry TS-16c).
   */
  private function isReachable( SupportTool $tool ): bool {
    if ( $tool->requiresTm() && ! $this->translationManagementActive() ) {
      return false;
    }

    return current_user_can( $tool->capability() ) || current_user_can( WPML_CAP_MANAGE_OPTIONS );
  }


  /**
   * `WPML_TM_VERSION` is defined only when the embedded TM module loads,
   * i.e. not on a blog license — the signal the landing and the dispatcher
   * always used (wpmldev-7163). Overridable for tests.
   */
  protected function translationManagementActive(): bool {
    return defined( 'WPML_TM_VERSION' );
  }


  private function load(): array {
    $raw = apply_filters( self::FILTER, [] );
    if ( ! is_array( $raw ) ) {
      return [];
    }

    $bySlug = [];
    foreach ( $raw as $entry ) {
      if ( ! is_array( $entry ) ) {
        continue;
      }
      $tool = SupportTool::fromArray( $entry );
      if ( $tool === null ) {
        continue;
      }
      $slug = $tool->slug();
      if ( isset( $bySlug[ $slug ] ) ) {
        $mayOverride = self::OVERRIDABLE_SLUG === $slug
          && self::OVERRIDABLE_PROVIDER === $tool->provider()
          && $bySlug[ $slug ]->provider() === null;
        if ( ! $mayOverride ) {
          continue;
        }
      }
      $bySlug[ $slug ] = $tool;
    }
    $tools = array_values( $bySlug );

    $indexed = [];
    foreach ( $tools as $i => $tool ) {
      $indexed[] = [ $tool, $i ];
    }
    usort(
      $indexed,
      function ( array $a, array $b ) {
        return [ $a[0]->tier(), $a[0]->order(), $a[1] ] <=> [ $b[0]->tier(), $b[0]->order(), $b[1] ];
      }
    );

    $sorted = [];
    foreach ( $indexed as $pair ) {
      $sorted[] = $pair[0];
    }

    return $sorted;
  }


}
