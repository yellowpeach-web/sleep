<?php
namespace WPML\UserInterface\Web\Infrastructure\WordPress\Port\Hook;

use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Hook\DashboardTranslatablePostTypesFilterInterface;

class DashboardTranslatablePostTypesFilter implements DashboardTranslatablePostTypesFilterInterface {
  const NAME = 'wpml_tm_dashboard_translatable_types';


  public function filter( array $postTypes ) {
    if ( isset( $postTypes['attachment'] ) ) {
      unset( $postTypes['attachment'] );
    }

    if ( isset( $postTypes['product_variation'] ) ) {
      unset( $postTypes['product_variation'] );
    }

    return apply_filters( static::NAME, $postTypes );
  }


}
