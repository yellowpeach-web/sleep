<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\LanguagePanel;

use WPML\Core\Component\Post\Application\LanguagePanel\EditorScreenQueryInterface;
use WPML\Core\Component\Post\Application\LanguagePanel\SiteEditorEntityTypes;
use WPML\Core\Component\Post\Application\LanguagePanel\TranslatableRestPostTypesQueryInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptPrerequisitesInterface;

class LanguagePanelScriptController implements ScriptPrerequisitesInterface, ScriptDataProviderInterface {

  const JS_WINDOW_KEY = 'wpmlLanguagePanel';

  const COPY_FROM_ORIGINAL_NONCE = 'copy_from_original_nonce';

  const GET_ORPHAN_POSTS_NONCE = 'get_orphan_posts_nonce';

  const GET_POSTS_FROM_TRID_NONCE = 'get_posts_from_trid_nonce';

  const CONNECT_TRANSLATIONS_NONCE = 'connect_translations_nonce';

  const PTC_ENGINE_STATUS_ENDPOINT = 'WPML\\TM\\PostEditScreen\\Endpoints\\GetPtcEngineStatus';

  private $screen;

  private $postTypes;


  public function __construct(
    EditorScreenQueryInterface $screen,
    TranslatableRestPostTypesQueryInterface $postTypes
  ) {
    $this->screen    = $screen;
    $this->postTypes = $postTypes;
  }


  public function scriptPrerequisitesMet(): bool {
    if ( ! $this->screen->isBlockEditorScreen() || ! $this->screen->isSetupComplete() ) {
      return false;
    }

    if ( $this->screen->isSiteEditorScreen() ) {
      return false;
    }

    $post = $this->screen->currentPost();
    if ( null === $post ) {
      return false;
    }

    return $this->isTranslatableRestType( $post['type'] ) || $this->isContentSetupOnlyScreen( $post['type'] );
  }


  private function isTranslatableRestType( string $postType ): bool {
    return in_array( $postType, $this->postTypes->names(), true );
  }


  private function isContentSetupOnlyScreen( string $postType ): bool {
    return $this->screen->canSeeContentSetup() && $this->screen->isRestPostType( $postType );
  }


  public function jsWindowKey(): string {
    return self::JS_WINDOW_KEY;
  }


  private function followedTypes( string $postType ): array {
    $types = SiteEditorEntityTypes::translatable( $this->postTypes->names() );
    if ( $this->isTranslatableRestType( $postType ) && ! in_array( $postType, $types, true ) ) {
      $types[] = $postType;
    }

    return $types;
  }


  public function initialScriptData(): array {
    $post = $this->screen->currentPost();
    if ( null === $post ) {
      return [];
    }

    return [
      'postId'                  => $post['id'],
      'postType'                => $post['type'],
      'contentSetupOnly'        => ! $this->isTranslatableRestType( $post['type'] ),
      'postTypeLabels'          => $this->screen->postTypeLabels( $post['type'] ),
      'rest'                    => $this->screen->restRoute( $post['type'] ),
      'types'                   => PanelTypesScriptData::of( $this->screen, $this->followedTypes( $post['type'] ) ),
      'ajaxUrl'                 => $this->screen->ajaxUrl(),
      'nonces'                  => [
        'copyFromOriginal'    => $this->screen->nonce( self::COPY_FROM_ORIGINAL_NONCE ),
        'getOrphanPosts'      => $this->screen->nonce( self::GET_ORPHAN_POSTS_NONCE ),
        'getPostsFromTrid'    => $this->screen->nonce( self::GET_POSTS_FROM_TRID_NONCE ),
        'connectTranslations' => $this->screen->nonce( self::CONNECT_TRANSLATIONS_NONCE ),
      ],
      'ajax'                    => [
        'ptcEngineStatus' => [
          'endpoint' => self::PTC_ENGINE_STATUS_ENDPOINT,
          'nonce'    => $this->screen->nonce( self::PTC_ENGINE_STATUS_ENDPOINT ),
        ],
      ],
      'isAutomaticTranslations' => $this->screen->isAutomaticTranslations(),
      'adminBar'                => $this->screen->isAdminBarShowing(),
      'trackingMode'            => $this->screen->postHogTrackingMode(),
      'urls'                    => [
        'draftsSetting'        => $this->screen->settingsUrl( '&section=ai-translation#translate-everything-drafts' ),
        'translationDashboard' => $this->screen->translationDashboardUrl(),
      ],
    ];
  }


}
