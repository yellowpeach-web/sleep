<?php

namespace WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config;

use WPML\PHP\Exception\Exception;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Config;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\Endpoint;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Notice;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Page;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Script;
use WPML\UserInterface\Web\Core\SharedKernel\Config\ScriptComponent;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Style;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\Updates\Update;

class Parser {

  const PARSE_TYPE_REST = 'rest';
  const PARSE_TYPE_AJAX = 'ajax';

  const ADMIN_PAGES_FILTER = 'wpml_admin_pages_config';

  private $configRaw;

  private $adminPagesFiltered = false;

  private $scriptComponentsCache = null;


  public function __construct( array $configRaw ) {
    $this->configRaw = $configRaw;

    $this->configRaw['adminPages'] =
      array_key_exists( 'adminPages', $configRaw ) &&
      is_array( $configRaw['adminPages'] )
        ? $configRaw['adminPages']
        : [];

    $this->configRaw['endpoints'] =
      array_key_exists( 'endpoints', $configRaw ) &&
      is_array( $configRaw['endpoints'] )
        ? $configRaw['endpoints']
        : [];

    $this->configRaw['interfaceMappings'] =
      array_key_exists( 'interfaceMappings', $configRaw ) &&
      is_array( $configRaw['interfaceMappings'] )
        ? $configRaw['interfaceMappings']
        : [];

    $this->configRaw['classDefinitions'] =
      array_key_exists( 'classDefinitions', $configRaw ) &&
      is_array( $configRaw['classDefinitions'] )
        ? $configRaw['classDefinitions']
        : [];

    $this->configRaw['scripts'] =
      array_key_exists( 'scripts', $configRaw ) &&
      is_array( $configRaw['scripts'] )
        ? $configRaw['scripts']
        : [];

    $this->configRaw['script-components'] =
      array_key_exists( 'script-components', $configRaw ) &&
      is_array( $configRaw['script-components'] )
        ? $configRaw['script-components']
        : [];

    $this->configRaw['styles'] =
      array_key_exists( 'styles', $configRaw ) &&
      is_array( $configRaw['styles'] )
        ? $configRaw['styles']
        : [];
  }


  public function parseInterfaceMappings() {
    return $this->configRaw['interfaceMappings'];
  }


  public function parseClassDefinitions() {
    return $this->configRaw['classDefinitions'];
  }


  public function parseAdminPages( ?Config $config = null ): Config {
    $config = $config ?? new Config();

    $this->addScriptComponentsTo( $config );

    foreach ( $this->adminPagesRaw() as $id => $raw ) {
      $page = new Page( $id );
      $this->parsePageFields( $page, $raw );

      $this->parsePageScripts( $page, $raw, $config );

      $this->parsePageStyles( $page, $raw );

      $this->rawEndpointsToObject(
        $raw['endpoints'] ?? [],
        $page
      );

      $config->addAdminPage( $page );
    }

    return $config;
  }


  public function removeAdminPageConfig( string $pageId ) {
    if ( array_key_exists( $pageId, $this->adminPagesRaw() ) ) {
      unset( $this->configRaw['adminPages'][$pageId] );
    }
  }


  private function adminPagesRaw(): array {
    if ( ! $this->adminPagesFiltered ) {
      $this->adminPagesFiltered = true;
      if ( function_exists( 'apply_filters' ) ) {
        $filtered = apply_filters( self::ADMIN_PAGES_FILTER, $this->configRaw['adminPages'] );
        if ( is_array( $filtered ) ) {
          $this->configRaw['adminPages'] = $this->sanitizeAdminPagesConfig( $filtered );
        }
      }
    }

    return (array) $this->configRaw['adminPages'];
  }


  private function sanitizeAdminPagesConfig( array $pages ): array {
    $sanitized = [];

    foreach ( $pages as $pageId => $pageConfig ) {
      if ( ! is_string( $pageId ) || '' === $pageId ) {
        $this->doingItWrongOnMalformedAdminPagesConfig(
          'Every wpml_admin_pages_config entry must be keyed by a string page id; a non-string key was dropped.'
        );
        continue;
      }

      if ( ! is_array( $pageConfig ) ) {
        $this->doingItWrongOnMalformedAdminPagesConfig(
          sprintf( 'The wpml_admin_pages_config entry for "%s" must be an array; it was dropped.', $pageId )
        );
        continue;
      }

      if ( array_key_exists( 'endpoints', $pageConfig ) && ! is_array( $pageConfig['endpoints'] ) ) {
        $this->doingItWrongOnMalformedAdminPagesConfig(
          sprintf( 'The "endpoints" of wpml_admin_pages_config page "%s" must be an array; it was dropped.', $pageId )
        );
        unset( $pageConfig['endpoints'] );
      } elseif ( array_key_exists( 'endpoints', $pageConfig ) ) {
        foreach ( $pageConfig['endpoints'] as $endpointId => $endpointConfig ) {
          if ( ! is_array( $endpointConfig ) ) {
            $this->doingItWrongOnMalformedAdminPagesConfig(
              sprintf(
                'The wpml_admin_pages_config endpoint "%s" on page "%s" must be an array; it was dropped.',
                is_string( $endpointId ) ? $endpointId : gettype( $endpointId ),
                $pageId
              )
            );
            unset( $pageConfig['endpoints'][ $endpointId ] );
          }
        }
      }

      $sanitized[ $pageId ] = $pageConfig;
    }

    return $sanitized;
  }


  private function doingItWrongOnMalformedAdminPagesConfig( $message ) {
    if ( function_exists( '_doing_it_wrong' ) ) {
      _doing_it_wrong(
        esc_html( self::ADMIN_PAGES_FILTER ),
        esc_html( $message ),
        '5.0.0'
      );
    }
  }


  public function parseStyles(): Config {
    $config = new Config();

    foreach ( $this->configRaw['styles'] as $id => $styleRaw ) {
      if ( ! array_key_exists( 'src', $styleRaw ) ) {
        continue;
      }

      $style = new Style( $id );
      $style->setSrc( $styleRaw['src'] );

      if ( array_key_exists( 'dependencies', $styleRaw ) ) {
        $style->setDependencies( $styleRaw['dependencies'] );
      }

      if ( array_key_exists( 'onlyRegister', $styleRaw ) ) {
        $style->setOnlyRegister( (bool) $styleRaw['onlyRegister'] );
      }

      $config->addStyle( $style );
    }

    return $config;
  }


  public function parseScripts(): Config {
    $config = new Config();

    $this->addScriptComponentsTo( $config );

    foreach ( $this->configRaw['scripts'] as $id => $scriptRaw ) {
      $scriptRaw['id'] = $scriptRaw['id'] ?? $id;
      if ( $script = $this->parseScript( $scriptRaw, $config->scriptComponents() ) ) {
        $config->addScript( $script );
      }
    }

    return $config;
  }


  public function parseContentStatsScripts() {
    $scripts = [];

    foreach ( $this->configRaw['contentStatsScripts'] as $id => $scriptRaw ) {

      $scriptRaw['id'] = $scriptRaw['id'] ?? $id;
      if ( $script = $this->parseScript( $scriptRaw ) ) {
        $scripts[] = $script;
      }
    }

    return $scripts;
  }


  public function parseCheckPosthogShouldRecordScript() {
    $scripts = [];

    foreach ( $this->configRaw['checkPosthogShouldRecord'] as $id => $scriptRaw ) {

      $scriptRaw['id'] = $scriptRaw['id'] ?? $id;
      if ( $script = $this->parseScript( $scriptRaw ) ) {
        $scripts[] = $script;
      }
    }

    return $scripts;
  }


  public function parseAdminNotices( ?Config $config = null ): Config {
    $config = $config ?? new Config();

    foreach ( $this->configRaw['adminNotices'] as $id => $raw ) {
      $notice = new Notice( $id );
      $this->parseNoticeFields( $notice, $raw );

      $this->parseNoticeScripts( $notice, $raw );

      $this->parseNoticeStyles( $notice, $raw );

      $endpoints = $this->parseEndpointsFor(
        $raw['endpoints'] ?? []
      );
      foreach ( $endpoints as $endpoint ) {
        $notice->addEndpoint( $endpoint );
      }

      $config->addAdminNotice( $notice );
    }

    return $config;
  }


  public function removeAdminNoticeConfig( string $noticeId ) {
    if ( array_key_exists( $noticeId, (array) $this->configRaw['adminNotices'] ) ) {
      unset( $this->configRaw['adminNotices'][$noticeId] );
    }
  }


  private function parsePageFields( Page $page, $pageConfig ) {
    $this->parsePageClassFields( $page, $pageConfig );

    if (
      array_key_exists( 'title', $pageConfig ) &&
      is_string( $pageConfig['title'] )
    ) {
      $page->setTitle( $pageConfig['title'] );
    }

    if (
      array_key_exists( 'parentId', $pageConfig ) &&
      is_string( $pageConfig['parentId'] )
    ) {
      $page->setParentId( $pageConfig['parentId'] );
    }

    if (
      array_key_exists( 'legacyParentId', $pageConfig ) &&
      is_string( $pageConfig['legacyParentId'] )
    ) {
      $page->setLegacyParentId( $pageConfig['legacyParentId'] );
    }

    if (
      array_key_exists( 'legacyExtension', $pageConfig ) &&
      is_string( $pageConfig['legacyExtension'] )
    ) {
      $page->setLegacyExtension( $pageConfig['legacyExtension'] );
    }

    if (
      array_key_exists( 'menuTitle', $pageConfig ) &&
      is_string( $pageConfig['menuTitle'] )
    ) {
      $page->setMenuTitle( $pageConfig['menuTitle'] );
    }

    if (
      array_key_exists( 'capability', $pageConfig ) &&
      is_string( $pageConfig['capability'] )
    ) {
      $page->setCapability( $pageConfig['capability'] );
    }

    if (
      array_key_exists( 'icon', $pageConfig ) &&
      is_string( $pageConfig['icon'] )
    ) {
      $page->setIcon( $pageConfig['icon'] );
    }

    if (
      array_key_exists( 'position', $pageConfig ) &&
      is_numeric( $pageConfig['position'] )
    ) {
      $page->setPosition( (int) $pageConfig['position'] );
    }

    if (
      array_key_exists( 'requiresWPMLSetupToBeCompleted', $pageConfig ) &&
      is_bool( $pageConfig['requiresWPMLSetupToBeCompleted'] )
    ) {
      $page->setRequiresWPMLSetupToBeCompleted( $pageConfig['requiresWPMLSetupToBeCompleted'] );
    }

  }


  private function parsePageClassFields( Page $page, $pageConfig ) {
    if (
      array_key_exists( 'controller', $pageConfig ) &&
      is_string( $pageConfig['controller'] ) &&
      $pageConfig['controller']
    ) {
      $page->setControllerClassName( $pageConfig['controller'] );
    }

    if (
      array_key_exists( 'requirements', $pageConfig ) &&
      is_string( $pageConfig['requirements'] ) &&
      $pageConfig['requirements']
    ) {
      $page->setRequirementsClassName( $pageConfig['requirements'] );
    }
  }


  private function parsePageScripts( Page $page, $pageConfig, Config $config ) {
    $scripts = $this->parseScriptsByConfig( $pageConfig, $page->id(), $config );
    foreach ( $scripts as $script ) {
      $page->addScript( $script );
    }
  }


  private function parseScriptsByConfig( $pageConfig, $fallbackId, ?Config $config = null ) {
    $scripts = [];
    $pageConfig['scripts'] = $pageConfig['scripts'] ?? [];
    if (
      is_array( $pageConfig['scripts'] ) &&
      array_key_exists( 'src', $pageConfig['scripts'] )
    ) {
      $pageConfig['scripts'] = [ $pageConfig['scripts'] ];
    }

    if ( ! is_array( $pageConfig['scripts'] ) ) {
      return [];
    }

    $scriptComponents = $config ? $config->scriptComponents() : [];

    foreach ( $pageConfig['scripts'] as $scriptRaw ) {
      $scriptRaw['id'] = $scriptRaw['id'] ?? $fallbackId;
      if ( $script = $this->parseScript( $scriptRaw, $scriptComponents ) ) {
        $scripts[] = $script;
      }
    }

    return $scripts;
  }


  private function parseScript( $scriptRaw, $scriptComponents = [] ) {
    if ( ! array_key_exists( 'src', $scriptRaw ) ) {
      return null;
    }

    $script = new Script( $scriptRaw['id'] );
    $script->setSrc( $scriptRaw['src'] );

    if ( array_key_exists( 'dataProvider', $scriptRaw ) ) {
      $script->setDataProvider( $scriptRaw['dataProvider'] );
    }

    if ( array_key_exists( 'prerequisites', $scriptRaw ) ) {
      $script->setPrerequisites( $scriptRaw['prerequisites'] );
    }

    if ( array_key_exists( 'components', $scriptRaw ) ) {
      $components = [];
      foreach ( $scriptRaw['components'] as $componentId ) {
        if ( array_key_exists( $componentId, $scriptComponents ) ) {
          $components[] = $scriptComponents[ $componentId ];
        }
      }
      $script->setComponents( $components );
    }

    if ( array_key_exists( 'dependencies', $scriptRaw ) ) {
      $script->setDependencies( $scriptRaw['dependencies'] );
    }

    if ( array_key_exists( 'endpoints', $scriptRaw ) ) {
      $this->rawEndpointsToObject(
        $scriptRaw['endpoints'] ?? [],
        $script
      );
    }

    if ( array_key_exists( 'styles', $scriptRaw ) ) {
      $script->setStyles( $scriptRaw['styles'] );
    }

    if ( array_key_exists( 'onlyRegister', $scriptRaw ) ) {
      $script->setOnlyRegister( (bool) $scriptRaw['onlyRegister'] );
    }

    if ( array_key_exists( 'usedOn', $scriptRaw ) ) {
      $script->setUsedOn( $scriptRaw['usedOn'] );
    }

    if ( array_key_exists( 'inFooter', $scriptRaw ) ) {
      $script->setInFooter( (bool) $scriptRaw['inFooter'] );
    }

    if ( array_key_exists( 'supportsHMR', $scriptRaw ) ) {
      $script->setSupportsHMR( (bool) $scriptRaw['supportsHMR'] );
    }

    return $script;
  }


  private function addScriptComponentsTo( Config $config ) {
    if ( $this->scriptComponentsCache === null ) {
      $this->scriptComponentsCache = [];
      foreach ( $this->configRaw['script-components'] as $id => $scriptComponentRaw ) {
        $scriptComponentRaw['id'] = $scriptComponentRaw['id'] ?? $id;
        $this->scriptComponentsCache[$id] = $this->parseScriptComponent( $scriptComponentRaw );
      }
    }

    foreach ( $this->scriptComponentsCache as $scriptComponent ) {
      $config->addScriptComponent( $scriptComponent );
    }
  }


  private function parseScriptComponent( $componentRaw ) {
    $scriptComponent = new ScriptComponent( $componentRaw['id'] );

    if ( array_key_exists( 'dataProvider', $componentRaw ) ) {
      $scriptComponent->setDataProvider( $componentRaw['dataProvider'] );
    }

    if ( array_key_exists( 'dependencies', $componentRaw ) ) {
      $scriptComponent->setDependencies( $componentRaw['dependencies'] );
    }

    if ( array_key_exists( 'endpoints', $componentRaw ) ) {
      $this->rawEndpointsToObject(
        $componentRaw['endpoints'] ?? [],
        $scriptComponent
      );
    }

    return $scriptComponent;
  }


  private function parsePageStyles( Page $page, $pageConfig ) {
    $styles = $this->parseStylesByConfig( $pageConfig, $page->id() );
    foreach ( $styles as $style ) {
      $page->addStyle( $style );
    }
  }


  private function parseStylesByConfig( $config, $fallbackId ) {
    $styles = [];

    $config['styles'] = $config['styles'] ?? [];
    if ( is_string( $config['styles'] ) ) {
      $config['styles'] = [ [ 'src' => $config['styles'] ] ];
    } else if (
      is_array( $config['styles'] ) &&
      array_key_exists( 'src', $config['styles'] )
    ) {
      $config['styles'] = [ $config['styles'] ];
    }

    if ( ! is_array( $config['styles'] ) ) {
      return [];
    }

    foreach ( $config['styles'] as $styleRaw ) {
      if ( ! array_key_exists( 'src', $styleRaw ) ) {
        continue;
      }

      $style = new Style( $styleRaw['id'] ?? $fallbackId );
      $style->setSrc( $styleRaw['src'] );

      if ( array_key_exists( 'dependencies', $styleRaw ) ) {
        $style->setDependencies( $styleRaw['dependencies'] );
      }

      $styles[] = $style;
    }

    return $styles;
  }


  public function parseAllAjaxEndpoints( ?Config $config = null ): Config {
    return $this->parseEndpoints( $config, self::PARSE_TYPE_AJAX );
  }


  public function parseAllRESTEndpoints( ?Config $config = null ): Config {
    return $this->parseEndpoints( $config, self::PARSE_TYPE_REST );
  }


  public function parseGeneralEndpoints( ?Config $config = null ): Config {
    $config    = $config ?? new Config();
    $endpoints = $this->parseEndpointsFor(
      is_array( $this->configRaw['endpoints'] )
        ? $this->configRaw['endpoints']
        : []
    );

    $config->setEndpoints( $endpoints );

    return $config;
  }


  public function parseUpdates() {
    $updates = [];

    foreach ( $this->configRaw['updates'] as $updateRaw ) {
      if (
        ! array_key_exists( 'handler', $updateRaw )
        || ! array_key_exists( 'id', $updateRaw )
      ) {
        continue;
      }

      $update = new Update( (int) $updateRaw['id'], $updateRaw['handler'] );

      if ( array_key_exists( 'tryOnlyOnce', $updateRaw ) ) {
        $update->setTryOnlyOnce( (bool) $updateRaw['tryOnlyOnce'] );
      }

      if ( array_key_exists( 'lazyLoad', $updateRaw ) ) {
        $update->setLazyLoad( (bool) $updateRaw['lazyLoad'] );
      }

      $updates[] = $update;
    }

    return $updates;
  }


  private function parseEndpoints( ?Config $config = null, $type = null ): Config {
    $config = $config ?? new Config();
    foreach ( $this->adminPagesRaw() as $page ) {
      $this->rawEndpointsToObject( $page['endpoints'] ?? [], $config, $type );
      foreach ( $page['scripts'] ?? [] as $script ) {
        $this->rawEndpointsToObject( $script['endpoints'] ?? [], $config, $type );
      }
    }

    foreach ( $this->configRaw['scripts'] as $script ) {
      $this->rawEndpointsToObject( $script['endpoints'] ?? [], $config, $type );
    }

    foreach ( $this->configRaw['script-components'] as $scriptComponent ) {
      $this->rawEndpointsToObject( $scriptComponent['endpoints'] ?? [], $config, $type );
    }

    $this->rawEndpointsToObject(
      is_array( $this->configRaw['endpoints'] )
        ? $this->configRaw['endpoints']
        : [],
      $config,
      $type
    );

    return $config;
  }


  private function rawEndpointsToObject( $endpointsRaw, $object, $type = null ) {
    if ( ! method_exists( $object, 'addEndpoint' ) ) {
      return;
    }

    $endpoints = $this->parseEndpointsFor( $endpointsRaw, $type );

    foreach ( $endpoints as $endpoint ) {
      $object->addEndpoint( $endpoint );
    }
  }


  private function parseEndpointsFor(
    $endpointsRaw,
    $type = null
  ) {
    $endpoints = [];
    foreach ( $endpointsRaw as $id => $raw ) {
      if (
        ! is_array( $raw ) ||
        ! array_key_exists( 'path', $raw )
      ) {
        continue;
      }
      $isAjax = array_key_exists( 'useAjax', $raw ) && $raw['useAjax'] === true;

      if ( ( $isAjax && $type === self::PARSE_TYPE_REST )
        || ( ! $isAjax && $type === self::PARSE_TYPE_AJAX ) ) {
        continue;
      }
      $endpoint = new Endpoint( $id, $raw['path'], $isAjax );

      if ( array_key_exists( 'method', $raw ) ) {
        $endpoint->setMethod( $raw['method'] );
      }

      if ( array_key_exists( 'handler', $raw ) ) {
        $endpoint->setHandler( $raw['handler'] );
      }

      if ( array_key_exists( 'version', $raw ) ) {
        $endpoint->setVersion( $raw['version'] );
      }

      if ( array_key_exists( 'capability', $raw ) ) {
        $endpoint->setCapability( $raw['capability'] );
      }

      if ( array_key_exists( 'args', $raw ) && is_array( $raw['args'] ) ) {
        $endpoint->setArgs( $raw['args'] );
      }

      $endpoints[] = $endpoint;
    }

    return $endpoints;
  }


  private function parseNoticeFields( Notice $notice, $noticeConfig ) {
    $this->parseNoticeClassFields( $notice, $noticeConfig );

    if (
      array_key_exists( 'onPages', $noticeConfig ) &&
      is_array( $noticeConfig['onPages'] )
    ) {
      foreach ( $noticeConfig['onPages'] as $existingPage ) {
        $notice->addOnPage( $existingPage );
      }
    }

    if (
      array_key_exists( 'capability', $noticeConfig ) &&
      is_string( $noticeConfig['capability'] )
    ) {
      $notice->setCapability( $noticeConfig['capability'] );
    }
  }


  private function parseNoticeClassFields( Notice $notice, $noticeConfig ) {
    if (
      array_key_exists( 'controller', $noticeConfig ) &&
      is_string( $noticeConfig['controller'] ) &&
      $noticeConfig['controller']
    ) {
      $notice->setControllerClassName( $noticeConfig['controller'] );
    }
  }


  private function parseNoticeScripts( Notice $notice, $noticeConfig ) {
    $scripts = $this->parseScriptsByConfig( $noticeConfig, $notice->id() );
    foreach ( $scripts as $script ) {
      $notice->addScript( $script );
    }
  }


  private function parseNoticeStyles( Notice $notice, $noticeConfig ) {
    $styles = $this->parseStylesByConfig( $noticeConfig, $notice->id() );
    foreach ( $styles as $style ) {
      $notice->addStyle( $style );
    }
  }


}
