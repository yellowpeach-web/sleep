<?php

namespace WPML\Infrastructure\WordPress\SharedKernel\Support;

use WPML\Core\SharedKernel\Component\Support\Application\Repository\RepairToolsPluginInterface;

class RepairToolsPlugin implements RepairToolsPluginInterface {

  const PLUGIN_FILE = 'wpml-troubleshooting/plugin.php';


  public function isInstalled(): bool {
    return file_exists( WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE );
  }


  public function isActive(): bool {
    $this->loadPluginApi();

    return is_plugin_active( self::PLUGIN_FILE );
  }


  public function activate(): bool {
    if ( ! $this->isInstalled() ) {
      return false;
    }
    $this->loadPluginApi();

    $result = activate_plugin( self::PLUGIN_FILE );

    return ! is_wp_error( $result );
  }


  private function loadPluginApi(): void {
    if ( ! function_exists( 'activate_plugin' ) ) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
  }
}
