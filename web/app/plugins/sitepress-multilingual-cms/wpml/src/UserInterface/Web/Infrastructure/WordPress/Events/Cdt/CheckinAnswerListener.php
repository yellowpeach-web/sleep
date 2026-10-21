<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt;

use WPML\Core\Component\Cdt\Application\Service\AnswerReader;

class CheckinAnswerListener {

  const REPOSITORY = 'wpml';

  private $answerReader;


  public function __construct( AnswerReader $answerReader ) {
    $this->answerReader = $answerReader;
  }


  public function onFetched( $response, $repositoryId ): void {
    if ( $repositoryId !== self::REPOSITORY || ! is_object( $response ) || ! isset( $response->subscription_data ) ) {
      return;
    }

    $subscription = $response->subscription_data;
    $answer       = is_object( $subscription ) && isset( $subscription->cdt )
      ? $subscription->cdt
      : ( is_array( $subscription ) && isset( $subscription['cdt'] ) ? $subscription['cdt'] : null );

    if ( ! is_object( $answer ) && ! is_array( $answer ) ) {
      return;
    }

    try {
      $map = (array) json_decode( (string) json_encode( $answer ), true );
      $this->answerReader->read( $map, time() );
    } catch ( \Throwable $e ) {
      return;
    }
  }


}
