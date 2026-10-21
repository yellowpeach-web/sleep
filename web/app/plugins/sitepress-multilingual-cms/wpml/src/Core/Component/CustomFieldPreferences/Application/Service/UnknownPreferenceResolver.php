<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Service;

final class UnknownPreferenceResolver {

  const MODE_IGNORE    = 0;
  const MODE_COPY      = 1;
  const MODE_TRANSLATE = 2;
  const MODE_COPY_ONCE = 3;

  const MODES = [ self::MODE_IGNORE, self::MODE_COPY, self::MODE_TRANSLATE, self::MODE_COPY_ONCE ];

  private $maps;

  private $source;

  private $memo = [];


  public function __construct(
    ExactEntryLookupInterface $maps,
    UnknownPreferenceSourceInterface $source
  ) {
    $this->maps   = $maps;
    $this->source = $source;
  }


  public function resolve( array $metaKeys, string $type ): array {
    $unknown = $this->withoutExactEntries( array_unique( $metaKeys ), $type );
    $this->memoizeAnswers( $this->notAskedYet( $unknown, $type ), $type );

    return $this->answersFor( $unknown, $type );
  }


  private function withoutExactEntries( array $metaKeys, string $type ): array {
    if ( ! $metaKeys ) {
      return [];
    }

    $hasExactEntry = $this->maps->hasExactEntries( $type, $metaKeys );

    return array_values(
      array_filter(
        $metaKeys,
        fn( $metaKey ) => empty( $hasExactEntry[ $metaKey ] )
      )
    );
  }


  private function notAskedYet( array $metaKeys, string $type ): array {
    $memo = isset( $this->memo[ $type ] ) ? $this->memo[ $type ] : [];

    return array_values(
      array_filter(
        $metaKeys,
        fn( $metaKey ) => ! array_key_exists( $metaKey, $memo )
      )
    );
  }


  public function reset() {
    $this->memo = [];
  }


  private function memoizeAnswers( array $metaKeys, string $type ) {
    if ( ! $metaKeys ) {
      return;
    }

    $answers = $this->source->resolve( $metaKeys, $type );
    foreach ( $metaKeys as $metaKey ) {
      $answer = isset( $answers[ $metaKey ] ) && is_numeric( $answers[ $metaKey ] )
        ? (int) $answers[ $metaKey ]
        : null;

      $this->memo[ $type ][ $metaKey ] = in_array( $answer, self::MODES, true ) ? $answer : null;
    }
  }


  private function answersFor( array $metaKeys, string $type ): array {
    $resolved = [];
    foreach ( $metaKeys as $metaKey ) {
      if ( isset( $this->memo[ $type ][ $metaKey ] ) ) {
        $resolved[ $metaKey ] = $this->memo[ $type ][ $metaKey ];
      }
    }

    return $resolved;
  }


}
