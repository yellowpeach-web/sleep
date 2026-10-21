<?php

namespace WPML\Infrastructure\WordPress\Component\ATE\Application\Repository;

use WPML\Core\SharedKernel\Component\ATE\Application\Repository\AteReachabilityRepositoryInterface;
use WPML\Infrastructure\WordPress\Port\Persistence\Options;

class AteReachabilityRepository implements AteReachabilityRepositoryInterface {

  const OPTION_KEY = 'wpml_ate_reachable';

  const UNSET = '__unset__';

  private $options;


  public function __construct( Options $options ) {
    $this->options = $options;
  }


  public function get(): ?bool {
    $value = $this->options->get( self::OPTION_KEY, self::UNSET );

    if ( $value === self::UNSET ) {
      return null;
    }

    return $value === '1';
  }


  public function save( bool $reachable ): void {
    $this->options->save( self::OPTION_KEY, $reachable ? '1' : '0' );
  }


}
