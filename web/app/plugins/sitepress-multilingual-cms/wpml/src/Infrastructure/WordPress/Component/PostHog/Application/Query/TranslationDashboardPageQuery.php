<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Application\Query;

use WPML\Core\Component\PostHog\Application\Query\TranslationDashboardPageQueryInterface;

class TranslationDashboardPageQuery implements TranslationDashboardPageQueryInterface {

  const DASHBOARD_PAGE = 'tm/menu/main.php';


  public function isCurrent(): bool {
    if ( ! is_admin() || ! isset( $_GET['page'] ) || ! is_string( $_GET['page'] ) ) {
      return false;
    }

    $page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
    if ( $page !== self::DASHBOARD_PAGE ) {
      return false;
    }

    if ( ! isset( $_GET['sm'] ) ) {
      return true;
    }

    if ( ! is_string( $_GET['sm'] ) ) {
      return false;
    }

    return sanitize_text_field( wp_unslash( $_GET['sm'] ) ) === 'dashboard';
  }


}
