<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Calculator;

class Diff {
  const DIFF_KEY_ADDED = '+';
  const DIFF_KEY_REMOVED = '-';


  public function diffStrings( string $before, string $current ) {
    return $this->diffArrays(
      preg_split( "/[\s]+/", $before ) ?: [],
      preg_split( "/[\s]+/", $current ) ?: []
    );
  }


  public function diffArrays(
    $b,
    $c,
    $bStart = 0,
    $bEnd = null,
    $cStart = 0,
    $cEnd = null,
    $cValuesAsIndex = null
  ) {
    $bEnd = $bEnd ?? count( $b );
    $cEnd = $cEnd ?? count( $c );

    if ( $bStart >= $bEnd && $cStart >= $cEnd ) {
      return [];
    }

    if ( $cValuesAsIndex === null ) {
      $cValuesAsIndex = [];
      foreach ( $c as $index => $value ) {
        $cValuesAsIndex[ $value ][] = $index;
      }
    }

    $maxMatchingWordsInARow = 0;
    $maxMatchAStart = $bStart;
    $maxMatchBstart = $cStart;

    $prevRow = [];

    for ( $bIndex = $bStart; $bIndex < $bEnd; $bIndex++ ) {
      $value = $b[ $bIndex ];

      $matchIndexes = $cValuesAsIndex[ $value ] ?? [];

      $currRow = [];

      foreach ( $matchIndexes as $matchIndex ) {

        if ( $matchIndex < $cStart || $matchIndex >= $cEnd ) {
          continue;
        }

        $prevMatch = $prevRow[ $matchIndex - 1 ] ?? 0;

        $currRow[ $matchIndex ] = $prevMatch + 1;

        if ( $currRow[ $matchIndex ] > $maxMatchingWordsInARow ) {
          $maxMatchingWordsInARow = $currRow[ $matchIndex ];
          $maxMatchAStart = $bIndex + 1 - $maxMatchingWordsInARow;
          $maxMatchBstart = $matchIndex + 1 - $maxMatchingWordsInARow;
        }
      }

      $prevRow = $currRow;
    }

    unset( $prevRow, $currRow );

    if ( $maxMatchingWordsInARow === 0 ) {
      return [
        [
          self::DIFF_KEY_REMOVED => array_slice( $b, $bStart, $bEnd - $bStart ),
          self::DIFF_KEY_ADDED => array_slice( $c, $cStart, $cEnd - $cStart ),
        ],
      ];
    }

    return array_merge(
      $this->diffArrays(
        $b,
        $c,
        $bStart,
        $maxMatchAStart,
        $cStart,
        $maxMatchBstart,
        $cValuesAsIndex
      ),
      array_slice( $c, $maxMatchBstart, $maxMatchingWordsInARow ),
      $this->diffArrays(
        $b,
        $c,
        $maxMatchAStart + $maxMatchingWordsInARow,
        $bEnd,
        $maxMatchBstart + $maxMatchingWordsInARow,
        $cEnd,
        $cValuesAsIndex
      )
    );
  }


}
