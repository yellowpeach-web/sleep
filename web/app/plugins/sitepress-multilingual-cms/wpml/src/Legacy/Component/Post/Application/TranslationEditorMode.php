<?php

namespace WPML\Legacy\Component\Post\Application;

class TranslationEditorMode {

  public function getBlockedPosts( array $postIds ): array {
    $blockedPostsRaw = \WPML_TM_Post_Edit_TM_Editor_Mode::get_blocked_posts( $postIds );
    if ( ! is_array( $blockedPostsRaw ) ) {
      return [];
    }

    $askedIds = array_fill_keys( array_map( 'intval', $postIds ), true );
    $blocked  = [];

    foreach ( $blockedPostsRaw as $key => $value ) {
      if ( $value === null ) {
        continue;
      }

      if ( is_int( $key ) && isset( $askedIds[ $key ] ) ) {
        $blocked[ $key ] = is_string( $value ) ? self::asPlainText( $value ) : '';
        continue;
      }

      $isIdLike = is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) );
      if ( $isIdLike && isset( $askedIds[ (int) $value ] ) && ! isset( $blocked[ (int) $value ] ) ) {
        $blocked[ (int) $value ] = '';
      }
    }

    return $blocked;
  }


  private static function asPlainText( string $reason ): string {
    return trim( html_entity_decode( $reason, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
  }


}
