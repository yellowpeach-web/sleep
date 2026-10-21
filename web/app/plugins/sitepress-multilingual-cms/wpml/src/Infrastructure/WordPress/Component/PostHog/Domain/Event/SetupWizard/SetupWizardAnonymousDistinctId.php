<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Domain\Event\SetupWizard;

use WPML\Core\Component\PostHog\Domain\Event\SetupWizard\SetupWizardAnonymousDistinctIdInterface;
use WPML\Core\Component\PostHog\Domain\Repository\SetupWizardAnonymousDistinctIdRepositoryInterface;

class SetupWizardAnonymousDistinctId implements SetupWizardAnonymousDistinctIdInterface {

  const PREFIX = 'wpml_anon_';

  private $repository;


  public function __construct( SetupWizardAnonymousDistinctIdRepositoryInterface $repository ) {
    $this->repository = $repository;
  }


  public function get(): string {
    $distinctId = $this->repository->get();

    if ( is_string( $distinctId ) ) {
      return $distinctId;
    }

    $distinctId = self::PREFIX . $this->generateUUID4();
    $this->repository->save( $distinctId );

    return $distinctId;
  }


  protected function generateUUID4(): string {
    return wp_generate_uuid4();
  }


}
