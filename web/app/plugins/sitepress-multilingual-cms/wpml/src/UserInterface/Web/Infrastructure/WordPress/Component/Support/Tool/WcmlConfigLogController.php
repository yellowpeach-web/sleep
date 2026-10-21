<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Wcml\WcmlEmbed;

class WcmlConfigLogController implements PageRenderInterface {


  const WIDTH_CLASS = 'wpml:max-w-5xl';


  public function render() {
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php esc_html_e( 'WCML configuration log', 'wpml' ); ?>
      </h1>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php
        esc_html_e( "Read-only snapshot of your WCML configuration. Copy the contents below when WPML Support asks for your WCML state — it's the same dump the legacy Troubleshooting page produced.", 'wpml' );
        ?>
      </p>

      <div class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5">
        <?php WcmlEmbed::embed( 'wcml-config-log' ); ?>
      </div>
      <?php
  }


}
