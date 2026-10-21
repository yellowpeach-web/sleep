<?php

namespace WPML\Core\Component\Translation\Application\Authorization;

interface TranslationGroupAuthorizationInterface {


  public function currentUserCanJoinGroup( int $trid, string $wpmlElementType, string $targetLanguage ): bool;


}
