<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application;

use WPML\Core\Port\PluginInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;


class TeaCalculationScriptController implements ScriptDataProviderInterface {

  private $plugin;


  public function __construct( PluginInterface $plugin ) {
    $this->plugin = $plugin;
  }


  public function jsWindowKey(): string {
    return 'wpmlTeaCalculationConfig';
  }


  public function initialScriptData(): array {
    return [
      'urls' => [
        'amsBaseUrl'     => $this->plugin->getAMSHost(),
        'connectedSites' => admin_url( 'admin.php?page=wpml-ai-translation-billing&settings=connected_sites' ),
        'heldOutDocuments' => admin_url( 'admin.php?page=tm%2Fmenu%2Fmain.php&holdout=1' ),
      ]
    ];
  }


}
