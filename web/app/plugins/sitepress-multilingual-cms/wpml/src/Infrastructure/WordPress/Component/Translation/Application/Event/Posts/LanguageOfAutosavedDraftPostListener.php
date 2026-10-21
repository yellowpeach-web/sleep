<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Event\Posts;

use WPML\Core\Component\Translation\Application\Repository\PendingTranslationGroupRepositoryInterface;
use WPML\Core\Component\Translation\Application\Service\LanguageService;
use WPML\Core\Component\Translation\Application\Service\UnauthorizedTranslationGroupJoinException;
use WPML\Core\Component\Translation\Domain\PendingTranslationGroup;
use WPML\Core\Port\Event\EventListenerInterface;
use WPML\Core\Security\ExecutionContext\ExecutionContext;
use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\PHP\Exception\InvalidArgumentException;
use WP_Post;

class LanguageOfAutosavedDraftPostListener implements EventListenerInterface {

  private $languageService;

  private $languageQuery;

  private $pendingGroups;


  public function __construct(
    LanguageService $languageInfoService,
    LanguagesQueryInterface $languageQuery,
    PendingTranslationGroupRepositoryInterface $pendingGroups
  ) {
    $this->languageService = $languageInfoService;
    $this->languageQuery   = $languageQuery;
    $this->pendingGroups   = $pendingGroups;
  }


  public function setLanguage( $postId, $post, $update, $postBefore ) {
    if ( ! $this->isDoingAutosave() ) {
      return;
    }

    if ( ! $update || $post->post_status !== 'draft' || ! $postBefore || $postBefore->post_status !== 'auto-draft' ) {
      return;
    }

    $currentLanguage = $this->languageQuery->getCurrentLanguageCode();
    $context         = $this->currentContext();
    $pendingGroup    = $this->pendingGroups->find( $postId );

    try {
      $this->setLanguageOfPost( $context, $postId, $post->post_type, $currentLanguage, $pendingGroup );
    } catch ( UnauthorizedTranslationGroupJoinException $e ) {
      try {
        $this->setLanguageOfPost( $context, $postId, $post->post_type, $currentLanguage, null );
      } catch ( InvalidArgumentException $e ) {
      } catch ( UnauthorizedTranslationGroupJoinException $e ) {
      }
    } catch ( InvalidArgumentException $e ) {
    }

    if ( $pendingGroup ) {
      $this->pendingGroups->forget( $postId );
    }
  }


  private function setLanguageOfPost(
    ExecutionContext $context,
    int $postId,
    string $postType,
    string $languageCode,
    ?PendingTranslationGroup $group
  ): void {
    $this->languageService->setLanguageOfElement(
      $context,
      $postId,
      'post',
      $postType,
      $languageCode,
      $group ? $group->getSourceLanguageCode() : null,
      $group ? $group->getTrid() : null
    );
  }


  protected function currentContext(): ExecutionContext {
    return ExecutionContextHolder::current();
  }


  protected function isDoingAutosave(): bool {
    return defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
  }


}
