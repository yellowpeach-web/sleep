<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Repository;

use WPML\Core\Component\Translation\Application\Repository\TranslatorNoteRepositoryInterface;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslatorNote;

class PostTranslatorNoteRepository implements TranslatorNoteRepositoryInterface {
  const TRANSLATOR_NOTE_META_KEY = TranslatorNote::POST_META_KEY;


  public function save( int $id, string $note ) {
    $result = update_post_meta( $id, self::TRANSLATOR_NOTE_META_KEY, $note );

    return $result !== false;
  }


}
