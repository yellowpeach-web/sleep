<?php

namespace WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application;

use WPML\Core\Component\ATE\Application\Service\PtcEngineStatus;
use WPML\Core\SharedKernel\Component\Installer\Application\Query\WpmlSiteKeyQueryInterface;

class TeaUpgradeFunnelProps {

  const SURFACE_TRANSLATIONS_DASHBOARD = 'translations_dashboard';
  const SURFACE_WP_DASHBOARD           = 'wp_dashboard';
  const SURFACE_PAYMENTS_TAB           = 'payments_tab';
  const SURFACE_AI_SETTINGS            = 'ai_settings';

  private $siteKeyQuery;


  public function __construct( WpmlSiteKeyQueryInterface $siteKeyQuery ) {
    $this->siteKeyQuery = $siteKeyQuery;
  }


  public function build( string $surface, array $noticeData ): array {
    return [
      'surface'             => $surface,
      'gap_profile_initial' => $noticeData['scenario'] === null
        ? 'unknown'
        : $this->gapProfileInitial( $noticeData['scenario'] ),
      'notice_instance_id'  => $noticeData['noticeInstanceId'],
      'site_key'            => $this->siteKeyQuery->get() ?: '',
    ];
  }


  private function gapProfileInitial( array $scenario ): string {
    $isPtc = $scenario['engine'] === PtcEngineStatus::PTC_ENGINE_SLUG;

    if ( $isPtc && $scenario['descriptionPresent'] && $scenario['teaOn'] ) {
      return 'none';
    }

    if ( ! $isPtc ) {
      return $scenario['teaOn'] ? 'engine+description' : 'engine+description+tea';
    }

    return $scenario['descriptionPresent'] ? 'tea_only' : 'description+tea';
  }


}
