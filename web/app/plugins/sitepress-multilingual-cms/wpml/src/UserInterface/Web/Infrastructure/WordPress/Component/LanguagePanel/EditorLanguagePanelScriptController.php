<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\LanguagePanel;

use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptPrerequisitesInterface;

class EditorLanguagePanelScriptController implements ScriptPrerequisitesInterface, ScriptDataProviderInterface {

  private $post;

  private $siteEditor;


  public function __construct(
    LanguagePanelScriptController $post,
    SiteEditorPanelScriptController $siteEditor
  ) {
    $this->post       = $post;
    $this->siteEditor = $siteEditor;
  }


  public function scriptPrerequisitesMet(): bool {
    return $this->siteEditor->scriptPrerequisitesMet() || $this->post->scriptPrerequisitesMet();
  }


  public function jsWindowKey(): string {
    return LanguagePanelScriptController::JS_WINDOW_KEY;
  }


  public function initialScriptData(): array {
    if ( $this->siteEditor->scriptPrerequisitesMet() ) {
      return $this->siteEditor->initialScriptData();
    }

    return $this->post->initialScriptData();
  }


}
