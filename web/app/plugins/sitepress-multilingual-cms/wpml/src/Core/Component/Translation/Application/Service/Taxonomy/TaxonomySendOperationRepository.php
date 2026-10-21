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
      $this->deleteOperation( $operationId );
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

    update_option( $this->key( $operationId ), $manifest, false );

    return $manifest;
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
