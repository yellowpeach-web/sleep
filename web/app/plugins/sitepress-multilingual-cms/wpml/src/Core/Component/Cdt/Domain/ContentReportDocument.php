<?php

namespace WPML\Core\Component\Cdt\Domain;

class ContentReportDocument {

  const SCHEMA_VERSION  = 1;
  const MAX_POST_TYPES  = 200;
  const MAX_SLUG_LENGTH = 64;


  public static function build( array $modes, array $calculated, ?string $editor, ?string $engine, int $now ): array {
    $postTypes = [];

    foreach ( $modes as $postType => $mode ) {
      if ( count( $postTypes ) >= self::MAX_POST_TYPES ) {
        break;
      }
      if ( strlen( $postType ) === 0 || strlen( $postType ) > self::MAX_SLUG_LENGTH ) {
        continue;
      }

      $stats                  = $calculated[ $postType ] ?? null;
      $postTypes[ $postType ] = [
        'translation_mode' => $mode,
        'posts'            => $stats ? max( 0, $stats['postsCount'] ) : 0,
        'characters'       => $stats ? max( 0, $stats['charactersCount'] ) : 0,
        'coverage'         => $stats && $mode !== TranslationMode::NOT_TRANSLATABLE
          ? self::coverage( $stats['translationCoverage'] )
          : [],
      ];
    }

    return [
      'schema_version' => self::SCHEMA_VERSION,
      'reported_at'    => (string) DateTimeFormat::fromUnix( $now ),
      'editor'         => $editor,
      'engine'         => $engine,
      'post_types'     => $postTypes,
    ];
  }


  private static function coverage( array $coverage ): array {
    $kept = [];
    foreach ( $coverage as $code => $percent ) {
      if ( ! LanguageCode::isValid( $code ) ) {
        continue;
      }
      $kept[ $code ] = $percent > 100 ? 100.0 : ( $percent < 0 ? 0.0 : $percent );
    }

    return $kept;
  }


}
