<?php

namespace WPML\UserInterface\Web\Core\Component\Settings\Application;

use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class SettingsController implements PageRenderInterface, ScriptDataProviderInterface {

  private $searchIndex;


  public function __construct( SearchIndex $searchIndex ) {
    $this->searchIndex = $searchIndex;
  }


  public function render() {
    echo '<div class="wrap">'
      . '<div id="wpml-settings-container"></div>'
      . '</div>';
  }


  public function jsWindowKey(): string {
    return 'wpmlSettingsScriptData';
  }


  public function initialScriptData(): array {
    return array(
      'index' => $this->searchIndex->getIndex(),
    );
  }


}
