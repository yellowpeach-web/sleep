<?php

namespace WPML\Legacy\Component\Translation\Sender\Taxonomy;

use WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomySendOperationRepository;
use WPML\Core\Component\Translation\Domain\TranslationType;
use WPML\PHP\Exception\InvalidArgumentException;

class TaxonomyTermBundleRounds {

  private $wpdb;

  private $dispatcher;

  private $repository;


  public function __construct( \wpdb $wpdb, $dispatcher, ?TaxonomySendOperationRepository $repository = null ) {
    $this->wpdb       = $wpdb;
    $this->dispatcher = $dispatcher;
    $this->repository = $repository ?: new TaxonomySendOperationRepository();
  }


  public function hold( string $operationId, array $members ): bool {
    $records = [];
    try {
      foreach ( $this->repository->openBundleMembers( $operationId ) as $record ) {
        $records[ $record['rid'] ] = $record;
      }
      foreach ( $members as $member ) {
        $records[ $member->getRid() ] = self::record( $member );
      }

      return $this->repository->setOpenBundleMembers( $operationId, array_values( $records ) );
    } catch ( InvalidArgumentException $e ) {
      return false;
    }
  }


  public function settleRound( string $operationId, array $records, bool $flush ): array {
    $members = [];
    $failed  = [];
    foreach ( $records as $record ) {
      $member = self::member( $record );
      if ( $this->dispatcher->adoptTermBundleMember( $member ) ) {
        $members[] = $member;
        continue;
      }
      ( new TaxonomyTermJobCleanup( $this->wpdb ) )->release( [ self::releasable( $member ) ] );
      $failed[] = $this->failure( $member, 'rejected', __( 'The taxonomy term could not be prepared for translation. Try again.', 'wpml' ), 'taxonomy_term_preparation_failed' );
    }

    $dispatched           = $this->dispatch( $members, ! $flush );
    $dispatched['failed'] = array_merge( $failed, $dispatched['failed'] );
    $this->repository->setOpenBundleMembers( $operationId, array_map( [ self::class, 'record' ], $dispatched['open'] ) );

    return $dispatched;
  }


  public function dispatch( array $members, bool $fullOnly ): array {
    $bundles     = $this->dispatcher->buildTermBundles( $members, $fullOnly );
    $byJobId     = [];
    $bundledRids = [];
    $models      = [];
    foreach ( $bundles as $bundle ) {
      $model = $bundle->getModel();
      if ( null === $model ) {
        continue;
      }
      $byJobId[ $bundle->getJobId() ] = $bundle;
      $models[]                       = $model;
      foreach ( $bundle->getMembers() as $member ) {
        $bundledRids[ $member->getRid() ] = true;
      }
    }

    $open   = [];
    $failed = [];
    foreach ( $members as $member ) {
      if ( isset( $bundledRids[ $member->getRid() ] ) ) {
        continue;
      }
      if ( $this->dispatcher->holdsPreparedMember( $member->getRid() ) ) {
        $open[] = $member;
      } else {
        $failed[] = $this->failure( $member, 'rejected', __( 'The taxonomy term could not be prepared for translation. Try again.', 'wpml' ), 'taxonomy_term_preparation_failed' );
      }
    }

    if ( ! $models ) {
      return [ 'accepted' => [], 'failed' => $failed, 'open' => $open ];
    }

    $result   = $this->dispatcher->dispatchToAte( $models );
    $accepted = [];
    foreach ( $result->getAcceptedJobIds() as $jobId ) {
      foreach ( $this->membersOf( $byJobId, $jobId ) as $member ) {
        $accepted[] = $member;
      }
    }

    $released = [];
    foreach ( $result->getRejectedJobIds() as $jobId ) {
      $released[] = $byJobId[ $jobId ] ?? null;
      foreach ( $this->membersOf( $byJobId, $jobId ) as $member ) {
        $failed[] = $this->failure( $member, 'rejected', __( 'The taxonomy term was not accepted by our translation system. Try again.', 'wpml' ), $result->getErrorCode() ?: 'taxonomy_term_rejected' );
      }
    }
    foreach ( $result->getRemainingJobIds() as $jobId ) {
      $released[] = $byJobId[ $jobId ] ?? null;
      foreach ( $this->membersOf( $byJobId, $jobId ) as $member ) {
        $failed[] = $this->failure( $member, 'remaining', __( 'The taxonomy term was not sent because an earlier translation batch failed. Try again.', 'wpml' ), 'taxonomy_term_remaining' );
      }
    }
    $this->dispatcher->releasePreparedBundles( array_values( array_filter( $released ) ) );

    foreach ( $result->getBlockedJobIds() as $jobId ) {
      foreach ( $this->membersOf( $byJobId, $jobId ) as $member ) {
        $failed[] = $this->failure( $member, 'blocked', __( 'The taxonomy term is waiting for automatic translation to become available.', 'wpml' ), $result->getErrorCode() ?: 'taxonomy_term_blocked' );
      }
    }
    foreach ( $result->getUncertainJobIds() as $jobId ) {
      foreach ( $this->membersOf( $byJobId, $jobId ) as $member ) {
        $failed[] = $this->failure( $member, 'remaining', __( 'Our translation system may already have received this taxonomy term. It was not sent again to avoid a duplicate charge.', 'wpml' ), $result->getErrorCode() ?: 'taxonomy_term_dispatch_uncertain' );
      }
    }

    return [ 'accepted' => $accepted, 'failed' => $failed, 'open' => $open ];
  }


  public static function releaseRecords( \wpdb $wpdb, array $records ): int {
    $releasable = [];
    foreach ( $records as $record ) {
      $releasable[] = self::releasable( self::member( $record ) );
    }

    return ( new TaxonomyTermJobCleanup( $wpdb ) )->release( $releasable );
  }


  public static function failedElement( array $failure ): array {
    return [
      'elementType'    => TranslationType::TAXONOMY,
      'elementId'      => $failure['member']->getTermTaxonomyId(),
      'targetLanguage' => $failure['member']->getTargetLanguage(),
      'reason'         => $failure['reason'],
      'kind'           => $failure['kind'],
      'errorCode'      => $failure['errorCode'],
    ];
  }


  public static function pairOf( PreparedTaxonomyTermJob $member ): array {
    return [
      'elementId'      => $member->getTermTaxonomyId(),
      'taxonomy'       => $member->getTaxonomy(),
      'sourceLanguage' => $member->getSourceLanguage(),
      'targetLanguage' => $member->getTargetLanguage(),
    ];
  }


  public static function record( PreparedTaxonomyTermJob $member ): array {
    return [
      'elementId'      => $member->getTermTaxonomyId(),
      'taxonomy'       => $member->getTaxonomy(),
      'sourceLanguage' => $member->getSourceLanguage(),
      'targetLanguage' => $member->getTargetLanguage(),
      'translationId'  => $member->getTranslationId(),
      'rid'            => $member->getRid(),
      'statusBefore'   => $member->getStatusBefore(),
    ];
  }


  public static function member( array $record ): PreparedTaxonomyTermJob {
    return new PreparedTaxonomyTermJob(
      null,
      $record['elementId'],
      $record['taxonomy'],
      $record['sourceLanguage'],
      $record['targetLanguage'],
      $record['translationId'],
      $record['rid'],
      0,
      0,
      $record['statusBefore'],
      0
    );
  }


  private static function releasable( PreparedTaxonomyTermJob $member ): PreparedTaxonomyTermJob {
    return new PreparedTaxonomyTermJob(
      null,
      $member->getTermTaxonomyId(),
      $member->getTaxonomy(),
      $member->getSourceLanguage(),
      $member->getTargetLanguage(),
      $member->getTranslationId(),
      $member->getRid(),
      PHP_INT_MAX,
      0,
      $member->getStatusBefore(),
      0
    );
  }


  private function membersOf( array $byJobId, int $jobId ): array {
    return isset( $byJobId[ $jobId ] ) ? $byJobId[ $jobId ]->getMembers() : [];
  }


  private function failure( PreparedTaxonomyTermJob $member, string $kind, string $reason, string $errorCode ): array {
    return [ 'member' => $member, 'kind' => $kind, 'reason' => $reason, 'errorCode' => $errorCode ];
  }

}
