<?php

namespace WPML\Core\Component\Post\Application\LanguagePanel\Write;

interface PostWriteAuthorizationInterface {


  public function currentUserCanEditPost( int $postId ): bool;


  public function currentUserCanManageTranslations(): bool;


  public function currentUserIsTranslatorOrHigher(): bool;


  public function currentUserIsAdministrator(): bool;


  public function currentUserHoldsAdministratorRole(): bool;


  public function currentUserCanAssignRootPage(): bool;


  public function isLangPairAllowed( string $from, string $to, int $postId ): bool;


  public function currentUserCanJoinGroup( int $trid, string $elementType, string $language ): bool;


  public function isPostLocked( int $postId ): bool;


  public function currentUserId(): int;


}
