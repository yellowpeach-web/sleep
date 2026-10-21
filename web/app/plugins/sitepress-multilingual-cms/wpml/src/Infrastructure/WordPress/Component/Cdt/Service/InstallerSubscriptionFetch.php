<?php

namespace WPML\Infrastructure\WordPress\Component\Cdt\Service;

use WPML\Core\Component\Cdt\Application\Service\SubscriptionFetchInterface;

class InstallerSubscriptionFetch implements SubscriptionFetchInterface {

  const REFRESH = 'refresh_subscriptions_data';

  private $installer;


  public function __construct( $installer = null ) {
    $this->installer = is_callable( $installer ) ? $installer : [ $this, 'loadedInstaller' ];
  }


  public function isAvailable(): bool {
    return $this->installer() !== null;
  }


  public function fetch(): void {
    $installer = $this->installer();
    if ( $installer !== null ) {
      $refresh = [ $installer, self::REFRESH ];
      if ( is_callable( $refresh ) ) {
        call_user_func( $refresh );
      }
    }
  }


  public function loadedInstaller() {
    return class_exists( 'WP_Installer' ) ? \WP_Installer::instance() : null;
  }


  private function installer() {
    $installer = call_user_func( $this->installer );

    return is_object( $installer ) && method_exists( $installer, self::REFRESH ) ? $installer : null;
  }


}
