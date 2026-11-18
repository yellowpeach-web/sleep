<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\LanguagePanel;

use WPML\Core\Component\Post\Application\LanguagePanel\EditorScreenQueryInterface;
use WPML\Core\Component\Post\Application\LanguagePanel\SiteEditorEntityTypes;
use WPML\Core\Component\Post\Application\LanguagePanel\TranslatableRestPostTypesQueryInterface;
use WPML\Core\SharedKernel\Component\Language\Application\Query\Dto\LanguageDto;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptPrerequisitesInterface;

class SiteEditorPanelScriptController implements ScriptPrerequisitesInterface, ScriptDataProviderInterface {

  const SCREEN = 'site-editor';

  private $screen;

  private $postTypes;

  private $languages;


  public function __construct(
    EditorScreenQueryInterface $screen,
    TranslatableRestPostTypesQueryInterface $postTypes,
    LanguagesQueryInterface $languages
  ) {
    $this->screen    = $screen;
    $this->postTypes = $postTypes;
    $this->languages = $languages;
  }


  public function scriptPrerequisitesMet(): bool {
    if (
      ! $this->screen->isSiteEditorScreen()
      || ! $this->screen->isSetupComplete()
      || ! $this->screen->canEditThemeOptions()
    ) {
      return false;
    }

    return [] !== $this->types();
  }


  private function types(): array {
    return SiteEditorEntityTypes::translatable( $this->postTypes->names() );
  }


  public function jsWindowKey(): string {
    return LanguagePanelScriptController::JS_WINDOW_KEY;
  }


  public function initialScriptData(): array {
    return [
      'screen'                  => self::SCREEN,
      'types'                   => PanelTypesScriptData::of( $this->screen, $this->types() ),
      'languages'               => [
        'default' => $this->languages->getDefaultCode(),
        'active'  => array_map(
          function ( LanguageDto $language ) {
            return $language->getCode();
          },
          array_values( $this->languages->getActive() )
        ),
      ],
      'ajaxUrl'                 => $this->screen->ajaxUrl(),
      'nonces'                  => [
        'copyFromOriginal'    => $this->screen->nonce( LanguagePanelScriptController::COPY_FROM_ORIGINAL_NONCE ),
        'getOrphanPosts'      => $this->screen->nonce( LanguagePanelScriptController::GET_ORPHAN_POSTS_NONCE ),
        'getPostsFromTrid'    => $this->screen->nonce( LanguagePanelScriptController::GET_POSTS_FROM_TRID_NONCE ),
        'connectTranslations' => $this->screen->nonce( LanguagePanelScriptController::CONNECT_TRANSLATIONS_NONCE ),
      ],
      'ajax'                    => [
        'ptcEngineStatus' => [
          'endpoint' => LanguagePanelScriptController::PTC_ENGINE_STATUS_ENDPOINT,
          'nonce'    => $this->screen->nonce( LanguagePanelScriptController::PTC_ENGINE_STATUS_ENDPOINT ),
        ],
      ],
      'isAutomaticTranslations' => $this->screen->isAutomaticTranslations(),
      'trackingMode'            => $this->screen->postHogTrackingMode(),
      'urls'                    => [
        'draftsSetting'        => $this->screen->settingsUrl( '&section=ai-translation#translate-everything-drafts' ),
        'translationDashboard' => $this->screen->translationDashboardUrl(),
      ],
    ];
  }


}
