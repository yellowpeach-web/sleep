<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Billing;

use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeFunnelProps;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Ate\AmsWidgetEmbed;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Ate\AteAutoRegister;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Notices\TeaUpgrade\TeaUpgradeCompactNoticeRenderer;

class BillingController implements PageRenderInterface {

  private $teaUpgradeCompactNoticeRenderer;


  public function __construct( TeaUpgradeCompactNoticeRenderer $teaUpgradeCompactNoticeRenderer ) {
    $this->teaUpgradeCompactNoticeRenderer = $teaUpgradeCompactNoticeRenderer;
    AteAutoRegister::attempt();
    add_action( 'admin_enqueue_scripts', array( AmsWidgetEmbed::class, 'enqueue' ) );
  }


  public function render(): void {
    $this->teaUpgradeCompactNoticeRenderer->render( TeaUpgradeFunnelProps::SURFACE_PAYMENTS_TAB );

    echo '<div class="wrap">';
    echo '<div class="wpml:max-w-6xl wpml:mt-6 wpml:pb-16">';
    /* translators: Name of the WPML billing screen: the admin menu item and the page heading. */
    echo '<h1>' . esc_html__( 'AI Translation: billing and usage', 'wpml' ) . '</h1>';

    echo '<style>wpml-ams-widget[route="billing"] h1.heading{display:none!important}</style>';

    AmsWidgetEmbed::renderContainerFitStyle( 'billing' );

    AmsWidgetEmbed::renderWithLoadingReveal(
      'wpml-ai-billing',
      'wpml-ams-widget[route="billing"]',
      __( 'Loading billing information…', 'wpml' ),
      static function () {
        AmsWidgetEmbed::renderElement( 'billing' );
      },
      40,
      true
    );

    echo '</div></div>';
  }


}
