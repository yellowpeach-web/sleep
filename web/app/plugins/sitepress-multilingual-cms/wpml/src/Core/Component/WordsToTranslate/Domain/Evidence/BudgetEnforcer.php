<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Evidence;

class BudgetEnforcer {

  const PROOF_CAP_BYTES = 2048;

  const TERMS_KEPT_ON_TRUNCATION = 10;

  const DEGRADATION_CONTEXT_TRIMMED = 'context-trimmed';
  const DEGRADATION_HUNK_COLLAPSED = 'hunk-collapsed';
  const DEGRADATION_TERMS_TRUNCATED = 'terms-truncated';

  private $proofSize;


  public function __construct( ProofSize $proofSize ) {
    $this->proofSize = $proofSize;
  }


  public function enforce( $proof, $tier ) {
    $degradations = [];

    if ( $tier === Tier::FINGERPRINT ) {
      unset( $proof['hunks'] );

      return [
        'proof'        => $proof,
        'degradations' => $degradations,
      ];
    }

    $collapseBatch = 0;
    $collapsedFields = [];

    while ( $this->proofSize->compressedBytes( $proof ) > self::PROOF_CAP_BYTES ) {
      if ( $this->trimContext( $proof ) ) {
        $degradations[] = self::DEGRADATION_CONTEXT_TRIMMED;
        continue;
      }

      $collapseBatch = $collapseBatch > 0 ? $collapseBatch * 2 : 1;
      $newlyCollapsedFields = $this->collapseLargestHunks( $proof, $collapseBatch );
      if ( $newlyCollapsedFields ) {
        $collapsedFields = array_merge( $collapsedFields, $newlyCollapsedFields );
        continue;
      }

      if ( $this->truncateTerms( $proof ) ) {
        $degradations[] = self::DEGRADATION_TERMS_TRUNCATED;
        continue;
      }

      break;
    }

    foreach ( array_unique( $collapsedFields ) as $collapsedField ) {
      $degradations[] = self::DEGRADATION_HUNK_COLLAPSED . ':' . $collapsedField;
    }

    return [
      'proof'        => $proof,
      'degradations' => $degradations,
    ];
  }


  private function trimContext( &$proof ) {
    if ( empty( $proof['hunks'] ) ) {
      return false;
    }

    $hunks = $proof['hunks'];
    $trimmed = false;

    foreach ( $hunks as $fieldKey => $fieldHunks ) {
      foreach ( $fieldHunks as $index => $hunk ) {
        if ( isset( $hunk['hunks'] ) ) {
          continue;
        }

        if ( $hunk['before'] !== '' || $hunk['after'] !== '' ) {
          $hunks[ $fieldKey ][ $index ]['before'] = '';
          $hunks[ $fieldKey ][ $index ]['after'] = '';
          $trimmed = true;
        }
      }
    }

    $proof['hunks'] = $hunks;

    return $trimmed;
  }


  private function collapseLargestHunks( &$proof, $count ) {
    if ( empty( $proof['hunks'] ) ) {
      return [];
    }

    $hunks = $proof['hunks'];

    $candidates = [];

    foreach ( $hunks as $fieldKey => $fieldHunks ) {
      foreach ( $fieldHunks as $index => $hunk ) {
        if ( isset( $hunk['hunks'] ) || $hunk['added'] === '' ) {
          continue;
        }

        $candidates[] = [
          'field' => $fieldKey,
          'index' => $index,
          'words' => (int) $hunk['added_words'],
        ];
      }
    }

    if ( ! $candidates ) {
      return [];
    }

    usort(
      $candidates,
      function ( $a, $b ): int {
        return $b['words'] - $a['words'];
      }
    );

    $collapsedFields = [];
    $dropByField = [];

    foreach ( array_slice( $candidates, 0, $count ) as $candidate ) {
      $dropByField[ $candidate['field'] ][ $candidate['index'] ] = $candidate['words'];
      $collapsedFields[] = $candidate['field'];
    }

    foreach ( $dropByField as $fieldKey => $dropIndexes ) {
      $hunks[ $fieldKey ] = $this->coalesceIntoSummary( $hunks[ $fieldKey ], $dropIndexes );
    }

    $proof['hunks'] = $hunks;

    return $collapsedFields;
  }


  private function coalesceIntoSummary( $fieldHunks, $dropIndexes ) {
    $summary = [
      'collapsed'   => true,
      'hunks'       => 0,
      'added_words' => 0,
    ];

    $kept = [];

    foreach ( $fieldHunks as $index => $hunk ) {
      if ( isset( $hunk['hunks'] ) ) {
        $summary['hunks'] += (int) $hunk['hunks'];
        $summary['added_words'] += (int) $hunk['added_words'];
        continue;
      }

      if ( isset( $dropIndexes[ $index ] ) ) {
        $summary['hunks']++;
        $summary['added_words'] += $dropIndexes[ $index ];
        continue;
      }

      $kept[] = $hunk;
    }

    if ( $summary['hunks'] > 0 ) {
      $kept[] = $summary;
    }

    return $kept;
  }


  private function truncateTerms( &$proof ) {
    if ( empty( $proof['terms'] ) || ! is_array( $proof['terms'] ) ) {
      return false;
    }

    if ( count( $proof['terms'] ) <= self::TERMS_KEPT_ON_TRUNCATION ) {
      return false;
    }

    $proof['terms'] = array_slice( $proof['terms'], 0, self::TERMS_KEPT_ON_TRUNCATION );

    return true;
  }


}
