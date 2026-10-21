<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeFunnelProps;

class EngineSwitchOrigin {

  const PARAM_SOURCE  = 'switch_source';
  const PARAM_SURFACE = 'switch_surface';

  const SOURCE_DIRECT = 'direct';

  const SURFACE_AI_SETTINGS = TeaUpgradeFunnelProps::SURFACE_AI_SETTINGS;
  const SURFACE_EATE        = 'eate';
  const SURFACE_DASHBOARD   = 'dashboard';

  const ALLOWED_SOURCES = [ 'quality_bar', 'tea_notice' ];

  const ALLOWED_SURFACES = [
    self::SURFACE_EATE,
    self::SURFACE_DASHBOARD,
    TeaUpgradeFunnelProps::SURFACE_TRANSLATIONS_DASHBOARD,
    TeaUpgradeFunnelProps::SURFACE_WP_DASHBOARD,
    TeaUpgradeFunnelProps::SURFACE_PAYMENTS_TAB,
    TeaUpgradeFunnelProps::SURFACE_AI_SETTINGS,
  ];


  public static function fromQuery( array $query ): array {
    $source  = isset( $query[ self::PARAM_SOURCE ] ) ? $query[ self::PARAM_SOURCE ] : null;
    $surface = isset( $query[ self::PARAM_SURFACE ] ) ? $query[ self::PARAM_SURFACE ] : null;

    if (
      is_string( $source ) && in_array( $source, self::ALLOWED_SOURCES, true )
      && is_string( $surface ) && in_array( $surface, self::ALLOWED_SURFACES, true )
    ) {
      return [
        'source'  => $source,
        'surface' => $surface,
      ];
    }

    return self::direct();
  }


  public static function direct(): array {
    return [
      'source'  => self::SOURCE_DIRECT,
      'surface' => self::SURFACE_AI_SETTINGS,
    ];
  }
}
