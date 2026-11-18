<?php

namespace WPML\Core\Component\Translation\Application\Service\Taxonomy;

use WPML\PHP\Exception\InvalidArgumentException;

class TaxonomySendOperationRepository {

  const VERSION          = 2;
  const PAGE_SIZE        = 20;
  const LIFETIME_SECONDS = 1800;
  const OPTION_PREFIX    = '_wpml_tax_send_';

  const MAX_STORED_FAILURES = 200;


  public function create(
    int $userId,
    array $selection,
    int $termTotal,
    array $labelIds,
    array $warnings,
    array $baseRequest,
    array $labelSelections = [],
    array $incompleteTaxonomies = [],
    array $incompleteLabelTaxonomies = []
  ): string {
    $operationId = $this->newOperationId();

    $manifest = [
      'version'        => self::VERSION,
      'userId'         => $userId,
      'createdAt'      => time(),
      'expiresAt'      => time() + self::LIFETIME_SECONDS,
      'selection'      => $selection,
      'cursor'         => [
        'taxonomyIndex' => 0,
        'elementId'     => 0,
        'language'      => '',
      ],
      'ended'          => false,
      'termTotal'      => max( 0, $termTotal ),
      'termSuccessful' => 0,
      'labelIds'       => array_values( array_unique( array_map( 'intval', $labelIds ) ) ),
      'labelSelections' => array_values( array_unique( $labelSelections ) ),
      'incompleteTaxonomies' => array_values( array_unique( $incompleteTaxonomies ) ),
      'incompleteLabelTaxonomies' => array_values( array_unique( $incompleteLabelTaxonomies ) ),
      'labelsSent'     => false,
      'successful'     => 0,
      'failures'       => [],
      'failureOverflow' => 0,
      'halted'         => false,
      'rounds'         => 0,
      'warnings'       => array_values( array_unique( $warnings ) ),
      'baseRequest'    => $baseRequest,
    ];

    add_option( $this->key( $operationId ), $manifest, '', false );

    return $operationId;
  }


  public function get( string $operationId, int $userId ): array {
    $this->assertValidId( $operationId );
    $manifest = get_option( $this->key( $operationId ), null );

    if ( ! is_array( $manifest ) || self::VERSION !== ( $manifest['version'] ?? null ) ) {
      throw new InvalidArgumentException(
        esc_html__( 'This translation send has expired. Start a new send to continue.', 'wpml' )
      );
    }

    if ( $manifest['expiresAt'] < time() ) {
      if ( ! $this->hasUncommittedTpBatch( $manifest ) ) {
        $this->deleteOperation( $operationId );
      }
      throw new InvalidArgumentException(
        esc_html__( 'This translation send has expired. Start a new send to continue.', 'wpml' )
      );
    }

    if ( $manifest['userId'] !== $userId ) {
      throw new InvalidArgumentException(
        esc_html__( 'This translation send belongs to another user.', 'wpml' )
      );
    }

    return $manifest;
  }


  public function commitRound(
    string $operationId,
    array $manifest,
    int $termSuccessful,
    int $labelSuccessful,
    array $failures,
    bool $labelsWereSent,
    array $retriedPairs = [],
    ?array $walk = null,
    array $additionalWarnings = [],
    array $incompleteLabelTaxonomies = []
  ): array {
    if ( $retriedPairs ) {
      $retriedKeys = [];
      foreach ( $retriedPairs as $pair ) {
        $retriedKeys[ $this->pairKey( $pair ) ] = true;
      }
      $manifest['failures'] = array_values(
        array_filter(
          $manifest['failures'],
          function ( $failure ) use ( $retriedKeys ) {
            return ! isset( $retriedKeys[ $this->pairKey( $failure ) ] );
          }
        )
      );
    }

    if ( null !== $walk ) {
      $manifest['cursor'] = $walk['cursor'];
      $manifest['ended']  = $walk['ended'];
    }

    $manifest['termSuccessful'] = $manifest['termSuccessful'] + max( 0, $termSuccessful );
    $manifest['successful']     = $manifest['successful'] + max( 0, $termSuccessful ) + max( 0, $labelSuccessful );
    $manifest                   = $this->addFailures( $manifest, $failures );
    $manifest['labelsSent'] = ! empty( $manifest['labelsSent'] ) || $labelsWereSent;
    $manifest['warnings'] = array_values(
      array_unique( array_merge( $manifest['warnings'], $additionalWarnings ) )
    );
    $manifest['incompleteLabelTaxonomies'] = array_values(
      array_unique( array_merge( $manifest['incompleteLabelTaxonomies'], $incompleteLabelTaxonomies ) )
    );
    $manifest['halted']     = $retriedPairs
      ? ( ! empty( $failures ) || ! $manifest['ended'] )
      : ( ! empty( $manifest['halted'] ) || ! empty( $failures ) );
    $manifest['rounds']     = $manifest['rounds'] + 1;
    $manifest['expiresAt']  = time() + self::LIFETIME_SECONDS;

    $stored = get_option( $this->key( $operationId ), null );
    if ( is_array( $stored ) ) {
      if ( array_key_exists( 'batchName', $stored ) ) {
        $manifest['batchName'] = (string) $stored['batchName'];
      }
      if ( array_key_exists( 'batchId', $stored ) ) {
        $manifest['batchId'] = (int) $stored['batchId'];
      }
      if ( array_key_exists( 'batchCommitted', $stored ) ) {
        $manifest['batchCommitted'] = (bool) $stored['batchCommitted'];
      }
      if ( array_key_exists( 'commitAttempts', $stored ) ) {
        $manifest['commitAttempts'] = (int) $stored['commitAttempts'];
      }
      if ( array_key_exists( 'committedBatches', $stored ) && is_array( $stored['committedBatches'] ) ) {
        $committedBatches = $stored['committedBatches'];
        $manifest['committedBatches'] = $committedBatches;
      }
      unset( $manifest['openBundleMembers'] );
      if ( is_array( $stored['openBundleMembers'] ?? null ) && $stored['openBundleMembers'] ) {
        $openBundleMembers             = $stored['openBundleMembers'];
        $manifest['openBundleMembers'] = $openBundleMembers;
      }
    }

    update_option( $this->key( $operationId ), $manifest, false );

    return $manifest;
  }


  public function openBundleMembers( string $operationId ): array {
    $this->assertValidId( $operationId );
    $manifest = get_option( $this->key( $operationId ), null );
    if ( ! is_array( $manifest ) || ! is_array( $manifest['openBundleMembers'] ?? null ) ) {
      return [];
    }
    $members = array_values( $manifest['openBundleMembers'] );

    return $members;
  }


  public function setOpenBundleMembers( string $operationId, array $members ): bool {
    $this->assertValidId( $operationId );
    $manifest = get_option( $this->key( $operationId ), null );
    if ( ! is_array( $manifest ) || self::VERSION !== ( $manifest['version'] ?? null ) ) {
      return false;
    }
    $members = array_values( $members );
    if ( ( $manifest['openBundleMembers'] ?? [] ) === $members ) {
      return true;
    }
    if ( $members ) {
      $manifest['openBundleMembers'] = $members;
    } else {
      unset( $manifest['openBundleMembers'] );
    }

    return update_option( $this->key( $operationId ), $manifest, false );
  }


  public function recordTpBatch( string $operationId, string $batchName, int $batchId ): bool {
    $this->assertValidId( $operationId );
    $manifest = get_option( $this->key( $operationId ), null );
    if ( ! is_array( $manifest ) || self::VERSION !== ( $manifest['version'] ?? null ) ) {
      return false;
    }
    if ( $batchName === ( $manifest['batchName'] ?? null ) && $batchId === ( $manifest['batchId'] ?? null ) ) {
      return true;
    }
    if ( isset( $manifest['batchName'] ) || isset( $manifest['batchId'] ) ) {
      if ( empty( $manifest['batchCommitted'] ) ) {
        return false;
      }
      $manifest['committedBatches'][] = [
        'batchName' => (string) $manifest['batchName'],
        'batchId'   => (int) $manifest['batchId'],
      ];
      $manifest['batchCommitted'] = false;
      $manifest['commitAttempts'] = 0;
    }
    $manifest['batchName'] = $batchName;
    $manifest['batchId']   = $batchId;

    return update_option( $this->key( $operationId ), $manifest, false );
  }


  public function recordTpCommitAttempt( string $operationId, string $batchName, int $batchId ): int {
    $this->assertValidId( $operationId );
    $manifest = get_option( $this->key( $operationId ), null );
    if ( ! is_array( $manifest )
      || self::VERSION !== ( $manifest['version'] ?? null )
      || $batchName !== ( $manifest['batchName'] ?? null )
      || $batchId !== ( $manifest['batchId'] ?? null )
    ) {
      return 0;
    }
    if ( ! $this->hasUncommittedTpBatch( $manifest ) ) {
      return 0;
    }
    $manifest['commitAttempts'] = ( $manifest['commitAttempts'] ?? 0 ) + 1;

    return update_option( $this->key( $operationId ), $manifest, false )
      ? $manifest['commitAttempts']
      : 0;
  }


  public function isTpBatchCommitted( string $operationId, string $batchName, int $batchId ): bool {
    $this->assertValidId( $operationId );
    $manifest = get_option( $this->key( $operationId ), null );

    if ( ! is_array( $manifest ) || self::VERSION !== ( $manifest['version'] ?? null ) ) {
      return false;
    }
    if ( $batchName === ( $manifest['batchName'] ?? null )
      && $batchId === ( $manifest['batchId'] ?? null )
      && ! empty( $manifest['batchCommitted'] )
    ) {
      return true;
    }
    foreach ( $manifest['committedBatches'] ?? [] as $committed ) {
      if ( $batchName === ( $committed['batchName'] ?? null ) && $batchId === ( $committed['batchId'] ?? null ) ) {
        return true;
      }
    }

    return false;
  }


  public function hasUncommittedTpBatch( array $manifest ): bool {
    return isset( $manifest['batchName'], $manifest['batchId'] ) && empty( $manifest['batchCommitted'] );
  }


  public function markTpBatchCommitted( string $operationId, string $batchName, int $batchId ): bool {
    $this->assertValidId( $operationId );
    $manifest = get_option( $this->key( $operationId ), null );
    if ( ! is_array( $manifest )
      || self::VERSION !== ( $manifest['version'] ?? null )
      || $batchName !== ( $manifest['batchName'] ?? null )
      || $batchId !== ( $manifest['batchId'] ?? null )
    ) {
      return false;
    }
    if ( ! empty( $manifest['batchCommitted'] ) ) {
      return true;
    }
    $manifest['batchCommitted'] = true;

    return update_option( $this->key( $operationId ), $manifest, false );
  }


  public function deleteCommittedOperation( string $operationId, string $batchName, int $batchId ): bool {
    $this->assertValidId( $operationId );
    $manifest = get_option( $this->key( $operationId ), null );
    if ( ! is_array( $manifest )
      || self::VERSION !== ( $manifest['version'] ?? null )
      || $batchName !== ( $manifest['batchName'] ?? null )
      || $batchId !== ( $manifest['batchId'] ?? null )
      || empty( $manifest['batchCommitted'] )
    ) {
      return false;
    }

    if ( ! $this->isComplete( $manifest ) || $this->hasRetryablePairs( $manifest ) ) {
      return false;
    }

    $this->deleteOperation( $operationId );
    return true;
  }


  private function hasRetryablePairs( array $manifest ): bool {
    foreach ( $manifest['failures'] as $failure ) {
      if ( in_array( $failure['outcome'] ?? '', [ 'rejected', 'remaining' ], true ) ) {
        return true;
      }
    }
    return false;
  }


  public function isComplete( array $manifest ): bool {
    return ( ! empty( $manifest['halted'] ) || ! empty( $manifest['ended'] ) )
      && ( empty( $manifest['labelIds'] ) || ! empty( $manifest['labelsSent'] ) );
  }


  public function deleteOperation( string $operationId ): void {
    delete_option( $this->key( $operationId ) );
  }


  private function addFailures( array $manifest, array $failures ): array {
    $room    = max( 0, self::MAX_STORED_FAILURES - count( $manifest['failures'] ) );
    $dropped = array_slice( $failures, $room );

    $manifest['failures'] = array_values( array_merge( $manifest['failures'], array_slice( $failures, 0, $room ) ) );
    if ( $dropped ) {
      $manifest['failureOverflow']      = $manifest['failureOverflow'] + count( $dropped );
      $manifest['incompleteTaxonomies'] = array_values(
        array_unique( array_merge( $manifest['incompleteTaxonomies'], array_column( $dropped, 'taxonomy' ) ) )
      );
    }

    return $manifest;
  }


  protected function newOperationId(): string {
    return str_replace( '-', '', wp_generate_uuid4() );
  }


  private function assertValidId( string $operationId ): void {
    if ( 1 !== preg_match( '/^[a-f0-9]{32}$/', $operationId ) ) {
      throw new InvalidArgumentException( esc_html__( 'The translation send identifier is not valid.', 'wpml' ) );
    }
  }


  private function key( string $operationId ): string {
    return self::OPTION_PREFIX . $operationId;
  }


  private function pairKey( array $pair ): string {
    return $pair['elementId'] . '|' . $pair['targetLanguage'];
  }

}
