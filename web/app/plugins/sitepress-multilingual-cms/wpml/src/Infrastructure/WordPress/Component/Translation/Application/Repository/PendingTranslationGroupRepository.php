<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Repository;

use WPML\Core\Component\Translation\Application\Repository\PendingTranslationGroupRepositoryInterface;
use WPML\Core\Component\Translation\Domain\PendingTranslationGroup;

class PendingTranslationGroupRepository implements PendingTranslationGroupRepositoryInterface {

  const META_KEY = '_wpml_pending_translation_group';


  public function remember( int $elementId, PendingTranslationGroup $group ): void {
    if ( $elementId <= 0 || $group->getTrid() <= 0 ) {
      return;
    }

    update_post_meta(
      $elementId,
      self::META_KEY,
      [
        'trid'        => $group->getTrid(),
        'source_lang' => $group->getSourceLanguageCode(),
      ]
    );
  }


  public function find( int $elementId ): ?PendingTranslationGroup {
    if ( $elementId <= 0 ) {
      return null;
    }

    $stored = get_post_meta( $elementId, self::META_KEY, true );
    if (
      ! is_array( $stored )
      || ! isset( $stored['trid'], $stored['source_lang'] )
      || ! is_numeric( $stored['trid'] )
      || (int) $stored['trid'] <= 0
      || ! is_string( $stored['source_lang'] )
      || '' === $stored['source_lang']
    ) {
      return null;
    }

    return new PendingTranslationGroup( (int) $stored['trid'], $stored['source_lang'] );
  }


  public function forget( int $elementId ): void {
    if ( $elementId <= 0 ) {
      return;
    }

    delete_post_meta( $elementId, self::META_KEY );
  }


}
