<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Notices\TeaUpgrade;

use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeDisplayedTracker;
use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeFunnelProps;
use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeNoticeDataProvider;

class TeaUpgradeCompactNoticeRenderer {

  const DISMISS_ROUTE = '/wpml/v1/dismiss-tea-upgrade-notice';

  private $noticeDataProvider;

  private $displayedTracker;

  private $dismissScriptEnqueued = false;


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
    echo '<p><strong>'
         /* translators: Heading of the notice about the new automatic translation pricing, shown on the WordPress Dashboard and on WPML's AI Translation pages. */
         . esc_html__( 'Your automatic translation pricing has been upgraded', 'wpml' )
         . '</strong></p>';
    echo '<p>'
         /* translators: Body of the notice about the new automatic translation pricing. PTC is a product name. "Translations Dashboard" is the WPML → Translations → Dashboard page. */
         . esc_html__( 'Automatic translation has new, lower pricing with PTC. See what changes on the Translations Dashboard.', 'wpml' )
         . '</p>';
    echo '<p><a class="button button-primary" href="' . $this->url( $this->dashboardUrl() ) . '">';
    /* translators: Button in the notice about the new automatic translation pricing. It opens the Translations Dashboard, which explains the setup. */
    echo esc_html__( 'Show me how', 'wpml' );
    echo '</a> ';
    echo '<button type="button" class="button button-secondary wpml-tea-upgrade-compact-notice__dismiss"'
         . ' data-testid="tea-upgrade-compact-notice-dismiss"'
         . ' data-surface="' . esc_attr( $surface ) . '">';
    /* translators: Button in the notice about the new automatic translation pricing. It hides the notice for good, for the current user only. */
    echo esc_html__( "Don't show again", 'wpml' );
    echo '</button> ';
    echo '<span class="wpml-tea-upgrade-compact-notice__status" role="status"></span>';
    echo '</p>';
    echo '</div>';

    $this->displayedTracker->trackDisplayed( $surface );
    $this->enqueueDismissScript();
  }


  private function enqueueDismissScript(): void {
    if ( $this->dismissScriptEnqueued ) {
      return;
    }
    $this->dismissScriptEnqueued = true;

    wp_enqueue_script( 'wp-api-fetch' );
    $config = wp_json_encode(
      [
        'route' => self::DISMISS_ROUTE,
        /* translators: Status next to the "Don't show again" button in the notice about the new automatic translation pricing, when hiding the notice did not work. */
        'error' => __( "Couldn't hide the notice. Try again.", 'wpml' ),
      ]
    );
    $js = '(function(){'
      . 'if(window.wpmlTeaUpgradeCompactDismiss){return;}window.wpmlTeaUpgradeCompactDismiss=true;'
      . "var C=$config;"
      . "document.addEventListener('click',function(e){"
      . "var b=e.target&&e.target.closest?e.target.closest('.wpml-tea-upgrade-compact-notice__dismiss'):null;"
      . 'var f=window.wp&&wp.apiFetch;if(!b||b.disabled||!f){return;}'
      . "var n=b.closest('.wpml-tea-upgrade-compact-notice');"
      . "var s=n?n.querySelector('.wpml-tea-upgrade-compact-notice__status'):null;"
      . "b.disabled=true;b.setAttribute('aria-busy','true');if(s){s.textContent='';}"
      . "f({path:C.route,method:'POST',data:{surface:b.getAttribute('data-surface')}})"
      . ".then(function(r){if(r&&r.success){if(n&&n.parentNode){n.parentNode.removeChild(n);}return;}throw new Error('not-dismissed');})"
      . ".catch(function(){b.disabled=false;b.removeAttribute('aria-busy');if(s){s.textContent=C.error;}});"
      . '});'
      . '})();';
    wp_add_inline_script( 'wp-api-fetch', $js );
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


  private function url( string $url ): string {
    if ( function_exists( 'esc_url' ) ) {
      return esc_url( $url );
    }

    return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
  }


}
