<?php

namespace WPML\Core\Component\Translation\Domain\Links;

final class ContentLinks {

  public static function signature( string $html ): array {
    $urls = [];

    if ( preg_match_all( '/\shref=["\']([^"\']+)["\']/i', $html, $m ) ) {
      $urls = $m[1];
    }

    if ( false !== strpos( $html, '<!-- wp:' ) ) {
      if ( preg_match_all( '/<!-- wp:.*?-->/s', $html, $blocks ) ) {
        foreach ( $blocks[0] as $block ) {
          if ( preg_match_all( '#https?:\\\\?/\\\\?/[^"\s]+#', $block, $m2 ) ) {
            foreach ( $m2[0] as $url ) {
              $urls[] = str_replace( '\\/', '/', $url );
            }
          }
        }
      }
    }

    $urls = array_values( array_unique( $urls ) );
    sort( $urls );

    return $urls;
  }


  public static function languagesNamedIn( array $urls, array $active, array $hostToLang = [] ): array {
    $found = [];

    foreach ( $urls as $url ) {
      $path  = trim( (string) parse_url( $url, PHP_URL_PATH ), '/' );
      $first = explode( '/', $path )[0];
      if ( in_array( $first, $active, true ) ) {
        $found[] = $first;
      }

      parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
      if ( isset( $query['lang'] ) && in_array( $query['lang'], $active, true ) ) {
        $found[] = $query['lang'];
      }

      $host = parse_url( $url, PHP_URL_HOST );
      if ( $host && isset( $hostToLang[ $host ] ) ) {
        $found[] = $hostToLang[ $host ];
      }
    }

    return array_values( array_unique( $found ) );
  }


}
