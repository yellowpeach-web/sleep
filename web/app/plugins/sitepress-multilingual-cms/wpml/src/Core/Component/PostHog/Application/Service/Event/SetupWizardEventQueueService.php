<?php

namespace WPML\Core\Component\PostHog\Application\Service\Event;

use WPML\Core\Component\PostHog\Application\Repository\SetupWizardEventQueueLockInterface;
use WPML\Core\Component\PostHog\Application\Repository\SetupWizardEventQueueRepositoryInterface;
use WPML\Core\Component\PostHog\Domain\Event\EventInterface;

class SetupWizardEventQueueService {

  const MAX_REPLAY_RETRIES = 3;

  const STALE_AFTER_SECONDS = 604800;

  const LOCK_ATTEMPTS         = 5;
  const LOCK_ATTEMPTS_DISCARD = 10;
  const LOCK_RETRY_DELAY_US   = 100000;

  private $queueRepository;

  private $enricher;

  private $lock;


  public function __construct(
    SetupWizardEventQueueRepositoryInterface $queueRepository,
    SetupWizardEventEnricher $enricher,
    SetupWizardEventQueueLockInterface $lock
  ) {
    $this->queueRepository = $queueRepository;
    $this->enricher        = $enricher;
    $this->lock            = $lock;
  }


  private function acquireLock( int $attempts ) {
    for ( $i = 0; $i < $attempts; $i++ ) {
      if ( $this->lock->acquire() ) {
        return true;
      }
      if ( $i < $attempts - 1 ) {
        usleep( self::LOCK_RETRY_DELAY_US );
      }
    }

    return false;
  }


  public function enqueue(
    EventInterface $event,
    array $personProps,
    string $distinctId,
    string $sessionId
  ): bool {
    if ( ! $this->enricher->enrich( $event ) ) {
      return false;
    }

    if ( ! $this->acquireLock( self::LOCK_ATTEMPTS ) ) {
      return false;
    }

    try {
      $this->queueRepository->deleteStale( self::STALE_AFTER_SECONDS );

      $properties = $event->getProperties();
      $properties['distinct_id'] = $distinctId;
      $properties['session_id']  = $sessionId;

      $this->queueRepository->enqueue(
        [
          'id'          => uniqid( 'wpml_ph_q_', true ),
          'source'      => SetupWizardEventQueueRepositoryInterface::QUEUE_SOURCE_SETUP_WIZARD,
          'event_name'  => $event->getName(),
          'properties'  => $properties,
          'person_props' => $personProps,
          'created_at'  => time(),
          'status'      => SetupWizardEventQueueRepositoryInterface::STATUS_PENDING,
          'retry_count' => 0,
          'last_error'  => '',
        ]
      );
    } finally {
      $this->lock->release();
    }

    return true;
  }


  public function getPending() {
    return $this->queueRepository->getPending(
      SetupWizardEventQueueRepositoryInterface::QUEUE_SOURCE_SETUP_WIZARD
    );
  }


  public function markSuccess( string $id ) {
    if ( ! $this->acquireLock( self::LOCK_ATTEMPTS ) ) {
      return;
    }

    try {
      $this->queueRepository->remove( $id );
    } finally {
      $this->lock->release();
    }
  }


  public function markFailed( string $id, string $error ) {
    if ( ! $this->acquireLock( self::LOCK_ATTEMPTS ) ) {
      return;
    }

    try {
      foreach ( $this->getPending() as $entry ) {
        if ( $entry['id'] !== $id ) {
          continue;
        }

        $retryCount = $entry['retry_count'] + 1;
        $changes    = [
          'retry_count' => $retryCount,
          'last_error'  => $error,
        ];

        if ( $retryCount >= self::MAX_REPLAY_RETRIES ) {
          $changes['status'] = SetupWizardEventQueueRepositoryInterface::STATUS_FAILED;
        }

        $this->queueRepository->update( $id, $changes );
        return;
      }
    } finally {
      $this->lock->release();
    }
  }


  public function discard() {
    $locked = $this->acquireLock( self::LOCK_ATTEMPTS_DISCARD );

    try {
      $this->queueRepository->discardAll(
        SetupWizardEventQueueRepositoryInterface::QUEUE_SOURCE_SETUP_WIZARD
      );
    } finally {
      if ( $locked ) {
        $this->lock->release();
      }
    }
  }


  public function cleanupStale() {
    if ( ! $this->acquireLock( self::LOCK_ATTEMPTS ) ) {
      return 0;
    }

    try {
      return $this->queueRepository->deleteStale( self::STALE_AFTER_SECONDS );
    } finally {
      $this->lock->release();
    }
  }

}
