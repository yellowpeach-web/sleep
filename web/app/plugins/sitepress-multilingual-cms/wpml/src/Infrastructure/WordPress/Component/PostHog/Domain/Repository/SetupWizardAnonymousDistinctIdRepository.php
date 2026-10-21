<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Domain\Repository;

use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardAnonymousDistinctIdRepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class SetupWizardAnonymousDistinctIdRepository
  implements SetupWizardAnonymousDistinctIdRepositoryInterface {

  const OPTION_NAME = 'wpml_ph_anon_distinct_id';

  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  public function save( string $distinctId ) {
    $this->options->save( self::OPTION_NAME, $distinctId, false );
  }


  public function get() {
    $distinctId = $this->options->get( self::OPTION_NAME );

    return is_string( $distinctId ) && $distinctId !== '' ? $distinctId : false;
  }


}
