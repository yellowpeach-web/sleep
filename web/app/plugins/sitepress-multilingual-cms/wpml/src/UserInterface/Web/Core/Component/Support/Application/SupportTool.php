<?php

namespace WPML\UserInterface\Web\Core\Component\Support\Application;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

/**
 * One entry of the Support tool registry (wpmldev-8103).
 *
 * A tool is what the `WPML → Support` landing shows as a tile, what the
 * search index lists, and what `?tool=<slug>` opens. Entries arrive as plain
 * arrays through the `wpml_support_tools` filter — from core's own
 * `CoreSupportTools` and from a sibling plugin (WPML Troubleshooting) — and
 * {@see SupportTool::fromArray()} is the one place that validates them.
 *
 * The entry contract (array keys):
 *
 *   slug         string   required, `^[a-z0-9-]+$`; the `&tool=` value.
 *   title        string   required, already translated.
 *   description  string   the tile's second line; default ''.
 *   tier         int      required, 1 (safe), 2 (logs) or 3 (advanced).
 *   order        int      position inside the tier, ascending; default 100.
 *   controller   PageRenderInterface|callable  required; a callable is
 *                         invoked lazily and must return a PageRenderInterface.
 *   capability   string   the capability the user needs to open the tool;
 *                         default `wpml_manage_support` (the page's own).
 *   applicable   callable|null  answers whether the tool is offered on THIS
 *                         site (the tile and the search entry); default: yes.
 *   search       array    list of `[ 'label' => string, 'anchor' => string ]`
 *                         sub-entries for the landing search index; default [].
 *   requiresTm   bool     the tool needs Translation Management (not on a
 *                         blog license): hidden and unreachable without it.
 *   provider     string|null  who registered the tool, e.g.
 *                         'wpml-troubleshooting'; core leaves it null.
 *   icon         string   the `d` attribute of the tile's SVG path; default ''.
 *   searchOrder  int|null position in the search index; default tier*1000+order.
 *
 * Unknown keys are ignored.
 */
final class SupportTool {

  const DEFAULT_CAPABILITY = 'wpml_manage_support';

  const TIER_SAFE     = 1;
  const TIER_LOGS     = 2;
  const TIER_ADVANCED = 3;

  private $slug;

  private $title;

  private $description;

  private $tier;

  private $order;

  private $controller;

  private $resolvedController = null;

  private $capability;

  private $applicable;

  private $search;

  private $requiresTm;

  private $provider;

  private $icon;

  private $searchOrder;


  private function __construct(
    string $slug,
    string $title,
    string $description,
    int $tier,
    int $order,
    $controller,
    string $capability,
    ?callable $applicable,
    array $search,
    bool $requiresTm,
    ?string $provider,
    string $icon,
    int $searchOrder
  ) {
    $this->slug        = $slug;
    $this->title       = $title;
    $this->description = $description;
    $this->tier        = $tier;
    $this->order       = $order;
    $this->controller  = $controller;
    $this->capability  = $capability;
    $this->applicable  = $applicable;
    $this->search      = $search;
    $this->requiresTm  = $requiresTm;
    $this->provider    = $provider;
    $this->icon        = $icon;
    $this->searchOrder = $searchOrder;
  }


  public static function fromArray( array $raw ) {
    $slug = $raw['slug'] ?? null;
    if ( ! is_string( $slug ) || preg_match( '/^[a-z0-9-]+$/', $slug ) !== 1 ) {
      return null;
    }

    $title = $raw['title'] ?? null;
    if ( ! is_string( $title ) || $title === '' ) {
      return null;
    }

    $tier = $raw['tier'] ?? null;
    if ( ! is_int( $tier ) || $tier < self::TIER_SAFE || $tier > self::TIER_ADVANCED ) {
      return null;
    }

    $controller = $raw['controller'] ?? null;
    if ( ! $controller instanceof PageRenderInterface && ! is_callable( $controller ) ) {
      return null;
    }

    $capability = $raw['capability'] ?? self::DEFAULT_CAPABILITY;
    if ( ! is_string( $capability ) || $capability === '' ) {
      return null;
    }

    $applicable = $raw['applicable'] ?? null;
    if ( $applicable !== null && ! is_callable( $applicable ) ) {
      return null;
    }

    $order = $raw['order'] ?? 100;
    if ( ! is_int( $order ) ) {
      return null;
    }

    $searchOrder = $raw['searchOrder'] ?? null;
    if ( $searchOrder !== null && ! is_int( $searchOrder ) ) {
      return null;
    }

    $provider = $raw['provider'] ?? null;
    if ( $provider !== null && ( ! is_string( $provider ) || $provider === '' ) ) {
      return null;
    }

    return new self(
      $slug,
      $title,
      is_string( $raw['description'] ?? null ) ? $raw['description'] : '',
      $tier,
      $order,
      $controller,
      $capability,
      $applicable,
      self::searchEntries( $raw['search'] ?? [] ),
      (bool) ( $raw['requiresTm'] ?? false ),
      $provider,
      is_string( $raw['icon'] ?? null ) ? $raw['icon'] : '',
      $searchOrder ?? $tier * 1000 + $order
    );
  }


  private static function searchEntries( $raw ): array {
    if ( ! is_array( $raw ) ) {
      return [];
    }

    $entries = [];
    foreach ( $raw as $entry ) {
      if ( ! is_array( $entry ) ) {
        continue;
      }
      $label = $entry['label'] ?? null;
      if ( ! is_string( $label ) || $label === '' ) {
        continue;
      }
      $anchor    = $entry['anchor'] ?? null;
      $entries[] = [
        'label'  => $label,
        'anchor' => is_string( $anchor ) ? $anchor : '',
      ];
    }

    return $entries;
  }


  public function slug(): string {
    return $this->slug;
  }


  public function title(): string {
    return $this->title;
  }


  public function description(): string {
    return $this->description;
  }


  public function tier(): int {
    return $this->tier;
  }


  public function order(): int {
    return $this->order;
  }


  public function controller() {
    if ( $this->resolvedController !== null ) {
      return $this->resolvedController;
    }

    if ( $this->controller instanceof PageRenderInterface ) {
      $this->resolvedController = $this->controller;
      return $this->resolvedController;
    }

    $built = call_user_func( $this->controller );
    if ( $built instanceof PageRenderInterface ) {
      $this->resolvedController = $built;
      return $this->resolvedController;
    }

    return null;
  }


  public function capability(): string {
    return $this->capability;
  }


  public function isApplicable(): bool {
    if ( $this->applicable === null ) {
      return true;
    }

    return (bool) call_user_func( $this->applicable );
  }


  public function search(): array {
    return $this->search;
  }


  public function requiresTm(): bool {
    return $this->requiresTm;
  }


  public function provider() {
    return $this->provider;
  }


  public function icon(): string {
    return $this->icon;
  }


  public function searchOrder(): int {
    return $this->searchOrder;
  }


}
