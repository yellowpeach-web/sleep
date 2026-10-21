<?php

namespace WPML\UserInterface\Web\Legacy\Component\ATE;

use WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\MigrationCode\MigrationCodeProviderInterface;

use function WPML\Container\make;

class MigrationCodeProvider implements MigrationCodeProviderInterface {


  public function getCode(): string {
    $result = make( \WPML_TM_AMS_API::class )->getWebsiteMigrationCode();

    if ( is_wp_error( $result ) || ! is_array( $result ) || ! isset( $result['code'] ) ) {
      return __( 'Error getting migration code.', 'wpml' );
    }

    return (string) $result['code'];
  }


}
