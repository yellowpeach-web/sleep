<?php

namespace WPML\Legacy\Component\Cdt\Query;

use WPML\Core\Component\Cdt\Application\Query\LiveAteHealthQueryInterface;
use WPML\Core\Component\Cdt\Domain\LateMode;
use WPML\Core\Port\Persistence\OptionsInterface;

class LiveAteHealthQuery implements LiveAteHealthQueryInterface {

  const PULL_DELIVERY_STATE   = 'wpml_pull_delivery_state';
  const RECONNECT_CODE_LENGTH = 32;

  private $options;

  private $reconnectCode;


  public function __construct( OptionsInterface $options, $reconnectCode = null ) {
    $this->options       = $options;
    $this->reconnectCode = is_callable( $reconnectCode ) ? $reconnectCode : null;
  }


  public function getLateMode(): string {
    $state = $this->state();

    return LateMode::normalize( $state['mode'] ?? null );
  }


  public function getOldestPendingHours( int $now ): ?float {
    $state  = $this->state();
    $oldest = isset( $state['oldest_automatic_pending_at'] ) && is_numeric( $state['oldest_automatic_pending_at'] )
      ? (int) $state['oldest_automatic_pending_at']
      : 0;

    if ( $oldest <= 0 ) {
      return null;
    }

    return round( max( 0, $now - $oldest ) / 3600, 2 );
  }


  public function getReconnectCode(): ?string {
    $code = $this->reconnectCode ? call_user_func( $this->reconnectCode ) : $this->diagnosticCode();
    $code = is_string( $code ) ? (string) substr( $code, 0, self::RECONNECT_CODE_LENGTH ) : '';

    return $code === '' ? null : $code;
  }


  private function state(): array {
    $state = $this->options->get( self::PULL_DELIVERY_STATE, [] );

    return is_array( $state ) ? $state : [];
  }


  private function diagnosticCode(): string {
    if ( ! class_exists( \WPML\TM\ATE\ClonedSites\ReconnectState::class ) ) {
      return '';
    }

    if ( ! \WPML\TM\ATE\ClonedSites\ReconnectState::get() ) {
      return '';
    }

    $websiteUuid = function_exists( 'wpml_get_site_id' ) ? (string) wpml_get_site_id( \WPML_TM_ATE::SITE_ID_SCOPE ) : '';

    return \WPML\TM\ATE\ClonedSites\ReconnectState::diagnosticCode( $websiteUuid );
  }


}
