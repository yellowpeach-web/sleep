<?php

namespace WPML\Legacy\Component\Translation\Sender\Taxonomy;

use WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomySendOperationRepository;
use WPML\Core\SharedKernel\Component\Translation\Domain\TaxonomyBatchCommitterInterface;

class TaxonomySendOperationSweeper {

  const OPERATIONS_REMOVED = 5;
  const ROWS_INSPECTED     = 20;
  const GRACE_SECONDS      = 3600;
  const DISPATCH_UNCERTAIN_OPTION_PREFIX = 'wpml.taxonomy_term_dispatch_uncertain_';
  const DISPATCH_SWEEP_CURSOR_OPTION     = 'wpml.taxonomy_term_dispatch_sweep_cursor';

  private $wpdb;

  private $translationProxyService;

  private $repository;

  private $releaseOpenMembers;


  public function __construct(
    ?\wpdb $wpdb = null,
    ?TaxonomyBatchCommitterInterface $translationProxyService = null,
    ?TaxonomySendOperationRepository $repository = null,
    ?callable $releaseOpenMembers = null
  ) {
    $this->wpdb                    = $wpdb ?: $GLOBALS['wpdb'];
    $this->translationProxyService = $translationProxyService;
    $this->repository              = $repository ?: new TaxonomySendOperationRepository();
    $this->releaseOpenMembers      = $releaseOpenMembers ?: function ( array $records ): int {
      return TaxonomyTermBundleRounds::releaseRecords( $this->wpdb, $records );
    };
  }


  public function sweep( int $now = 0, bool $commitAbandoned = false ): int {
    $now = $now ?: time();
    if ( $commitAbandoned ) {
      return \WPML\TM\Jobs\JobLog::runAsBackgroundWork(
        'taxonomy_send_cleanup',
        function () use ( $now ) {
          return \WPML\TM\Jobs\JobLog::runInGroup(
            \WPML\TM\Jobs\JobLog::GROUP_ID_SEND_JOBS,
            'Taxonomy send cleanup',
            function () use ( $now ) {
              return $this->sweepOperations( $now, true );
            }
          );
        }
      );
    }

    return $this->sweepOperations( $now, $commitAbandoned );
  }


  private function sweepOperations( int $now, bool $commitAbandoned ): int {
    $wpdb = $this->wpdb;

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND CHAR_LENGTH( option_name ) = %d ORDER BY option_id ASC LIMIT %d",
        $wpdb->esc_like( TaxonomySendOperationRepository::OPTION_PREFIX ) . '%',
        strlen( TaxonomySendOperationRepository::OPTION_PREFIX ) + 32,
        self::ROWS_INSPECTED
      ),
      ARRAY_A
    );
    if ( ! is_array( $rows ) ) {
      return 0;
    }

    $removed         = 0;
    $remoteCommits  = 0;
    $minimumAttempts = $this->minimumCommitAttempts( $rows, $now );
    foreach ( $rows as $row ) {
      if ( $removed >= self::OPERATIONS_REMOVED ) {
        break;
      }

      $name = (string) ( $row['option_name'] ?? '' );
      if ( ! $this->isOperationRow( $name ) ) {
        continue;
      }

      $storedValue = (string) ( $row['option_value'] ?? '' );
      $operation   = \WPML\Utils\Unserialize::maybe( $storedValue );
      if ( $this->expiresAt( $operation ) + self::GRACE_SECONDS >= $now ) {
        continue;
      }

      if ( $this->hasUncommittedBatch( $operation ) ) {
        if ( ! $commitAbandoned ) {
          continue;
        }
        $attempts = $operation['commitAttempts'] ?? 0;
        if ( $attempts >= 3 ) {
          \WPML\TM\Jobs\JobLog::addError(
            'taxonomy_send_operation_commit_abandoned:' . $operation['batchName'] . ':' . $operation['batchId']
          );
        } else {
          if ( $remoteCommits > 0 || $attempts > $minimumAttempts ) {
            continue;
          }
          $remoteCommits++;
          $operationId = substr( $name, strlen( TaxonomySendOperationRepository::OPTION_PREFIX ) );
          $committed = $this->translationProxyService()->commit(
            $operation['batchName'],
            $operation['batchId']
          );
          if ( ! $committed
            && $this->repository->recordTpCommitAttempt( $operationId, $operation['batchName'], $operation['batchId'] ) > 0
          ) {
            continue;
          }

          if ( $committed ) {
            if ( ! $this->repository->markTpBatchCommitted( $operationId, $operation['batchName'], $operation['batchId'] ) ) {
              continue;
            }
            $operation['batchCommitted'] = true;
            $storedValue = serialize( $operation );
          }
        }
      }

      if ( ! $this->deleteIfUnchanged( $name, $storedValue ) ) {
        continue;
      }

      wp_cache_delete( $name, 'options' );
      $removed++;

      if ( is_array( $operation ) && is_array( $operation['openBundleMembers'] ?? null ) && $operation['openBundleMembers'] ) {
        try {
          call_user_func( $this->releaseOpenMembers, array_values( $operation['openBundleMembers'] ) );
        } catch ( \Throwable $e ) {
          \WPML\TM\Jobs\JobLog::addError( 'taxonomy_send_operation_members_release_failed:' . $name );
        }
      }
    }

    $this->sweepResolvedOrOrphanedDispatchMarkers();

    return $removed;
  }


  private function minimumCommitAttempts( iterable $rows, int $now ): int {
    $minimum = PHP_INT_MAX;
    foreach ( $rows as $row ) {
      $operation = \WPML\Utils\Unserialize::maybe( (string) ( $row['option_value'] ?? '' ) );
      if ( $this->expiresAt( $operation ) + self::GRACE_SECONDS < $now
        && $this->hasUncommittedBatch( $operation )
      ) {
        $minimum = min( $minimum, $operation['commitAttempts'] ?? 0 );
      }
    }
    return $minimum;
  }


  private function hasUncommittedBatch( $operation ): bool {
    return is_array( $operation )
      && empty( $operation['batchCommitted'] )
      && is_string( $operation['batchName'] ?? null )
      && '' !== $operation['batchName']
      && is_int( $operation['batchId'] ?? null )
      && $operation['batchId'] > 0;
  }


  private function translationProxyService(): TaxonomyBatchCommitterInterface {
    if ( null === $this->translationProxyService ) {
      $this->translationProxyService = \WPML\Container\make( TaxonomyBatchCommitterInterface::class );
    }
    return $this->translationProxyService;
  }


  private function sweepResolvedOrOrphanedDispatchMarkers(): void {
    $wpdb = $this->wpdb;
    $storedCursor = get_option( self::DISPATCH_SWEEP_CURSOR_OPTION, 0 );
    $cursor       = is_int( $storedCursor )
      ? max( 0, $storedCursor )
      : ( is_string( $storedCursor ) && ctype_digit( $storedCursor ) ? (int) $storedCursor : 0 );
    $rows         = $this->dispatchMarkerRowsAfter( $cursor );
    if ( null === $rows ) {
      return;
    }
    if ( ! $rows && $cursor > 0 ) {
      $cursor = 0;
      $rows   = $this->dispatchMarkerRowsAfter( $cursor );
      if ( null === $rows ) {
        return;
      }
    }
    if ( ! $rows ) {
      update_option( self::DISPATCH_SWEEP_CURSOR_OPTION, 0, false );

      return;
    }

    $lastOptionId = $cursor;

    foreach ( $rows as $row ) {
      $optionIdValue = $row['option_id'] ?? 0;
      $optionId      = is_int( $optionIdValue )
        ? $optionIdValue
        : ( is_string( $optionIdValue ) && ctype_digit( $optionIdValue ) ? (int) $optionIdValue : 0 );
      $lastOptionId  = max( $lastOptionId, $optionId );
      $nameValue     = $row['option_name'] ?? '';
      $name          = is_string( $nameValue ) ? $nameValue : '';
      if ( 1 !== preg_match( '/^' . preg_quote( self::DISPATCH_UNCERTAIN_OPTION_PREFIX, '/' ) . '([1-9][0-9]*)$/', $name, $matches ) ) {
        continue;
      }

      $jobId       = (int) $matches[1];
      $optionValue = $row['option_value'] ?? '';
      $storedValue = is_string( $optionValue ) ? $optionValue : '';
      $state = $wpdb->get_row(
        $wpdb->prepare(
          "SELECT j.editor_job_id, j.translated,
            EXISTS (
              SELECT 1
              FROM {$wpdb->prefix}icl_translation_status source_status
              INNER JOIN {$wpdb->prefix}icl_translations target ON target.translation_id = source_status.translation_id
              INNER JOIN {$wpdb->prefix}icl_translations source
                ON source.trid = target.trid AND source.source_language_code IS NULL
              INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tt.term_taxonomy_id = source.element_id
              WHERE source_status.rid = j.rid
            ) AS source_exists
           FROM {$wpdb->prefix}icl_translate_job j
           WHERE j.job_id = %d",
          $jobId
        ),
        ARRAY_A
      );

      $jobAbsent = null === $state && empty( $wpdb->last_error );
      $resolved  = is_array( $state ) && (
        (int) ( $state['editor_job_id'] ?? 0 ) > 0
        || ! empty( $state['translated'] )
        || empty( $state['source_exists'] )
      );
      if ( ( $jobAbsent || $resolved ) && $this->deleteIfUnchanged( $name, $storedValue ) ) {
        wp_cache_delete( $name, 'options' );
      }
    }

    update_option( self::DISPATCH_SWEEP_CURSOR_OPTION, $lastOptionId, false );
  }


  private function dispatchMarkerRowsAfter( int $cursor ): ?array {
    $wpdb = $this->wpdb;
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT option_id, option_name, option_value FROM {$wpdb->options}
         WHERE option_name LIKE %s AND option_id > %d
         ORDER BY option_id ASC LIMIT %d",
        $wpdb->esc_like( self::DISPATCH_UNCERTAIN_OPTION_PREFIX ) . '%',
        $cursor,
        self::ROWS_INSPECTED
      ),
      ARRAY_A
    );

    if ( ! is_array( $rows ) ) {
      return null;
    }

    $normalizedRows = [];
    foreach ( $rows as $row ) {
      if ( ! is_array( $row ) ) {
        return null;
      }
      $normalizedRows[] = $row;
    }

    return $normalizedRows;
  }


  private function isOperationRow( string $name ): bool {
    return 1 === preg_match(
      '/^' . preg_quote( TaxonomySendOperationRepository::OPTION_PREFIX, '/' ) . '[a-f0-9]{32}$/',
      $name
    );
  }


  private function expiresAt( $operation ): int {
    if ( ! is_array( $operation ) || ! isset( $operation['expiresAt'] ) || ! is_numeric( $operation['expiresAt'] ) ) {
      return 0;
    }

    return (int) $operation['expiresAt'];
  }


  private function deleteIfUnchanged( string $name, string $storedValue ): bool {
    $wpdb = $this->wpdb;

    $deleted = $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
        $name,
        $storedValue
      )
    );

    return is_int( $deleted ) && $deleted > 0;
  }
}
