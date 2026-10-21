<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Evidence;

use WPML\Core\Component\WordsToTranslate\Domain\Calculator\Diff;

class HunkBuilder {

  const CONTEXT_WORDS = 3;


  public function build( $diff, $countFactor = 1, string $joiner = ' ', int $rawTokenOffset = 0 ) {
    $parts = array_values( $diff );
    $hunks = [];

    $cumulativeTokens = $rawTokenOffset;
    $wordsSoFar = (int) round( $rawTokenOffset * $countFactor );

    foreach ( $parts as $index => $part ) {
      if ( ! is_array( $part ) ) {
        continue;
      }

      $added = $part[ Diff::DIFF_KEY_ADDED ] ?? [];
      if ( ! $added ) {
        continue;
      }

      $removed = $part[ Diff::DIFF_KEY_REMOVED ] ?? [];

      $cumulativeTokens += count( $added );
      $wordsCumulative = (int) round( $cumulativeTokens * $countFactor );
      $addedWords = $wordsCumulative - $wordsSoFar;
      $wordsSoFar = $wordsCumulative;

      $hunks[] = new Hunk(
        implode( $joiner, $this->contextBefore( $parts, $index ) ),
        implode( $joiner, $removed ),
        implode( $joiner, $added ),
        implode( $joiner, $this->contextAfter( $parts, $index ) ),
        $addedWords
      );
    }

    return $hunks;
  }


  private function contextBefore( $parts, $index ) {
    $context = [];

    for ( $i = $index - 1; $i >= 0; $i-- ) {
      if ( count( $context ) >= self::CONTEXT_WORDS ) {
        break;
      }

      $part = $parts[ $i ];
      if ( is_array( $part ) ) {
        break;
      }

      array_unshift( $context, $part );
    }

    return $context;
  }


  private function contextAfter( $parts, $index ) {
    $context = [];
    $total = count( $parts );

    for ( $i = $index + 1; $i < $total; $i++ ) {
      if ( count( $context ) >= self::CONTEXT_WORDS ) {
        break;
      }

      $part = $parts[ $i ];
      if ( is_array( $part ) ) {
        break;
      }

      $context[] = $part;
    }

    return $context;
  }


}
