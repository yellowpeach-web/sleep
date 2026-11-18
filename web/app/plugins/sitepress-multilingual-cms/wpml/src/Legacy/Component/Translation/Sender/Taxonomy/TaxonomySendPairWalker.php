<?php

namespace WPML\Legacy\Component\Translation\Sender\Taxonomy;

use WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomySendOperationRepository;
use WPML\TM\Dashboard\Taxonomy\TaxonomyDashboardData;
use WPML\TM\Dashboard\Taxonomy\TaxonomySelectionPlan;

class TaxonomySendPairWalker {

  const FIRST_WINDOW = 100;

  const LARGEST_WINDOW = 1000;

  const WINDOWS_PER_ROUND = 40;

  const ROUND_READ_SECONDS = 2;

  private $data;


  public function __construct( ?TaxonomyDashboardData $data = null ) {
    $this->data = $data ?: new TaxonomyDashboardData();
  }


  public function freeze( array $taxonomies, array $languages, bool $overwrite, ?array $selections = null ): array {
    $languages = array_values( array_unique( $languages ) );
    sort( $languages, SORT_STRING );

    $boundary    = $taxonomies && $languages ? $this->data->getLastTermTaxonomyId() : 0;
    $jobBoundary = $taxonomies && $languages ? $this->data->getLastTranslateJobId() : 0;
    $walked      = [];
    $plans       = [];
    $counts      = [];
    $termTotal   = 0;
    foreach ( $languages ? $taxonomies : [] as $taxonomy ) {
      $selection = $selections[ $taxonomy ] ?? [ 'mode' => 'all', 'ids' => [], 'branches' => [] ];
      $plan      = $this->plan( $taxonomy, $languages, $overwrite, $selection, $boundary );
      $counts[ $taxonomy ] = [
        'selected'               => $plan['selected'],
        'sent'                   => $plan['sent'],
        'alreadyTranslated'      => $plan['alreadyTranslated'],
        'inFlight'               => $plan['inFlight'],
        'inTranslationWithPosts' => $plan['inTranslationWithPosts'],
        'words'                  => $plan['words'],
        'perLanguage'            => $plan['perLanguage'],
        'addedParents'           => $plan['addedParents'],
      ];
      if ( $plan['sent'] > 0 ) {
        $walked[]   = $taxonomy;
        $plans[ $taxonomy ] = [
          'selectedIds' => $plan['canonical']['selectedIds'],
          'carried'     => $plan['canonical']['carried'],
          'held'        => $plan['canonical']['held'],
          'counts'      => $counts[ $taxonomy ],
        ];
        $termTotal += $plan['sent'];
      }
    }

    return [
      'selection' => [
        'taxonomies'  => $walked,
        'languages'   => $languages,
        'overwrite'   => $overwrite,
        'boundary'    => $boundary,
        'jobBoundary' => $jobBoundary,
        'plans'       => $plans,
        'counts'      => $counts,
      ],
      'termTotal' => $termTotal,
    ];
  }


  public function normalizeSelection( array $selection ): array {
    if ( isset( $selection['plans'] ) ) {
      return $selection;
    }

    $plans = [];
    foreach ( $selection['taxonomies'] as $taxonomy ) {
      $plan = $this->plan(
        $taxonomy,
        $selection['languages'],
        $selection['overwrite'],
        [ 'mode' => 'all', 'ids' => [], 'branches' => [] ],
        $selection['boundary']
      );
      $plans[ $taxonomy ] = [
        'selectedIds' => $plan['canonical']['selectedIds'],
        'carried'     => $plan['canonical']['carried'],
        'held'        => $plan['canonical']['held'],
      ];
    }
    $selection['plans'] = $plans;

    return $selection;
  }


  private function plan( string $taxonomy, array $languages, bool $overwrite, array $selection, int $boundary ): array {
    return ( new TaxonomySelectionPlan( $this->data ) )->build( [
      'taxonomy'        => $taxonomy,
      'targetLanguages' => $languages,
      'overwrite'       => $overwrite,
      'selection'       => $selection,
      'lastElementId'   => $boundary,
    ] );
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
    $pairs   = [];
    $skipped = [];
    $last    = $position;

    for ( $windows = 0; $position['taxonomyIndex'] < $taxonomyCount; ++$windows ) {
      if ( self::WINDOWS_PER_ROUND === $windows || ( $windows > 0 && $this->now() >= $deadline ) ) {
        return [ 'pairs' => $pairs, 'skipped' => $skipped, 'cursor' => $position, 'ended' => false ];
      }

      $taxonomyIndex = $position['taxonomyIndex'];
      $taxonomy      = $taxonomies[ $taxonomyIndex ];
      $insideTerm = '' !== $position['language'];
      $after      = $insideTerm ? $position['elementId'] - 1 : $position['elementId'];
      $found      = $this->data->getEligibleTermPairsWindow(
        $taxonomy,
        $selection['languages'],
        $selection['overwrite'],
        true,
        $after,
        $selection['boundary'],
        $window,
        $selection['jobBoundary']
      );
      $plan       = $selection['plans'][ $taxonomy ] ?? null;
      $selected   = $plan ? array_flip( $plan['selectedIds'] ) : [];
      $held       = $this->heldLookup( $selection, $taxonomy );
      $windowPairs = $this->inWalkOrder( $found['pairs'] );
      foreach ( $windowPairs as $pair ) {
        if ( $insideTerm
          && $pair['elementId'] === $position['elementId']
          && strcmp( $pair['targetLanguage'], $position['language'] ) <= 0
        ) {
          continue;
        }
        if ( $plan && ! isset( $selected[ $pair['elementId'] ] ) ) {
          continue;
        }
        if ( isset( $held[ $pair['elementId'] ][ $pair['targetLanguage'] ] ) ) {
          continue;
        }
        if ( count( $pairs ) === $limit ) {
          return [ 'pairs' => $pairs, 'skipped' => $skipped, 'cursor' => $last, 'ended' => false ];
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

      if ( $plan && array_key_exists( 'elementIds', $found ) ) {
        $present = array_flip( array_map( 'intval', $found['elementIds'] ) );
        $upper   = $found['hasMore'] ? $found['lastElementId'] : $selection['boundary'];
        foreach ( $plan['selectedIds'] as $id ) {
          if ( $id > $after && $id <= $upper && ! isset( $present[ $id ] ) ) {
            $skipped[] = [ 'taxonomy' => $taxonomy, 'elementId' => $id ];
          }
        }
      }
      $position = $found['hasMore']
        ? [ 'taxonomyIndex' => $taxonomyIndex, 'elementId' => $found['lastElementId'], 'language' => '' ]
        : [ 'taxonomyIndex' => $taxonomyIndex + 1, 'elementId' => 0, 'language' => '' ];
      $window   = min( $window * 2, self::LARGEST_WINDOW );
    }

    return [ 'pairs' => $pairs, 'skipped' => $skipped, 'cursor' => $position, 'ended' => true ];
  }


  private function firstWindow( array $selection, int $limit ): int {
    $languages = max( 1, count( $selection['languages'] ) );

    return min( self::FIRST_WINDOW, (int) ceil( 2 * $limit / $languages ) );
  }


  protected function now(): float {
    return microtime( true );
  }


  public function splitStillNeeded( array $selection, array $pairs ): array {
    $needed  = [];
    $handled = [];
    $byTerm  = [];
    $held    = [];
    foreach ( $pairs as $pair ) {
      $taxonomy = $pair['taxonomy'];
      $carried  = ! empty( $selection['plans'][ $taxonomy ]['carried'][ $pair['elementId'] ][ $pair['targetLanguage'] ] );
      if ( ! isset( $held[ $taxonomy ] ) ) {
        $held[ $taxonomy ] = $this->heldLookup( $selection, $taxonomy );
      }
      if ( ! $carried && isset( $held[ $taxonomy ][ $pair['elementId'] ][ $pair['targetLanguage'] ] ) ) {
        $handled[] = $pair;
        continue;
      }
      $byTerm[ $pair['taxonomy'] . '|' . $pair['elementId'] ][] = $pair;
    }

    foreach ( $byTerm as $termPairs ) {
      $eligible = array_flip(
        $this->data->getEligibleLanguagesOfTerm(
          $termPairs[0]['taxonomy'],
          $termPairs[0]['elementId'],
          array_column( $termPairs, 'targetLanguage' ),
          $selection['overwrite'],
          true,
          $selection['jobBoundary']
        )
      );
      foreach ( $termPairs as $pair ) {
        if ( ! empty( $selection['plans'][ $pair['taxonomy'] ]['carried'][ $pair['elementId'] ][ $pair['targetLanguage'] ] ) ) {
          $needed[] = $pair;
          continue;
        }
        if ( isset( $eligible[ $pair['targetLanguage'] ] ) ) {
          $needed[] = $pair;
        } else {
          $handled[] = $pair;
        }
      }
    }

    return [ $needed, $handled ];
  }


  private function heldLookup( array $selection, string $taxonomy ): array {
    $lookup = [];
    foreach ( $selection['plans'][ $taxonomy ]['held'] ?? [] as $elementId => $languages ) {
      $lookup[ $elementId ] = array_flip( $languages );
    }

    return $lookup;
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
