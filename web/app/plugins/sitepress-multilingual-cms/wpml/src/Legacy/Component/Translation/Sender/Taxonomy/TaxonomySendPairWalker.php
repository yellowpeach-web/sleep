<?php

namespace WPML\Legacy\Component\Translation\Sender\Taxonomy;

use WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomySendOperationRepository;
use WPML\TM\Dashboard\Taxonomy\TaxonomyDashboardData;

class TaxonomySendPairWalker {

  const FIRST_WINDOW = 100;

  const LARGEST_WINDOW = 1000;

  const WINDOWS_PER_ROUND = 40;

  const ROUND_READ_SECONDS = 2;

  private $data;


  public function __construct( ?TaxonomyDashboardData $data = null ) {
    $this->data = $data ?: new TaxonomyDashboardData();
  }


  public function freeze( array $taxonomies, array $languages, bool $overwrite ): array {
    $languages = array_values( array_unique( $languages ) );
    sort( $languages, SORT_STRING );

    $boundary    = $taxonomies && $languages ? $this->data->getLastTermTaxonomyId() : 0;
    $jobBoundary = $taxonomies && $languages ? $this->data->getLastTranslateJobId() : 0;
    $walked      = [];
    $termTotal   = 0;
    foreach ( $languages ? $taxonomies : [] as $taxonomy ) {
      $count = $this->data->countEligibleTermPairs( $taxonomy, $languages, $overwrite, ! $overwrite, $boundary );
      if ( $count > 0 ) {
        $walked[]   = $taxonomy;
        $termTotal += $count;
      }
    }

    return [
      'selection' => [
        'taxonomies'  => $walked,
        'languages'   => $languages,
        'overwrite'   => $overwrite,
        'boundary'    => $boundary,
        'jobBoundary' => $jobBoundary,
      ],
      'termTotal' => $termTotal,
    ];
  }


  public function translatableLanguages( array $codes ): array {
    return $this->data->filterTranslatableLanguages( $codes );
  }


  public function next( array $selection, array $cursor, int $limit ): array {
    $limit         = max( 1, $limit );
    $taxonomies    = $selection['taxonomies'];
    $taxonomyCount = count( $taxonomies );
    $position      = $cursor;
    $window        = $this->firstWindow( $selection, $limit );
    $deadline      = $this->now() + self::ROUND_READ_SECONDS;
    $pairs = [];
    $last  = $position;

    for ( $windows = 0; $position['taxonomyIndex'] < $taxonomyCount; ++$windows ) {
      if ( self::WINDOWS_PER_ROUND === $windows || ( $windows > 0 && $this->now() >= $deadline ) ) {
        return [ 'pairs' => $pairs, 'cursor' => $position, 'ended' => false ];
      }

      $taxonomyIndex = $position['taxonomyIndex'];
      $taxonomy      = $taxonomies[ $taxonomyIndex ];
      $insideTerm = '' !== $position['language'];
      $found      = $this->data->getEligibleTermPairsWindow(
        $taxonomy,
        $selection['languages'],
        $selection['overwrite'],
        ! $selection['overwrite'],
        $insideTerm ? $position['elementId'] - 1 : $position['elementId'],
        $selection['boundary'],
        $window,
        $selection['jobBoundary']
      );

      $windowPairs = $this->inWalkOrder( $found['pairs'] );
      foreach ( $windowPairs as $pair ) {
        if ( $insideTerm
          && $pair['elementId'] === $position['elementId']
          && strcmp( $pair['targetLanguage'], $position['language'] ) <= 0
        ) {
          continue;
        }
        if ( count( $pairs ) === $limit ) {
          return [ 'pairs' => $pairs, 'cursor' => $last, 'ended' => false ];
        }

        $pairs[] = [
          'elementId'      => $pair['elementId'],
          'taxonomy'       => $taxonomy,
          'sourceLanguage' => $pair['sourceLanguage'],
          'targetLanguage' => $pair['targetLanguage'],
        ];
        $last    = [
          'taxonomyIndex' => $taxonomyIndex,
          'elementId'     => $pair['elementId'],
          'language'      => $pair['targetLanguage'],
        ];
      }

      $position = $found['hasMore']
        ? [ 'taxonomyIndex' => $taxonomyIndex, 'elementId' => $found['lastElementId'], 'language' => '' ]
        : [ 'taxonomyIndex' => $taxonomyIndex + 1, 'elementId' => 0, 'language' => '' ];
      $window   = min( $window * 2, self::LARGEST_WINDOW );
    }

    return [ 'pairs' => $pairs, 'cursor' => $position, 'ended' => true ];
  }


  private function firstWindow( array $selection, int $limit ): int {
    $languages = max( 1, count( $selection['languages'] ) );

    return min( self::FIRST_WINDOW, (int) ceil( 2 * $limit / $languages ) );
  }


  protected function now(): float {
    return microtime( true );
  }


  public function splitStillNeeded( array $selection, array $pairs ): array {
    $byTerm = [];
    foreach ( $pairs as $pair ) {
      $byTerm[ $pair['taxonomy'] . '|' . $pair['elementId'] ][] = $pair;
    }

    $needed  = [];
    $handled = [];
    foreach ( $byTerm as $termPairs ) {
      $eligible = array_flip(
        $this->data->getEligibleLanguagesOfTerm(
          $termPairs[0]['taxonomy'],
          $termPairs[0]['elementId'],
          array_column( $termPairs, 'targetLanguage' ),
          $selection['overwrite'],
          ! $selection['overwrite'],
          $selection['jobBoundary']
        )
      );
      foreach ( $termPairs as $pair ) {
        if ( isset( $eligible[ $pair['targetLanguage'] ] ) ) {
          $needed[] = $pair;
        } else {
          $handled[] = $pair;
        }
      }
    }

    return [ $needed, $handled ];
  }


  private function inWalkOrder( array $pairs ): array {
    usort(
      $pairs,
      function ( array $left, array $right ): int {
        return $left['elementId'] <=> $right['elementId']
          ?: strcmp( $left['targetLanguage'], $right['targetLanguage'] );
      }
    );

    return $pairs;
  }

}
