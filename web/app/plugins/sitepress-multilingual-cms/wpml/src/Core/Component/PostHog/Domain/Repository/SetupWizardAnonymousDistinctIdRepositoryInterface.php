<?php

namespace WPML\Core\Component\PostHog\Domain\Repository;

interface SetupWizardAnonymousDistinctIdRepositoryInterface {


  public function save( string $distinctId );


  public function get();


}
