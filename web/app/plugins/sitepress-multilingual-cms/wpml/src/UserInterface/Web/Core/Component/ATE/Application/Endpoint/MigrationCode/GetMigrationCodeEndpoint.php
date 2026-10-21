<?php

namespace WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\MigrationCode;

use WPML\Core\Port\Endpoint\EndpointInterface;

class GetMigrationCodeEndpoint implements EndpointInterface {

  private $migrationCodeProvider;


  public function __construct( MigrationCodeProviderInterface $migrationCodeProvider ) {
    $this->migrationCodeProvider = $migrationCodeProvider;
  }


  public function handle( $requestData = null ): array {
    try {
      return [
        'success' => true,
        'data'    => $this->migrationCodeProvider->getCode(),
      ];
    } catch ( \Throwable $e ) {
      return [
        'success' => false,
        'message' => 'Failed to fetch migration code.',
      ];
    }
  }


}
