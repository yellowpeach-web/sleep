<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\Component\Dashboard\Application\DashboardController;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\DashboardRequirements;
use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptPrerequisitesInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Page;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageConfigUserInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Ate\AteAutoRegister;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Wcml\WcmlBonded;

class TranslationsTabStripDispatcher implements
  PageRenderInterface,
  PageConfigUserInterface,
  ScriptPrerequisitesInterface,
  ScriptDataProviderInterface {

  const DEFAULT_TAB = 'dashboard';

  /**
   * Canonical "is Translation Management allowed?" check (reads
   * `WPML(setup)['is-tm-allowed']`). A blog license sets it false; the
   * TM-only tabs (Dashboard / Translation Jobs / Translation Tasks)
   * hide when it is false, while Menus and Taxonomy stay available.
   *
   * @var DashboardRequirements
   */
  private $tmAllowed;

  /**
   * The Translation-Management / ATE tab bodies are nullable: they are
   * not needed on a blog license (their tabs are gated off) and the
   * Dashboard graph in particular hard-depends on TM (the legacy
   * `TranslationProxy`, included via `WPML_TM_PATH`) and cannot be
   * constructed when TM is not loaded. The composer-level factory in
   * `config-class-definitions.php` therefore passes `null` for these
   * when TM is not allowed; on a TM-enabled site they are real
   * instances (wpmldev-7163).
   *
   * @var ?DashboardController
   */
  private $dashboardController;

  private $dashboardTabBody;

  private $tabStripRenderer;

  private $jobsTabBody;

  private $menusTabBody;

  private $taxonomyTabBody;

  private $stringsTabBody;

  private $mediaTabBody;

  private $tasksTabBody;

  private $storeUrlsTabBody;

  private $glossaryTabBody;

  private $improveTranslationsTabBody;

  private $page;


  public function __construct(
    DashboardRequirements $tmAllowed,
    ?DashboardController $dashboardController,
    ?DashboardTabBody $dashboardTabBody,
    TabStripRenderer $tabStripRenderer,
    ?TranslationJobsTabBody $jobsTabBody,
    MenusTabBody $menusTabBody,
    TaxonomyTabBody $taxonomyTabBody,
    StringsTabBody $stringsTabBody,
    MediaTabBody $mediaTabBody,
    ?TranslationTasksTabBody $tasksTabBody,
    StoreUrlsTabBody $storeUrlsTabBody,
    ?GlossaryTabBody $glossaryTabBody,
    ?ImproveTranslationsTabBody $improveTranslationsTabBody
  ) {
    $this->tmAllowed                  = $tmAllowed;
    $this->dashboardController        = $dashboardController;
    $this->dashboardTabBody           = $dashboardTabBody;
    $this->tabStripRenderer           = $tabStripRenderer;
    $this->jobsTabBody                = $jobsTabBody;
    $this->menusTabBody               = $menusTabBody;
    $this->taxonomyTabBody            = $taxonomyTabBody;
    $this->stringsTabBody             = $stringsTabBody;
    $this->mediaTabBody               = $mediaTabBody;
    $this->tasksTabBody               = $tasksTabBody;
    $this->storeUrlsTabBody           = $storeUrlsTabBody;
    $this->glossaryTabBody            = $glossaryTabBody;
    $this->improveTranslationsTabBody = $improveTranslationsTabBody;

    $this->aliasLegacyContextOnEnqueue();
    $this->injectLegacyBodyClassForActiveTab();

    $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
    if ( $tab === '' ) {
      $tab = self::DEFAULT_TAB;
    }
    if ( in_array( $tab, array( self::DEFAULT_TAB, GlossaryTabBody::TAB, ImproveTranslationsTabBody::TAB ), true ) ) {
      AteAutoRegister::attempt();
    }
  }


  public function setPageConfig( Page $page ) {
    $this->page = $page;
    // DashboardTabBody is absent on a blog license (Dashboard tab hidden).
    if ( $this->dashboardTabBody !== null ) {
      $this->dashboardTabBody->setPage( $page );
    }
  }


  public function render() {
    $tabs       = $this->tabs();
    $activeId   = $this->resolveActiveTabId( $tabs );
    $activeBody = $this->resolveActiveBody( $activeId );

    echo '<style>body{background-color:#f0f0f1!important}</style>';

    $fullWidthTabs    = array( 'menus' );
    $isFullWidthTab   = in_array( $activeId, $fullWidthTabs, true );
    $wrapperWidthCls  = $isFullWidthTab ? '' : 'wpml:max-w-[1400px] ';

    echo '<div class="wrap">';
    echo '<div class="' . esc_attr( $wrapperWidthCls . 'wpml:mt-6 wpml:pb-16' ) . '">';


    if ( $this->page->title() ) {
      echo '<h1 class="wpml-translations-h1">' . esc_html( $this->page->title() ) . '</h1>';
    }

    $this->tabStripRenderer->render( $tabs, $activeId );

    echo '<div class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded wpml:p-5">';
    $activeBody->render();
    echo '</div>';

    echo '</div>';
    echo '</div>';
  }


  public function jsWindowKey(): string {
    return $this->dashboardController !== null
      ? $this->dashboardController->jsWindowKey()
      : '';
  }


  public function initialScriptData(): array {
    if ( $this->dashboardController !== null
      && $this->resolveActiveTabId( $this->tabs() ) === self::DEFAULT_TAB
    ) {
      return $this->dashboardController->initialScriptData();
    }
    return array();
  }


  public function scriptPrerequisitesMet(): bool {
    // blog license has no DashboardController at all.
    return $this->dashboardController !== null
      && $this->resolveActiveTabId( $this->tabs() ) === self::DEFAULT_TAB
      && $this->dashboardController->scriptPrerequisitesMet();
  }


  private function tabs(): array {
    $isAdminOrManager = static function (): bool {
      return current_user_can( 'manage_translations' );
    };

    // Translation Management gate. A blog license (TM not allowed)
    $tmAllowed = $this->tmAllowed->requirementsMet();

    $isManagerWithTm = static function () use ( $isAdminOrManager, $tmAllowed ): bool {
      return $tmAllowed && $isAdminOrManager();
    };

    $canManageTaxonomyTranslation = static function () use ( $isAdminOrManager ): bool {
      return $isAdminOrManager() || current_user_can( 'wpml_manage_taxonomy_translation' );
    };

    $tabs = array();

    $tabs[] = new TabDefinition(
      'dashboard',
      /* translators: Name of the Dashboard tab of WPML → Translations. */
      __( 'Dashboard', 'wpml' ),
      __( 'Central hub for translating all your site content.', 'wpml' ),
      $isManagerWithTm
    );

    $tabs[] = new TabDefinition(
      'menus',
      /* translators: Name of the Menus tab of WPML → Translations: the site's navigation menus. */
      __( 'Menus', 'wpml' ),
      __( 'Align menus in translated languages with your default menu structure.', 'wpml' ),
      $isAdminOrManager
    );

    $tabs[] = new TabDefinition(
      'strings',
      /* translators: Name of the Strings tab of WPML → Translations: the texts of the theme, the plugins and the site. Also used as link text and as a back-link to that tab. */
      __( 'Strings', 'wpml' ),
      __( 'Translate interface strings from themes, plugins, and your site directly.', 'wpml' ),
      static function (): bool {
        return self::stringsTabAllowed();
      }
    );

    $tabs[] = new TabDefinition(
      'taxonomy',
      /* translators: Singular name of a kind of content: a category, a tag or another way of grouping content. Used as a tab title on WPML → Translations and on the Translate Everything word-count list. */
      __( 'Taxonomy', 'wpml' ),
      __( 'Manually translate taxonomy terms independently of posts.', 'wpml' ),
      $canManageTaxonomyTranslation
    );

    $tabs[] = new TabDefinition(
      'store-urls',
      /* translators: Name of a tab of WPML → Translations: the web addresses of the WooCommerce shop pages. Noun phrase (the addresses of the store), not an instruction to store addresses. */
      __( 'Store URLs', 'wpml' ),
      __( 'Translate WooCommerce slugs for shop, products, categories and tags.', 'wpml' ),
      static function () use ( $isAdminOrManager ): bool {
        return WcmlBonded::rendersEmbeddedBodies() && $isAdminOrManager();
      }
    );

    $tabs[] = new TabDefinition(
      'media',
      /* translators: Name of the Media tab of WPML → Translations: the images and other files in the media library. */
      __( 'Media', 'wpml' ),
      __( 'Translate captions, alt text, and descriptions of media library items.', 'wpml' ),
      static function () use ( $isAdminOrManager ): bool {
        return defined( 'WPML_MEDIA_VERSION' ) && $isAdminOrManager();
      }
    );

    $tabs[] = new TabDefinition(
      'jobs',
      /* translators: Name of the Translation Jobs tab of WPML → Translations, also used as link text pointing at it. */
      __( 'Translation Jobs', 'wpml' ),
      __( 'Track and manage all translation jobs sent from this site.', 'wpml' ),
      $isManagerWithTm
    );

    // Under a blog license (TM not allowed) there are no translation
    $tabs[] = new TabDefinition(
      'tasks',
      /* translators: Name of the Translation Tasks tab of WPML → Translations, also used as link text pointing at it. */
      __( 'Translation Tasks', 'wpml' ),
      __( 'Tasks waiting for your review or translation.', 'wpml' ),
      static function () use ( $tmAllowed ): bool {
        return self::tasksTabAllowed( $tmAllowed );
      }
    );

    // blog license (TM not allowed) even if a stale
    // license — ATE is meaningless without Translation Management
    $canTranslateAndAte = static function () use ( $tmAllowed ): bool {
      return $tmAllowed && current_user_can( 'translate' ) && \WPML_TM_ATE_Status::is_enabled();
    };

    $tabs[] = new TabDefinition(
      'glossary',
      /* translators: The WPML Glossary: the terms that must always be translated the same way. It names the Glossary tab of WPML → Translations and appears in the list of Advanced Translation Editor features. */
      __( 'Glossary', 'wpml' ),
      __( 'Define how specific terms should always be translated.', 'wpml' ),
      $canTranslateAndAte
    );

    $tabs[] = new TabDefinition(
      'improve-translations',
      /* translators: Name of the Improve Translations tab of WPML → Translations. Verb phrase, imperative. */
      __( 'Improve Translations', 'wpml' ),
      __( 'Review and improve your automatic translations.', 'wpml' ),
      $canTranslateAndAte,
      'wpml-tab-badge-improve-translations'
    );

    return $tabs;
  }


  public static function stringsTabAllowed(): bool {
    return defined( 'WPML_ST_VERSION' )
      && ( current_user_can( 'manage_translations' ) || current_user_can( 'wpml_manage_string_translation' ) );
  }


  public static function tasksTabAllowed( bool $tmAllowed ): bool {
    return $tmAllowed && current_user_can( 'translate' );
  }


  private function resolveActiveTabId( array $tabs ): string {
    $defaultTab = $this->defaultTabFor( $tabs );

    $raw = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? $_GET['tab'] : '';
    if ( $raw === '' || preg_match( '/^[a-z0-9-]+$/', $raw ) !== 1 ) {
      return $defaultTab;
    }

    foreach ( $tabs as $tab ) {
      if ( $tab->id() === $raw && $tab->isVisible() ) {
        return $raw;
      }
    }
    return $defaultTab;
  }


  /**
   * Pick the landing tab when no `?tab=` is set. Dashboard wins when
   * visible (admins/managers with TM allowed). Otherwise fall back to
   * the first visible tab: translator-only users land on Translation
   * Tasks, and blog-license admins (TM not allowed, no Dashboard) land
   * on Menus — the first blog-eligible tab. Returns the bare default
   * if somehow nothing is visible, so the method always yields a
   * string.
   *
   * @param array<int, TabDefinition> $tabs
   */
  private function defaultTabFor( array $tabs ): string {
    foreach ( $tabs as $tab ) {
      if ( $tab->id() === self::DEFAULT_TAB && $tab->isVisible() ) {
        return self::DEFAULT_TAB;
      }
    }
    foreach ( $tabs as $tab ) {
      if ( $tab->isVisible() ) {
        return $tab->id();
      }
    }
    return self::DEFAULT_TAB;
  }


  private function resolveActiveBody( string $activeId ): PageRenderInterface {
    $bodies = array(
      'dashboard'            => $this->dashboardTabBody,
      'jobs'                 => $this->jobsTabBody,
      'menus'                => $this->menusTabBody,
      'taxonomy'             => $this->taxonomyTabBody,
      'strings'              => $this->stringsTabBody,
      'media'                => $this->mediaTabBody,
      'tasks'                => $this->tasksTabBody,
      'store-urls'           => $this->storeUrlsTabBody,
      'glossary'             => $this->glossaryTabBody,
      'improve-translations' => $this->improveTranslationsTabBody,
    );
    // The nullable (TM/ATE) bodies are absent on a blog license, but
    if ( isset( $bodies[ $activeId ] ) ) {
      return $bodies[ $activeId ];
    }

    return new PlaceholderTabBody( $activeId );
  }




  private function aliasesForActiveTab(): array {
    // Menus for a blog-license admin where TM is not allowed).
    $activeTab = $this->resolveActiveTabId( $this->tabs() );

    if ( $activeTab === 'jobs' && ! isset( $_GET['sm'] ) ) {
      return array( 'sm' => 'jobs' );
    }
    if ( $activeTab === 'tasks' ) {
      return array( 'page' => 'tm/menu/translations-queue.php' );
    }
    if ( $activeTab === 'menus' ) {
      return array( 'page' => 'sitepress-multilingual-cms/menu/menu-sync/menus-sync.php' );
    }

    return array();
  }


  private function injectLegacyBodyClassForActiveTab(): void {
    $activeTab = $this->resolveActiveTabId( $this->tabs() );

    $legacyBodyClassByTab = array(
      'strings' => 'wpml-string-translation-menu-string-translation-php',
    );
    if ( ! isset( $legacyBodyClassByTab[ $activeTab ] ) ) {
      return;
    }
    $legacyClass = $legacyBodyClassByTab[ $activeTab ];

    add_filter(
      'admin_body_class',
      function ( $classes ) use ( $legacyClass ) {
        return rtrim( $classes ) . ' ' . $legacyClass;
      }
    );
  }


  private function aliasLegacyContextOnEnqueue(): void {
    $aliases = $this->aliasesForActiveTab();
    if ( $aliases === array() ) {
      return;
    }

    $originals = array();
    foreach ( $aliases as $key => $_value ) {
      $originals[ $key ] = $_GET[ $key ] ?? null;
    }

    add_action(
      'admin_enqueue_scripts',
      function () use ( $aliases ) {
        foreach ( $aliases as $key => $value ) {
          $_GET[ $key ] = $value;
        }
      },
      0
    );
    add_action(
      'admin_enqueue_scripts',
      function () use ( $originals ) {
        foreach ( $originals as $key => $original ) {
          if ( $original === null ) {
            unset( $_GET[ $key ] );
          } else {
            $_GET[ $key ] = $original;
          }
        }
      },
      999
    );
  }


}
