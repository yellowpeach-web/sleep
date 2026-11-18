<?php

namespace WPML\Infrastructure\WordPress\Component\Item\Application\Query;

use WPML\Core\Component\Post\Application\Query\PermalinkQueryInterface;

class PermalinkQuery implements PermalinkQueryInterface {


  public function getPermalink( int $postId ) {
    return get_permalink( $postId, false );
  }


  public function prefetch( array $postIds ) {
    if ( ! $postIds ) {
      return;
    }

    _prime_post_caches( $postIds, false, false );

    global $wpml_post_translations;
    if ( is_object( $wpml_post_translations ) && method_exists( $wpml_post_translations, 'prefetch_ids' ) ) {
      $wpml_post_translations->prefetch_ids( $postIds );
    }
  }


}
