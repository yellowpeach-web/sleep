<?php

namespace WPML\Legacy\Component\Post\Application\LanguagePanel\Write;

use WPML\Core\Component\Post\Application\LanguagePanel\Write\PostWriteAuthorizationInterface;

class PostWriteAuthorization implements PostWriteAuthorizationInterface {

  private $langPairPermissions;


  public function currentUserCanEditPost( int $postId ): bool {
    return current_user_can( 'edit_post', $postId );
  }


  public function currentUserCanManageTranslations(): bool {
    return \WPML\LIB\WP\User::canManageTranslations();
  }


  public function currentUserIsTranslatorOrHigher(): bool {
    return \WPML\LIB\WP\User::currentUserIsTranslatorOrHigher();
  }


  public function currentUserIsAdministrator(): bool {
    return \WPML\LIB\WP\User::currentUserIsAdmin();
  }


  public function currentUserHoldsAdministratorRole(): bool {
    return \WPML\LIB\WP\User::isAdministrator();
  }


  public function currentUserCanAssignRootPage(): bool {
    return \WPML\UrlHandling\RootPage\RootPageCommand::restPolicy()->permits();
  }


  public function isLangPairAllowed( string $from, string $to, int $postId ): bool {
    if ( null === $this->langPairPermissions ) {
      $this->langPairPermissions = new \WPML\TranslationRoles\LangPairPermissions();
    }

    return $this->langPairPermissions->is_allowed( $from, $to, $postId );
  }


  public function currentUserCanJoinGroup( int $trid, string $elementType, string $language ): bool {
    return \WPML\Translation\TranslationGroupAuthorization::currentUserCanJoinGroup( $trid, $elementType, $language );
  }


  public function isPostLocked( int $postId ): bool {
    $lock = get_post_meta( $postId, '_edit_lock', true );
    if ( ! is_string( $lock ) || '' === $lock ) {
      return false;
    }

    $parts = explode( ':', $lock );
    $time  = (int) $parts[0];
    $last  = isset( $parts[1] ) ? $parts[1] : get_post_meta( $postId, '_edit_last', true );
    $user  = is_numeric( $last ) ? (int) $last : 0;

    if ( ! $user || ! get_userdata( $user ) ) {
      return false;
    }

    $filtered = apply_filters( 'wp_check_post_lock_window', 150 );
    $window   = is_int( $filtered ) && $filtered > 0 ? $filtered : 150;

    return $time > 0 && $time > time() - $window && get_current_user_id() !== $user;
  }


  public function currentUserId(): int {
    return get_current_user_id();
  }


}
