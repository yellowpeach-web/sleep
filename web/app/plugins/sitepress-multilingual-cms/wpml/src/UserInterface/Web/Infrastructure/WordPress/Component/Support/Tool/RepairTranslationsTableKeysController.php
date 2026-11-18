<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\Core\Port\Endpoint\EndpointInterface;


class RepairTranslationsTableKeysController implements EndpointInterface {


  public function handle( $requestData = null ): array {
      $engine = TranslationsTableKeysController::engine();
    if ( $engine === null ) {
        return [
          'done'    => false,
          'message' => TranslationsTableKeysController::refusalMessage( 'already-right', '', '' ),
        ];
    }

      $result = $engine->repair( time() );

    if ( $result['done'] || $result['refusal'] === 'already-right' ) {
      \WPML\Troubleshooting\TranslationsTableKeysNotice::recheckNow();
    }

      return [
        'done'    => $result['done'],
        'message' => TranslationsTableKeysController::resultMessage( $result ),
      ];
  }


}
