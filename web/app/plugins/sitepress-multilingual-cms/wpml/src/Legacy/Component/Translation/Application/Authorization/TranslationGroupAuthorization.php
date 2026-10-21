<?php

namespace WPML\Legacy\Component\Translation\Application\Authorization;

use WPML\Core\Component\Translation\Application\Authorization\TranslationGroupAuthorizationInterface;

class TranslationGroupAuthorization implements TranslationGroupAuthorizationInterface {


  public function currentUserCanJoinGroup( int $trid, string $wpmlElementType, string $targetLanguage ): bool {
    if ( ! class_exists( \WPML\Translation\TranslationGroupAuthorization::class ) ) {
      return false;
    }

    return \WPML\Translation\TranslationGroupAuthorization::currentUserCanJoinGroup(
      $trid,
      $wpmlElementType,
      $targetLanguage
    );
  }


}
