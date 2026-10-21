<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config;

use WPML\DicInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\AssetInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Page as DomainPage;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Script;
use WPML\UserInterface\Web\Core\SharedKernel\Config\ScriptComponent;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Style;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\ApiInterface;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\PageInterface;

class AdminPage implements PageInterface {

  private $api;

  private $dic;

  private $registeredScripts = [];

  private $dataAppliedScripts = [];


  public function __construct( ApiInterface $api, DicInterface $dic ) {
    $this->api = $api;
    $this->dic = $dic;

    add_action( 'wp_print_scripts', [ $this, 'loadDataForEnqueuedScripts' ], 1 );
    add_action( 'admin_print_footer_scripts', [ $this, 'loadDataForEnqueuedScripts' ], 1 );
  }


  public function register( DomainPage $page, $onLoadPageHandle ) {
    $loadPage =
      function( string $hook ) use ( $page, $onLoadPageHandle ) {
        add_action(
          $hook,
          function() use ( $page, $onLoadPageHandle ) {
            $onLoadPageHandle( $page );
          }
        );
      };

    if ( $useLegacy = $page->legacyExtension() ) {
      $loadPage( $useLegacy );
      return;
    }

    if ( $wpPageId = $this->loadPage( $page ) ) {
      $loadPage( 'load-' . str_replace( '.php', '', $wpPageId ) );
    }
  }


  public function registerStyle( Style $style ) {
      wp_register_style(
        $style->id(),
        $this->assetUrl( $style ),
        $style->dependencies(),
        WPML_VERSION
      );
  }


  public function loadStyle( Style $style ) {
      wp_enqueue_style(
        $style->id(),
        $this->assetUrl( $style ),
        $style->dependencies(),
        WPML_VERSION
      );
  }


  private function endpointsAsScriptVars( $script ): string {
    $scriptVars = '';
    if ( $script->endpoints() ) {
      $endpoints = [
        'route' => [],
        'nonce' => $this->api->nonce()
      ];
      foreach ( $script->endpoints() as $endpoint ) {
        $endpoints['route'][$endpoint->id()] = [
          'url' => $this->api->getFullUrl( $endpoint ),
        ];
      }
      $jsonEndpoints = wp_json_encode( $endpoints ) ?: '{}';
      $scriptVars .= 'var ' . $script->idCamelCase() . 'Endpoints = ' . $jsonEndpoints . ';';
    }
    return $scriptVars;
  }


  public function registerScript( Script $script ) {
    $this->registeredScripts[] = $script;
    wp_register_script(
      $script->id(),
      $this->assetUrl( $script ),
      $script->dependencies(),
      WPML_VERSION,
      [
        'in_footer' => $script->inFooter(),
      ]
    );

    $scriptVars = $this->endpointsAsScriptVars( $script );

    if ( $scriptVars !== '' ) {
      wp_add_inline_script(
        $script->id(),
        $scriptVars,
        'before'
      );
    }

    wp_set_script_translations(
      $script->id(),
      'wpml',
      WPML_ROOT_DIR . '/languages/'
    );

    if ( $script->isLoaded() ) {
      $ids = array_merge( [ $script->id() ], $script->dependencies() );
      foreach ( $ids as $id ) {
        $styles = $script->styles( $id ) ?: [];
        foreach ( $styles as $styleId ) {
          wp_enqueue_style( $styleId );
        }
      }
    }
  }


  public function loadScript( Script $script ) {
    $this->registerScript( $script );
    wp_enqueue_script( $script->id() );

    $ids = array_merge( [ $script->id() ], $script->dependencies() );
    foreach ( $ids as $id ) {
      $script->loaded( $id );
      $styles = $script->styles( $id ) ?: [];
      foreach ( $styles as $styleId ) {
        wp_enqueue_style( $styleId );
      }
    }
  }


  public function deferScriptLoading( callable $callback ) {
    add_action( 'admin_enqueue_scripts', $callback, 20 );
  }


  private function scriptVar( $key, $data ): string {
    return 'var ' . $key . ' = ' . json_encode( $data ) . ';';
  }


  public function provideDataForScript(
    Script $script,
    string $jsWindowKey,
    $data
  ) {
    wp_add_inline_script(
      $script->id(),
      $this->scriptVar( $jsWindowKey, $data ),
      'before'
    );
  }


  public function loadDataForEnqueuedScripts(): void {
    foreach ( $this->registeredScripts as $script ) {
      if ( in_array( $script->id(), $this->dataAppliedScripts, true ) ) {
        continue;
      }

      if ( ! wp_script_is( $script->id(), 'enqueued' ) ) {
        continue;
      }

      $this->dataAppliedScripts[] = $script->id();

      $scriptVars = $this->endpointsAsScriptVars( $script );

      if ( $dataProviderClass = $script->dataProvider() ) {
        $dataProvider = $this->makeDataProvider( $dataProviderClass );
        $scriptVars .= $this->scriptVar(
          $dataProvider->jsWindowKey(),
          $dataProvider->initialScriptData()
        );
      }

      foreach ( $script->components() as $component ) {
        $scriptVars .= $this->endpointsAsScriptVars( $component );

        if ( $dataProviderClass = $component->dataProvider() ) {
          $dataProvider = $this->makeDataProvider( $dataProviderClass );
          $scriptVars .= $this->scriptVar(
            $dataProvider->jsWindowKey(),
            $dataProvider->initialScriptData()
          );
        }
      }

      if ( $scriptVars !== '' ) {
        wp_add_inline_script(
          $script->id(),
          $scriptVars,
          'before'
        );
      }
    }
  }


  private function makeDataProvider( $dataProviderClass ) {
    $dataProvider = $this->dic->make( $dataProviderClass );

    if ( ! $dataProvider instanceof ScriptDataProviderInterface ) {
      throw new \InvalidArgumentException(
        'Invalid data provider. It must implement ' .
        ScriptDataProviderInterface::class
      );
    }

    return $dataProvider;
  }


  private function loadPage( DomainPage $page ) {
    if ( $page->legacyExtension() ) {
      return null;
    }

    if ( $page->parentId() ) {
      return $this->loadSubPage( $page );
    }

    if ( $page->legacyParentId() ) {
      return $this->legacyLoadPage( $page );
    }

    if ( $page->title() === '' && $page->menuTitle() === '' ) {
      return null;
    }

    return add_menu_page(
      $page->title(),
      $page->menuTitle(),
      $page->capability(),
      $page->id(),
      [ $page, 'render' ],
      $page->icon(),
      $page->position()
    );
  }


  private function legacyLoadPage( DomainPage $page ): string {
    if ( ! $parentId = $page->legacyParentId() ) {
      return '';
    }

    add_action(
      'wpml_admin_menu_configure',
      function( $menuId ) use ( $page, $parentId )  {
        if ( $menuId !== $parentId ) {
          return;
        }

        $menu = [
          'order'      => $page->position(),
          'page_title' => $page->title(),
          'menu_title' => $page->menuTitle(),
          'capability' => $this->api->capabilityPlusAdmin( $page->capability() ),
          'menu_slug'  => $page->id(),
          'function'   => [ $page, 'render' ],
        ];

        $menu = apply_filters( 'wpml_menu_page', $menu );

        do_action( 'wpml_admin_menu_register_item', $menu );
      }
    );

    $firstPart = $page->position() > 1 ? $parentId : 'toplevel';
    return strtolower( $firstPart . '_page_' . $page->id() );
  }


  private function loadSubPage( DomainPage $page ): string {
    return (string) add_submenu_page(
      $page->parentId() ?: '',
      $page->title(),
      $page->menuTitle(),
      $this->api->capabilityPlusAdmin( $page->capability() ),
      $page->id(),
      [ $page, 'render' ],
      $page->position()
    );
  }


  private function assetUrl( AssetInterface $asset ): string {
    $relativePath = $asset->src() ?: '';

    if ( defined( 'WPML_HMR_SERVER' ) && $asset->supportsHMR() ) {
      return WPML_HMR_SERVER . preg_replace( '#public/(js|css)/#', '', $relativePath );
    }

      return plugins_url( $relativePath, WPML_PUBLIC_DIR );
  }


}
