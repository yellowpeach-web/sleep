<?php

namespace WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository;

use WPML\Core\Component\PostHog\Application\Repository\SetupWizardEventQueueRepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class SetupWizardEventQueueRepository implements SetupWizardEventQueueRepositoryInterface {

  const OPTION_KEY = 'wpml_posthog_setup_wizard_event_queue';

  const MAX_EVENTS_PER_SOURCE = 50;

  const MAX_TOTAL_PER_SOURCE = 150;

  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  public function enqueue( array $entry ) {
    $entries = $this->getAll();
    $entries[] = $entry;

    $entries = $this->evictOverflow( $entries, $entry['source'] );

    $this->saveAll( $entries );
  }


  public function getPending( string $source ) {
    $pending = array_filter(
      $this->getAll(),
      function ( array $entry ) use ( $source ) {
        return $entry['source'] === $source
               && $entry['status'] === self::STATUS_PENDING;
      }
    );

    usort(
      $pending,
      function ( array $a, array $b ) {
        return $a['created_at'] <=> $b['created_at'];
      }
    );

    return $pending;
  }


  public function update( string $id, array $changes ) {
    $entries = $this->getAll();

    foreach ( $entries as $index => $entry ) {
      if ( $entry['id'] === $id ) {
        $entries[ $index ] = $this->normalise( array_merge( $entry, $changes ) );
        break;
      }
    }

    $this->saveAll( $entries );
  }


  public function remove( string $id ) {
    $this->saveAll(
      array_values(
        array_filter(
          $this->getAll(),
          function ( array $entry ) use ( $id ) {
            return $entry['id'] !== $id;
          }
        )
      )
    );
  }


  public function discardAll( string $source ) {
    $entries = $this->getAll();

    foreach ( $entries as $index => $entry ) {
      if ( $entry['source'] === $source ) {
        $entries[ $index ]['status'] = self::STATUS_DISCARDED;
      }
    }

    $this->saveAll( $entries );
  }


  public function deleteStale( int $olderThanSeconds ) {
    $cutoff = time() - $olderThanSeconds;
    $kept   = [];
    $removed = 0;

    foreach ( $this->getAll() as $entry ) {
      if ( $entry['created_at'] < $cutoff ) {
        $removed++;
        continue;
      }
      $kept[] = $entry;
    }

    $this->saveAll( $kept );

    return $removed;
  }


  public function getAll() {
    $stored = $this->options->get( self::OPTION_KEY, null );

    if ( ! is_array( $stored ) ) {
      return [];
    }

    $entries = [];

    foreach ( $stored as $entry ) {
      if ( is_array( $entry ) ) {
        $entries[] = $this->normalise( $entry );
      }
    }

    return $entries;
  }


  private function normalise( array $entry ) {
    $properties = isset( $entry['properties'] ) && is_array( $entry['properties'] )
      ? $entry['properties'] : [];

    $personProps = isset( $entry['person_props'] ) && is_array( $entry['person_props'] )
      ? $entry['person_props'] : [];

    return [
      'id'           => isset( $entry['id'] ) && is_string( $entry['id'] )
        ? $entry['id'] : '',
      'source'       => isset( $entry['source'] ) && is_string( $entry['source'] )
        ? $entry['source'] : '',
      'event_name'   => isset( $entry['event_name'] ) && is_string( $entry['event_name'] )
        ? $entry['event_name'] : '',
      'properties'   => $properties,
      'person_props' => $personProps,
      'created_at'   => isset( $entry['created_at'] ) && is_int( $entry['created_at'] )
        ? $entry['created_at'] : 0,
      'status'       => isset( $entry['status'] ) && is_string( $entry['status'] )
        ? $entry['status'] : self::STATUS_PENDING,
      'retry_count'  => isset( $entry['retry_count'] ) && is_int( $entry['retry_count'] )
        ? $entry['retry_count'] : 0,
      'last_error'   => isset( $entry['last_error'] ) && is_string( $entry['last_error'] )
        ? $entry['last_error'] : '',
    ];
  }


  private function evictOverflow( array $entries, string $source ) {
    $pendingOfSource = array_filter(
      $entries,
      function ( array $entry ) use ( $source ) {
        return $entry['source'] === $source
               && $entry['status'] === self::STATUS_PENDING;
      }
    );

    if ( count( $pendingOfSource ) <= self::MAX_EVENTS_PER_SOURCE ) {
      return $this->enforceTotalCeiling( array_values( $entries ), $source );
    }

    usort(
      $pendingOfSource,
      function ( array $a, array $b ) {
        return $a['created_at'] <=> $b['created_at'];
      }
    );

    $evictedId = $pendingOfSource[0]['id'];

    return $this->enforceTotalCeiling(
      array_values(
        array_filter(
          $entries,
          function ( array $entry ) use ( $evictedId ) {
            return $entry['id'] !== $evictedId;
          }
        )
      ),
      $source
    );
  }


  private function enforceTotalCeiling( array $entries, string $source ) {
    $ofSource = array_filter(
      $entries,
      function ( array $entry ) use ( $source ) {
        return $entry['source'] === $source;
      }
    );

    $excess = count( $ofSource ) - self::MAX_TOTAL_PER_SOURCE;

    if ( $excess <= 0 ) {
      return $entries;
    }

    $nonPending = array_filter(
      $ofSource,
      function ( array $entry ) {
        return $entry['status'] !== self::STATUS_PENDING;
      }
    );

    usort(
      $nonPending,
      function ( array $a, array $b ) {
        return $a['created_at'] <=> $b['created_at'];
      }
    );

    $dropIds = [];

    foreach ( array_slice( $nonPending, 0, $excess ) as $entry ) {
      $dropIds[ $entry['id'] ] = true;
    }

    return array_values(
      array_filter(
        $entries,
        function ( array $entry ) use ( $dropIds ) {
          return ! isset( $dropIds[ $entry['id'] ] );
        }
      )
    );
  }


  private function saveAll( array $entries ) {
    $this->options->save( self::OPTION_KEY, $entries, false );
  }

}
