<?php

namespace WPML\Core\Component\SettingsStorage\Application\Service;

final class LegacySerializedSettingsRepairer {

  const MAX_SERIALIZED_DEPTH  = 512;
  const MAX_REPAIR_CANDIDATES = 256;
  const MAX_REPAIR_WORK       = 5000000;

  const SERIALIZED_TYPE_MARKERS = [
    'b' => true,
    'i' => true,
    'd' => true,
    'R' => true,
    'r' => true,
    's' => true,
    'S' => true,
    'a' => true,
    'O' => true,
    'C' => true,
    'E' => true,
  ];

  private static $repairSuffixes = [];

  private static $potentialValueStarts = [];

  private static $workRemaining = 0;

  private static $searchAborted = false;


  public static function repair( string $raw ): ?string {
    if ( $raw === '' || trim( $raw ) !== $raw || $raw[0] !== 'a' ) {
      return null;
    }

    self::$repairSuffixes      = [];
    self::$potentialValueStarts = self::findPotentialSerializedValueStarts( $raw );
    self::$searchAborted       = false;
    self::$workRemaining       = min( self::MAX_REPAIR_WORK, max( 10000, strlen( $raw ) * 32 ) );

    $candidates = self::scanRepairValueCandidates( $raw, 0, 0 );
    if ( ! is_array( $candidates ) || self::searchWasAborted() ) {
      self::resetRepairState();

      return null;
    }

    $completeRepairs = [];
    $unsafeComplete  = false;
    $rawLength       = strlen( $raw );

    foreach ( $candidates as $candidate ) {
      if ( $rawLength !== $candidate['offset'] ) {
        continue;
      }
      if ( $candidate['unsafe_shrink'] ) {
        $unsafeComplete = true;
        continue;
      }

      $repaired = self::applyLengthReplacements( $raw, $candidate['replacements'] );
      if ( self::isCompleteSerializedArray( $repaired ) && ! in_array( $repaired, $completeRepairs, true ) ) {
        $completeRepairs[] = $repaired;
      }
    }

    $result = ! $unsafeComplete && count( $completeRepairs ) === 1
      ? reset( $completeRepairs )
      : null;

    self::resetRepairState();

    return is_string( $result ) ? $result : null;
  }


  public static function isCompleteSerializedArray( $raw ): bool {
    if ( ! is_string( $raw ) ) {
      return false;
    }

    $serialized = trim( $raw );
    if ( $serialized === '' || $serialized[0] !== 'a' ) {
      return false;
    }

    $offset = 0;

    return self::scanSerializedValue( $serialized, $offset, 0 )
      && strlen( $serialized ) === $offset;
  }


  private static function searchWasAborted(): bool {
    return self::$searchAborted;
  }


  private static function resetRepairState(): void {
    self::$repairSuffixes       = [];
    self::$potentialValueStarts = [];
    self::$searchAborted        = false;
    self::$workRemaining        = 0;
  }


  private static function scanRepairValueCandidates(
    string $serialized,
    int $offset,
    int $depth,
    ?int $remainingSiblings = null
  ): ?array {
    $serializedLength = strlen( $serialized );
    if (
      ! self::spendRepairWork()
      || $depth > self::MAX_SERIALIZED_DEPTH
      || $offset >= $serializedLength
    ) {
      return null;
    }

    $type = $serialized[ $offset ];
    ++$offset;

    switch ( $type ) {
      case 'N':
        return self::repairCandidateAfterExactScan( $serialized, $offset, true );

      case 'b':
      case 'i':
      case 'd':
      case 'R':
      case 'r':
        return self::repairCandidateAfterExactScan( $serialized, $offset, false );

      case 's':
        return self::scanRepairStringCandidates( $serialized, $offset - 1, $depth, $remainingSiblings );

      case 'S':
        $byteLength = self::readSerializedUnsignedInteger( $serialized, $offset );
        if ( $byteLength === null || ! self::scanSerializedEscapedBytes( $serialized, $offset, $byteLength ) ) {
          return null;
        }

        return [ self::newRepairCandidate( $offset ) ];

      case 'a':
        $itemCount = self::readSerializedUnsignedInteger( $serialized, $offset );

        return $itemCount === null
          ? null
          : self::scanRepairPairsCandidates( $serialized, $offset, $itemCount, $depth );

      case 'O':
        $classLength = self::readSerializedUnsignedInteger( $serialized, $offset );
        if ( $classLength === null || ! self::scanSerializedClassName( $serialized, $offset, $classLength ) ) {
          return null;
        }
        $propertyCount = self::readSerializedUnsignedInteger( $serialized, $offset );

        return $propertyCount === null
          ? null
          : self::scanRepairPairsCandidates( $serialized, $offset, $propertyCount, $depth );

      case 'C':
        $classLength = self::readSerializedUnsignedInteger( $serialized, $offset );
        if ( $classLength === null || ! self::scanSerializedClassName( $serialized, $offset, $classLength ) ) {
          return null;
        }
        $payloadLength = self::readSerializedUnsignedInteger( $serialized, $offset );
        if ( $payloadLength === null || ! self::scanSerializedCustomPayload( $serialized, $offset, $payloadLength ) ) {
          return null;
        }

        return [ self::newRepairCandidate( $offset ) ];

      case 'E':
        $enumLength = self::readSerializedUnsignedInteger( $serialized, $offset );
        if ( $enumLength === null || ! self::scanSerializedQuotedBytes( $serialized, $offset, $enumLength ) ) {
          return null;
        }

        return [ self::newRepairCandidate( $offset ) ];
    }

    return null;
  }


  private static function scanRepairStringCandidates(
    string $serialized,
    int $typeOffset,
    int $depth,
    ?int $remainingSiblings
  ): ?array {
    $offset          = $typeOffset + 1;
    $digitsOffset    = $typeOffset + 2;
    $declaredLength  = self::readSerializedUnsignedInteger( $serialized, $offset );
    if ( $declaredLength === null ) {
      return null;
    }

    $digitsLength = $offset - $digitsOffset;
    if ( ! self::consumeSerializedLiteral( $serialized, $offset, ':"' ) ) {
      return null;
    }

    $payloadOffset = $offset;
    $declaredEnd   = $payloadOffset + $declaredLength;
    if ( substr( $serialized, $declaredEnd, 2 ) === '";' ) {
      return [ self::newRepairCandidate( $declaredEnd + 2 ) ];
    }

    $candidates = [];
    $closing    = strpos( $serialized, '";', $payloadOffset );
    while ( $closing !== false ) {
      if ( ! self::spendRepairWork() ) {
        return null;
      }

      $candidateOffset = $closing + 2;
      if ( $remainingSiblings !== null ) {
        if ( ! self::hasEnoughPotentialSerializedValues( $candidateOffset, $remainingSiblings ) ) {
          break;
        }

        $suffixIsRepairable = self::hasRepairableValuesToClosingBrace(
          $serialized,
          $candidateOffset,
          $remainingSiblings,
          $depth
        );
        if ( $suffixIsRepairable === null ) {
          return null;
        }
        if ( ! $suffixIsRepairable ) {
          $closing = strpos( $serialized, '";', $candidateOffset );
          continue;
        }
      }

      $actualLength               = $closing - $payloadOffset;
      $candidate                  = self::newRepairCandidate( $candidateOffset );
      $candidate['unsafe_shrink'] = $actualLength < $declaredLength;
      $candidate['replacements'][] = [
        'offset'      => $digitsOffset,
        'length'      => $digitsLength,
        'replacement' => (string) $actualLength,
      ];

      if ( ! self::appendRepairCandidate( $candidates, $candidate ) ) {
        return null;
      }

      $closing = strpos( $serialized, '";', $candidateOffset );
    }

    return $candidates ?: null;
  }


  private static function findPotentialSerializedValueStarts( string $serialized ): array {
    $starts           = [];
    $serializedLength = strlen( $serialized );
    for ( $offset = 0; $offset + 1 < $serializedLength; ++$offset ) {
      $type = $serialized[ $offset ];
      $next = $serialized[ $offset + 1 ];
      if (
        ( $type === 'N' && $next === ';' )
        || ( isset( self::SERIALIZED_TYPE_MARKERS[ $type ] ) && $next === ':' )
      ) {
        $starts[] = $offset;
      }
    }

    return $starts;
  }


  private static function hasEnoughPotentialSerializedValues( int $offset, int $required ): bool {
    if ( $required === 0 ) {
      return true;
    }

    $low    = 0;
    $starts = self::$potentialValueStarts;
    $high   = count( $starts );
    while ( $low < $high ) {
      $middle = (int) floor( ( $low + $high ) / 2 );
      if ( $starts[ $middle ] < $offset ) {
        $low = $middle + 1;
      } else {
        $high = $middle;
      }
    }

    return count( $starts ) - $low >= $required;
  }


  private static function hasRepairableValuesToClosingBrace(
    string $serialized,
    int $offset,
    int $remainingSiblings,
    int $depth
  ): ?bool {
    $cacheKey = $depth . ':' . $remainingSiblings . ':' . $offset;
    if ( array_key_exists( $cacheKey, self::$repairSuffixes ) ) {
      return self::$repairSuffixes[ $cacheKey ];
    }

    if ( ! self::spendRepairWork() ) {
      self::$repairSuffixes[ $cacheKey ] = null;

      return null;
    }

    if ( $remainingSiblings === 0 ) {
      self::$repairSuffixes[ $cacheKey ] = isset( $serialized[ $offset ] )
        && $serialized[ $offset ] === '}';

      return self::$repairSuffixes[ $cacheKey ];
    }

    $values = self::scanRepairValueCandidates(
      $serialized,
      $offset,
      $depth,
      $remainingSiblings - 1
    );
    if ( ! is_array( $values ) ) {
      self::$repairSuffixes[ $cacheKey ] = self::searchWasAborted() ? null : false;

      return self::$repairSuffixes[ $cacheKey ];
    }

    $unknown = false;
    foreach ( $values as $value ) {
      $isRepairable = self::hasRepairableValuesToClosingBrace(
        $serialized,
        $value['offset'],
        $remainingSiblings - 1,
        $depth
      );
      if ( $isRepairable === true ) {
        self::$repairSuffixes[ $cacheKey ] = true;

        return true;
      }
      $unknown = $unknown || $isRepairable === null;
    }

    self::$repairSuffixes[ $cacheKey ] = $unknown ? null : false;

    return self::$repairSuffixes[ $cacheKey ];
  }


  private static function spendRepairWork( int $units = 1 ): bool {
    self::$workRemaining -= $units;
    if ( self::$workRemaining < 0 ) {
      self::$searchAborted = true;
    }

    return self::$workRemaining >= 0;
  }


  private static function scanRepairPairsCandidates(
    string $serialized,
    int $offset,
    int $itemCount,
    int $depth
  ): ?array {
    if ( ! self::consumeSerializedLiteral( $serialized, $offset, ':{' ) ) {
      return null;
    }

    $remaining = strlen( $serialized ) - $offset;
    if ( $itemCount > (int) floor( max( 0, $remaining - 1 ) / 4 ) ) {
      return null;
    }

    $candidates  = [ self::newRepairCandidate( $offset ) ];
    $totalValues = $itemCount * 2;
    for ( $index = 0; $index < $totalValues; ++$index ) {
      $nextCandidates = [];
      foreach ( $candidates as $candidate ) {
        $values = self::scanRepairValueCandidates(
          $serialized,
          $candidate['offset'],
          $depth + 1,
          $totalValues - $index - 1
        );
        if ( ! is_array( $values ) ) {
          continue;
        }

        foreach ( $values as $value ) {
          $combined = [
            'offset'        => $value['offset'],
            'replacements'  => array_merge( $candidate['replacements'], $value['replacements'] ),
            'unsafe_shrink' => $candidate['unsafe_shrink'] || $value['unsafe_shrink'],
          ];
          if ( ! self::appendRepairCandidate( $nextCandidates, $combined ) ) {
            return null;
          }
        }
      }

      if ( ! $nextCandidates ) {
        return null;
      }
      $candidates = $nextCandidates;
    }

    $complete = [];
    foreach ( $candidates as $candidate ) {
      $end = $candidate['offset'];
      if ( self::consumeSerializedLiteral( $serialized, $end, '}' ) ) {
        $candidate['offset'] = $end;
        if ( ! self::appendRepairCandidate( $complete, $candidate ) ) {
          return null;
        }
      }
    }

    return $complete ?: null;
  }


  private static function repairCandidateAfterExactScan(
    string $serialized,
    int $offset,
    bool $isNull
  ): ?array {
    $valid = $isNull
      ? self::consumeSerializedLiteral( $serialized, $offset, ';' )
      : self::scanSerializedScalar( $serialized, $offset );

    return $valid ? [ self::newRepairCandidate( $offset ) ] : null;
  }


  private static function newRepairCandidate( int $offset ): array {
    return [
      'offset'        => $offset,
      'replacements'  => [],
      'unsafe_shrink' => false,
    ];
  }


  private static function appendRepairCandidate( array &$candidates, array $candidate ): bool {
    foreach ( $candidates as $existing ) {
      if ( $existing === $candidate ) {
        return true;
      }
    }

    $candidates[] = $candidate;
    if ( count( $candidates ) > self::MAX_REPAIR_CANDIDATES ) {
      self::$searchAborted = true;

      return false;
    }

    return true;
  }


  private static function applyLengthReplacements( string $serialized, array $replacements ): string {
    usort(
      $replacements,
      static function ( array $left, array $right ): int {
        return $right['offset'] - $left['offset'];
      }
    );

    foreach ( $replacements as $replacement ) {
      $serialized = substr_replace(
        $serialized,
        $replacement['replacement'],
        $replacement['offset'],
        $replacement['length']
      );
    }

    return $serialized;
  }


  private static function scanSerializedValue( string $serialized, int &$offset, int $depth ): bool {
    $serializedLength = strlen( $serialized );
    if ( $depth > self::MAX_SERIALIZED_DEPTH || $offset >= $serializedLength ) {
      return false;
    }

    $type = $serialized[ $offset ];
    ++$offset;

    switch ( $type ) {
      case 'N':
        return self::consumeSerializedLiteral( $serialized, $offset, ';' );

      case 'b':
      case 'i':
      case 'd':
      case 'R':
      case 'r':
        return self::scanSerializedScalar( $serialized, $offset );

      case 's':
        $byteLength = self::readSerializedUnsignedInteger( $serialized, $offset );

        return $byteLength !== null
          && self::scanSerializedQuotedBytes( $serialized, $offset, $byteLength );

      case 'S':
        $byteLength = self::readSerializedUnsignedInteger( $serialized, $offset );

        return $byteLength !== null
          && self::scanSerializedEscapedBytes( $serialized, $offset, $byteLength );

      case 'a':
        $itemCount = self::readSerializedUnsignedInteger( $serialized, $offset );

        return $itemCount !== null
          && self::scanSerializedPairs( $serialized, $offset, $itemCount, $depth );

      case 'O':
        $classLength = self::readSerializedUnsignedInteger( $serialized, $offset );
        if ( $classLength === null || ! self::scanSerializedClassName( $serialized, $offset, $classLength ) ) {
          return false;
        }

        $propertyCount = self::readSerializedUnsignedInteger( $serialized, $offset );

        return $propertyCount !== null
          && self::scanSerializedPairs( $serialized, $offset, $propertyCount, $depth );

      case 'C':
        $classLength = self::readSerializedUnsignedInteger( $serialized, $offset );
        if ( $classLength === null || ! self::scanSerializedClassName( $serialized, $offset, $classLength ) ) {
          return false;
        }

        $payloadLength = self::readSerializedUnsignedInteger( $serialized, $offset );

        return $payloadLength !== null
          && self::scanSerializedCustomPayload( $serialized, $offset, $payloadLength );

      case 'E':
        $enumLength = self::readSerializedUnsignedInteger( $serialized, $offset );

        return $enumLength !== null
          && self::scanSerializedQuotedBytes( $serialized, $offset, $enumLength );
    }

    return false;
  }


  private static function scanSerializedScalar( string $serialized, int &$offset ): bool {
    if ( ! self::consumeSerializedLiteral( $serialized, $offset, ':' ) ) {
      return false;
    }

    $end = strpos( $serialized, ';', $offset );
    if ( $end === false || $end === $offset ) {
      return false;
    }

    $offset = $end + 1;

    return true;
  }


  private static function readSerializedUnsignedInteger( string $serialized, int &$offset ): ?int {
    if ( ! self::consumeSerializedLiteral( $serialized, $offset, ':' ) ) {
      return null;
    }

    $start            = $offset;
    $serializedLength = strlen( $serialized );
    while ( $offset < $serializedLength ) {
      $byte = ord( $serialized[ $offset ] );
      if ( $byte < 48 || $byte > 57 ) {
        break;
      }
      ++$offset;
    }

    if ( $start === $offset ) {
      return null;
    }

    $digits  = ltrim( substr( $serialized, $start, $offset - $start ), '0' );
    $digits  = $digits === '' ? '0' : $digits;
    $maximum = (string) $serializedLength;
    $tooBig  = strlen( $digits ) > strlen( $maximum )
      || ( strlen( $digits ) === strlen( $maximum ) && strcmp( $digits, $maximum ) > 0 );

    return $tooBig ? null : (int) $digits;
  }


  private static function scanSerializedQuotedBytes(
    string $serialized,
    int &$offset,
    int $byteLength
  ): bool {
    if ( ! self::consumeSerializedLiteral( $serialized, $offset, ':"' ) ) {
      return false;
    }
    if ( $byteLength > strlen( $serialized ) - $offset ) {
      return false;
    }

    $offset += $byteLength;

    return self::consumeSerializedLiteral( $serialized, $offset, '";' );
  }


  private static function scanSerializedEscapedBytes(
    string $serialized,
    int &$offset,
    int $byteLength
  ): bool {
    if ( ! self::consumeSerializedLiteral( $serialized, $offset, ':"' ) ) {
      return false;
    }

    $serializedLength = strlen( $serialized );
    for ( $decoded = 0; $decoded < $byteLength; ++$decoded ) {
      if ( $offset >= $serializedLength ) {
        return false;
      }
      if ( $serialized[ $offset ] !== '\\' ) {
        ++$offset;
        continue;
      }

      $hex = substr( $serialized, $offset + 1, 2 );
      if ( strlen( $hex ) !== 2 || ! ctype_xdigit( $hex ) ) {
        return false;
      }
      $offset += 3;
    }

    return self::consumeSerializedLiteral( $serialized, $offset, '";' );
  }


  private static function scanSerializedPairs(
    string $serialized,
    int &$offset,
    int $itemCount,
    int $depth
  ): bool {
    if ( ! self::consumeSerializedLiteral( $serialized, $offset, ':{' ) ) {
      return false;
    }

    $remaining = strlen( $serialized ) - $offset;
    if ( $itemCount > (int) floor( max( 0, $remaining - 1 ) / 4 ) ) {
      return false;
    }

    $totalValues = $itemCount * 2;
    for ( $index = 0; $index < $totalValues; ++$index ) {
      if ( ! self::scanSerializedValue( $serialized, $offset, $depth + 1 ) ) {
        return false;
      }
    }

    return self::consumeSerializedLiteral( $serialized, $offset, '}' );
  }


  private static function scanSerializedClassName(
    string $serialized,
    int &$offset,
    int $classLength
  ): bool {
    if ( ! self::consumeSerializedLiteral( $serialized, $offset, ':"' ) ) {
      return false;
    }
    if ( $classLength > strlen( $serialized ) - $offset ) {
      return false;
    }

    $offset += $classLength;

    return self::consumeSerializedLiteral( $serialized, $offset, '"' );
  }


  private static function scanSerializedCustomPayload(
    string $serialized,
    int &$offset,
    int $payloadLength
  ): bool {
    if ( ! self::consumeSerializedLiteral( $serialized, $offset, ':{' ) ) {
      return false;
    }
    if ( $payloadLength > strlen( $serialized ) - $offset ) {
      return false;
    }

    $offset += $payloadLength;

    return self::consumeSerializedLiteral( $serialized, $offset, '}' );
  }


  private static function consumeSerializedLiteral(
    string $serialized,
    int &$offset,
    string $literal
  ): bool {
    $literalLength = strlen( $literal );
    if ( substr( $serialized, $offset, $literalLength ) !== $literal ) {
      return false;
    }

    $offset += $literalLength;

    return true;
  }
}
