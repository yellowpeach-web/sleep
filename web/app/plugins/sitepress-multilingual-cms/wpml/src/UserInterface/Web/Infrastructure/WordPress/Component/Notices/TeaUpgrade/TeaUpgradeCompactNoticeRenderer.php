<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Notices\TeaUpgrade;

use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeDisplayedTracker;
use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeFunnelProps;
use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeNoticeDataProvider;

class TeaUpgradeCompactNoticeRenderer {

  private $noticeDataProvider;

  private $displayedTracker;


  public function __construct(
    TeaUpgradeNoticeDataProvider $noticeDataProvider,
    TeaUpgradeDisplayedTracker $displayedTracker
  ) {
    $this->noticeDataProvider = $noticeDataProvider;
    $this->displayedTracker   = $displayedTracker;
  }


  public function render( string $surface ): void {
    $this->renderIfVisible( $surface, true );
  }


  public function renderIfVisible( string $surface, bool $visible ): void {
    if ( ! $visible ) {
      return;
    }

    $noticeData = $this->noticeDataProvider->get();
    if ( ! $noticeData['isEligible'] ) {
      return;
    }

    echo '<div class="notice notice-info wpml-tea-upgrade-compact-notice" data-testid="tea-upgrade-compact-notice">';
    echo '<p><strong>' . $this->text( 'Your automatic translation pricing has been upgraded' ) . '</strong></p>';
    echo '<p>'
         . $this->text( 'Finish the recommended setup from the Translations Dashboard to use the new pricing.' )
         . '</p>';
    echo '<p><a class="button button-primary" href="' . $this->url( $this->dashboardUrl() ) . '">';
    echo $this->text( 'Show me how' );
    echo '</a></p>';
    echo '</div>';

    $this->displayedTracker->trackDisplayed( $surface );
  }


  public function registerWpDashboardHook(): void {
    add_action(
      'admin_notices',
      function () {
        if ( ( $GLOBALS['pagenow'] ?? '' ) !== 'index.php' ) {
          return;
        }

        if ( ! current_user_can( WPML_CAP_MANAGE_TRANSLATIONS ) ) {
          return;
        }

        $this->render( TeaUpgradeFunnelProps::SURFACE_WP_DASHBOARD );
      }
    );
  }


  private function dashboardUrl(): string {
    if ( function_exists( 'admin_url' ) ) {
      return admin_url( 'admin.php?page=tm%2Fmenu%2Fmain.php' );
    }

    return 'admin.php?page=tm%2Fmenu%2Fmain.php';
  }


  private function text( string $text ): string {
    if ( function_exists( 'esc_html__' ) ) {
      return esc_html__( $text, 'wpml' );
    }

    return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
  }


  private function url( string $url ): string {
    if ( function_exists( 'esc_url' ) ) {
      return esc_url( $url );
    }

    return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
  }


}
