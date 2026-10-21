<?php

namespace WPML\WordPress;

use function WPML\PHP\Logger\error as logError;

class ClientSafeError {

  const GENERIC_MESSAGE = 'Internal server error.';


  public static function message( $boundary, $e ) {
    logError( sprintf( '%s failed: %s: %s', $boundary, get_class( $e ), $e->getMessage() ) );

    return self::GENERIC_MESSAGE;
  }


  public static function wpError( $boundary, $e, $code = 500, $status = null ) {
    $message = self::message( $boundary, $e );

    return null === $status
      ? new \WP_Error( $code, $message )
      : new \WP_Error( $code, $message, [ 'status' => $status ] );
  }


  public static function isDeliberateClientMessage( $e ) {
    return $e instanceof \WPML\PHP\Exception\ClientMessageException;
  }

}
