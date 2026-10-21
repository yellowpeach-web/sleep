<?php

namespace WPML\Infrastructure\WordPress\Component\Cdt\Query;

use WPML\Core\Component\Cdt\Application\Query\RequestContextQueryInterface;

class WpRequestContext implements RequestContextQueryInterface {

  private $probe;


  public function __construct( $probe = null ) {
    $this->probe = is_callable( $probe ) ? $probe : [ $this, 'wordPressSays' ];
  }


  public function isAdminPageLoad(): bool {
    foreach ( [ 'cron', 'cli', 'rest', 'ajax', 'admin_post' ] as $check ) {
      if ( call_user_func( $this->probe, $check ) ) {
        return false;
      }
    }

    return (bool) call_user_func( $this->probe, 'admin' );
  }


  public function wordPressSays( string $check ): bool {
    switch ( $check ) {
      case 'cron':
        return wp_doing_cron();
      case 'cli':
        return defined( 'WP_CLI' ) && WP_CLI;
      case 'rest':
        return defined( 'REST_REQUEST' );
      case 'ajax':
        return wp_doing_ajax();
      case 'admin_post':
        return isset( $GLOBALS['pagenow'] ) && 'admin-post.php' === $GLOBALS['pagenow'];
      case 'admin':
        return is_admin();
      default:
        return false;
    }
  }


}
