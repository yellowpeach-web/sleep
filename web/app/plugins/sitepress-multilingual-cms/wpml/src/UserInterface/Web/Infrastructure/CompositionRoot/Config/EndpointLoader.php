<?php

namespace WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config;

use Throwable;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\DicInterface;
use WPML\PHP\Exception\ClientMessageException;
use WPML\PHP\Exception\InvalidArgumentException as WpmlInvalidArgumentException;
use WPML\PHP\Exception\InvalidItemIdException;
use WPML\PHP\Exception\InvalidTypeException;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\Endpoint;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\PermissionInterface;
use function WPML\PHP\Logger\error as logError;

class EndpointLoader {

  const GENERIC_ERROR_MESSAGE = 'Internal server error.';

  const STATUS_BAD_REQUEST = 400;

  const LOGGED_TRACE_FRAMES = 5;

  private $endpoint;

  private $dic;

  private $api;

  private $outputBufferActive = false;


  public function __construct(
    Endpoint $endpoint,
    DicInterface $dic,
    ApiInterface $api
  ) {
    $this->endpoint = $endpoint;
    $this->dic      = $dic;
    $this->api      = $api;
    $this->register();
  }


  public function register() {
    $this->api->registerRoute(
      $this->endpoint,
      [ $this, 'handle' ],
      [ $this, 'authorisation' ]
    );
  }


  public function handle( $params ) {
    $handlerString = $this->endpoint->handler();

    if ( ! $handlerString ) {
      $this->api->responseJsonError( 'Endpoint handler missing.' );

      return;
    }

    $handler = $this->dic->make( $handlerString );

    try {
      $this->outputBufferActive = ob_start();

      $result = $handler->handle( $params );

      $this->outputBufferActive && ob_end_clean();
      $this->outputBufferActive = false;

      if ( isset( $result['status'] ) ) {
        $status = $result['status'];
        if ( is_int( $status ) ) {
          unset( $result['status'] );
          return $this->api->responseJsonWithStatusCode( $result, $status );
        }
      }

      return $this->api->responseJsonSuccess( $result );
    } catch ( Throwable $e ) {
      $this->outputBufferActive && ob_end_clean();
      $this->outputBufferActive = false;

      if ( self::isCallersFault( $e ) ) {
        return $this->api->responseJsonWithStatusCode( $e->getMessage(), self::STATUS_BAD_REQUEST );
      }

      logError(
        sprintf(
          'REST endpoint "%s" failed: %s: %s in %s:%d%s',
          $this->endpoint->id(),
          get_class( $e ),
          $e->getMessage(),
          self::relativePath( $e->getFile() ),
          $e->getLine(),
          $this->logsTraceFrames() ? self::traceFrames( $e ) : ''
        )
      );

      return $this->api->responseJsonError( self::GENERIC_ERROR_MESSAGE );
    }
  }


  protected function logsTraceFrames(): bool {
    return defined( 'WP_DEBUG' ) && WP_DEBUG;
  }


  private static function traceFrames( Throwable $e ): string {
    $frames = array_slice( explode( "\n", $e->getTraceAsString() ), 0, self::LOGGED_TRACE_FRAMES );

    return "\n" . implode( "\n", array_map( [ self::class, 'relativePath' ], $frames ) );
  }


  private static function relativePath( string $path ): string {
    return defined( 'WP_CONTENT_DIR' ) ? str_replace( WP_CONTENT_DIR . '/', '', $path ) : $path;
  }


  private static function isCallersFault( Throwable $e ) {
    return $e instanceof WpmlInvalidArgumentException
        || $e instanceof ClientMessageException
        || $e instanceof InvalidItemIdException
        || $e instanceof InvalidTypeException;
  }


  public function authorisation( $request = null ) {
    if ( ! $this->api->validateRequest( $this->endpoint->capabilities() ) ) {
      return false;
    }

    $permissionClass = $this->endpoint->permission();
    if ( ! $permissionClass ) {
      return true;
    }

    $permission = $this->dic->make( $permissionClass );

    return true === $permission->check( $request );
  }


}
