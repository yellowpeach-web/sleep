<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Wcml\WcmlEmbed;

class WcmlStatusController implements PageRenderInterface {


  const WIDTH_CLASS = 'wpml:max-w-5xl';


  public function render() {
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php /* translators: Name of a Support tool: its entry in the tool list on WPML → Support and the heading of its own screen. WCML is the short name of WooCommerce Multilingual. */ esc_html_e( 'WCML status', 'wpml' ); ?>
      </h1>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php
        esc_html_e( 'Status checks for the WooCommerce Multilingual & Multicurrency add-on. Confirms store pages, products and taxonomies are translated and that the supporting plugins are active.', 'wpml' );
        ?>
      </p>

      <div class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5">
        <?php WcmlEmbed::embed( 'wcml-status' ); ?>
      </div>
      <?php
  }


}
