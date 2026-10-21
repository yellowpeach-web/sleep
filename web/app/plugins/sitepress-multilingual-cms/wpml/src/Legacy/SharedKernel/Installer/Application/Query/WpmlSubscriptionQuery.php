<?php

namespace WPML\Legacy\SharedKernel\Installer\Application\Query;

use WPML\Core\SharedKernel\Component\Installer\Application\Query\WpmlSubscriptionQueryInterface;

class WpmlSubscriptionQuery implements WpmlSubscriptionQueryInterface {

  private $installer = null;


  public function __construct() {
    if ( class_exists( 'WP_Installer' ) ) {
      $this->installer = \WP_Installer::instance();
    }
  }


  public function isValid(): bool {
    if ( ! $this->installer ) {
      return false;
    }

    if ( ! $this->installer->get_repositories() ) {
      $this->installer->load_repositories_list();
    }

    if ( ! $this->installer->get_settings() ) {
      $this->installer->save_settings();
    }

    return $this->installer->repository_has_valid_subscription( 'wpml' );
  }


}
