<?php

namespace WPML\Core\Component\PostHog\Application\Repository;

interface SetupWizardEventQueueRepositoryInterface {

  const QUEUE_SOURCE_SETUP_WIZARD = 'setup_wizard';

  const STATUS_PENDING   = 'pending';
  const STATUS_FAILED    = 'failed';
  const STATUS_DISCARDED = 'discarded';

  public function enqueue( array $entry );

  public function getPending( string $source );

  public function update( string $id, array $changes );

  public function remove( string $id );

  public function discardAll( string $source );

  public function deleteStale( int $olderThanSeconds );

  public function getAll();

}
