<?php

namespace WPML\Legacy\Component\Translation\Application\Repository;

use WPML\Core\Component\Translation\Application\Repository\TermBundleMembersInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\TM\ATE\JobError\Recorder;
use WPML\TM\ATE\TranslateEverything\TermBundleMembers as MembersTable;
use WPML\Translation\TranslateJobErrorServiceFactory;

class TermBundleMembers implements TermBundleMembersInterface {


  public function unsolved( int $bundleJobId ): array {
    global $wpdb;
    $table = new MembersTable( $wpdb );

    $unsolved = [];
    foreach ( $table->membersOf( $bundleJobId ) as $member ) {
      if ( in_array( $member['state'], [ MembersTable::STATE_IN_BUNDLE, MembersTable::STATE_GIVEN_UP ], true ) ) {
        $unsolved[ $member['term_rid'] ] = $member;
      }
    }
    if ( ! $unsolved ) {
      return [];
    }

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT rid, translation_id FROM {$wpdb->prefix}icl_translation_status WHERE rid IN ("
        . implode( ', ', array_fill( 0, count( $unsolved ), '%d' ) ) . ')',
        array_keys( $unsolved )
      ),
      ARRAY_A
    );

    $members = [];
    foreach ( (array) $rows as $row ) {
      $member    = $unsolved[ (int) $row['rid'] ];
      $members[] = [
        'termTaxonomyId' => $member['term_taxonomy_id'],
        'translationId'  => (int) $row['translation_id'],
        'termRid'        => $member['term_rid'],
        'state'          => $member['state'],
      ];
    }

    return $members;
  }


  public function setState( int $bundleJobId, array $termRids, string $state ) {
    global $wpdb;
    ( new MembersTable( $wpdb ) )->setState( $bundleJobId, $termRids, $state );
  }


  public function releaseWords( int $bundleJobId, array $termRids ) {
    global $wpdb;
    ( new MembersTable( $wpdb ) )->releaseWords( $bundleJobId, $termRids );
  }


  public function hasApplied( int $bundleJobId ): bool {
    global $wpdb;
    foreach ( ( new MembersTable( $wpdb ) )->membersOf( $bundleJobId ) as $member ) {
      if ( MembersTable::STATE_APPLIED === $member['state'] ) {
        return true;
      }
    }

    return false;
  }


  public function completeDelivered( int $bundleJobId, bool $clearErrorRecord = true ) {
    global $wpdb;

    $wpdb->query(
      $wpdb->prepare(
        "UPDATE {$wpdb->prefix}icl_translation_status ts INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = ts.rid"
        . ' SET ts.status = %d WHERE j.job_id = %d AND ts.status = %d',
        TranslationStatus::COMPLETE,
        $bundleJobId,
        TranslationStatus::ATE_UNSOLVABLE
      )
    );
    if ( $clearErrorRecord ) {
      ( new Recorder( TranslateJobErrorServiceFactory::create() ) )->clear( $bundleJobId );
    }
  }


}
