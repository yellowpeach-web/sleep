<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\Component\Dashboard\Application\DashboardRequirements;
use WPML\UserInterface\Web\Core\Component\Settings\Application\SettingsController;
use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class SettingsSectionDispatcher implements PageRenderInterface, ScriptDataProviderInterface {

  private $landing;

  private $customFields;

  private $customTermMeta;

  private $postTypes;

  private $taxonomies;

  private $compatibility;

  private $media;

  private $translationEditor;

  private $postsPagesSync;

  private $deletingContent;

  private $dataSharing;

  private $translatedDocuments;

  private $customXml;

  private $languages;

  private $languageSwitchers;

  private $urlsAndSeo;

  private $translators;

  private $aiTranslation;

  private $stringTranslation;

  private $wcml;

  private $multicurrency;

  private $accessibleSlugs;

  /**
   * Canonical "is Translation Management allowed?" check (reads
   * `WPML(setup)['is-tm-allowed']`). A blog license sets it false, in
   * which case the sections that are not part of the blog-license
   * settings surface are dropped from the index (see
   * `$blogLicenseHiddenSections`) — matching the old GUI, where a blog
   * license only exposed Languages, Theme & plugins localization and the
   * `translation-options.php` Settings page (wpmldev-7163).
   *
   * @var DashboardRequirements
   */
  private $tmAllowed;

  /**
   * Section slugs dropped from the Settings index on a blog license, so
   * the new GUI matches what the old GUI exposed there.
   *
   * Verified against the legacy blog-license Settings page
   * (`menu/translation-options.php`), which renders only: Posts and
   * pages synchronization, Login and registration pages (folded into
   * Compatibility here), Post Types Translation, Taxonomies Translation
   * and Media Translation — plus the standalone Languages and Theme &
   * plugins pages (Languages / Language Switchers / URLs and SEO /
   * Compatibility). Everything below was absent there:
   *  - ai-translation / translation-editor / translators / string-translation
   *    — Translation-Management / ATE / String-Translation features;
   *  - custom-xml — was a tab on the CMS-only TM Settings page;
   *  - translated-documents / custom-fields / custom-term-meta — only on
   *    the TM Settings (MCS) page, never on the blog-license Settings.
   *
   * @var string[]
   */
  private static $blogLicenseHiddenSections = array(
    'ai-translation',
    'translation-editor',
    'translators',
    'string-translation',
    'custom-xml',
    'translated-documents',
    'custom-fields',
    'custom-term-meta',
  );


  public function __construct(
    DashboardRequirements $tmAllowed,
    SettingsController $landing,
    CustomFieldsTranslationController $customFields,
    CustomTermMetaTranslationController $customTermMeta,
    PostTypesTranslationController $postTypes,
    TaxonomiesTranslationController $taxonomies,
    CompatibilityController $compatibility,
    MediaTranslationSettingsController $media,
    TranslationEditorController $translationEditor,
    PostsAndPagesSyncController $postsPagesSync,
    DeletingContentController $deletingContent,
    DataSharingController $dataSharing,
    TranslatedDocumentsOptionsController $translatedDocuments,
    CustomXmlConfigurationController $customXml,
    LanguagesController $languages,
    LanguageSwitchersController $languageSwitchers,
    UrlsAndSeoController $urlsAndSeo,
    TranslatorsController $translators,
    AiTranslationController $aiTranslation,
    StringTranslationController $stringTranslation,
    WcmlSettingsController $wcml,
    MulticurrencyController $multicurrency
  ) {
    $this->tmAllowed           = $tmAllowed;
    $this->landing             = $landing;
    $this->customFields        = $customFields;
    $this->customTermMeta      = $customTermMeta;
    $this->postTypes           = $postTypes;
    $this->taxonomies          = $taxonomies;
    $this->compatibility       = $compatibility;
    $this->media               = $media;
    $this->translationEditor   = $translationEditor;
    $this->postsPagesSync      = $postsPagesSync;
    $this->deletingContent     = $deletingContent;
    $this->dataSharing         = $dataSharing;
    $this->translatedDocuments = $translatedDocuments;
    $this->customXml           = $customXml;
    $this->languages           = $languages;
    $this->languageSwitchers   = $languageSwitchers;
    $this->urlsAndSeo          = $urlsAndSeo;
    $this->translators         = $translators;
    $this->aiTranslation       = $aiTranslation;
    $this->stringTranslation   = $stringTranslation;
    $this->wcml                = $wcml;
    $this->multicurrency       = $multicurrency;

    $this->aliasLegacySubmenuParam();
    $this->aliasLegacyPageOnEnqueue();
  }


  public function render() {
    $section = $this->currentSection();

    if ( $section === '' ) {
      $this->landing->render();
      return;
    }

    $sub = $this->resolveSection( $section );
    if ( $sub === null ) {
      $this->landing->render();
      return;
    }

    if ( ! in_array( $section, $this->accessibleSectionSlugs(), true ) ) {
      $this->landing->render();
      return;
    }

    ob_start();
    $sub->render();
    $body = (string) ob_get_clean();
    $body = $this->preserveSectionInLinks( $body, $section );

    echo '<div class="wrap">';
    echo '<div class="wpml:max-w-3xl wpml:mt-6 wpml:pb-16">';
    $this->renderBackLink();
    echo $body;
    echo '</div>';
    echo '</div>';
  }


  public function jsWindowKey(): string {
    return $this->landing->jsWindowKey();
  }


  public function initialScriptData(): array {
    $data = $this->landing->initialScriptData();

    if ( isset( $data['index'] ) && is_array( $data['index'] ) ) {
      $data['index'] = $this->accessibleIndex();
    }

    return $data;
  }


  private function accessibleIndex(): array {
    $data  = $this->landing->initialScriptData();
    $index = isset( $data['index'] ) && is_array( $data['index'] ) ? $data['index'] : array();

    $index = apply_filters( 'wpml_settings_search_index', $index );

    // Drop the sections that the old GUI never exposed on a blog license
    // (see `$blogLicenseHiddenSections`). Filtering here also gates
    $index = $this->filterIndexForBlogLicense( $index );

    return self::filterIndexByCapability( $index );
  }


  /**
   * On a blog license (TM not allowed), drop the sections that the old
   * GUI never exposed there (see `$blogLicenseHiddenSections`). On a
   * TM-enabled site the index is returned unchanged. Index keys are
   * renumbered so the JS-side `index[]` shape stays a packed array.
   *
   * @param array<int, array<string, mixed>> $index
   *
   * @return array<int, array<string, mixed>>
   */
  private function filterIndexForBlogLicense( array $index ): array {
    if ( $this->tmAllowed->requirementsMet() ) {
      return $index;
    }

    return array_values(
      array_filter(
        $index,
        static function ( $section ) {
          if ( ! is_array( $section ) || ! isset( $section['href'] ) || ! is_string( $section['href'] ) ) {
            return false;
          }
          $query = (string) wp_parse_url( $section['href'], PHP_URL_QUERY );
          $args  = array();
          wp_parse_str( $query, $args );
          $slug = isset( $args['section'] ) && is_string( $args['section'] ) ? $args['section'] : '';
          return ! in_array( $slug, self::$blogLicenseHiddenSections, true );
        }
      )
    );
  }


  private function accessibleSectionSlugs(): array {
    if ( $this->accessibleSlugs !== null ) {
      return $this->accessibleSlugs;
    }
    $slugs = array();
    foreach ( $this->accessibleIndex() as $row ) {
      if ( ! is_array( $row ) || ! isset( $row['href'] ) || ! is_string( $row['href'] ) ) {
        continue;
      }
      $query = (string) wp_parse_url( $row['href'], PHP_URL_QUERY );
      $args  = array();
      wp_parse_str( $query, $args );
      if ( isset( $args['section'] ) && is_string( $args['section'] ) && $args['section'] !== '' ) {
        $slugs[] = $args['section'];
      }
    }
    $this->accessibleSlugs = $slugs;
    return $slugs;
  }


  private static function filterIndexByCapability( array $index ): array {
    return array_values(
      array_filter(
        $index,
        static function ( $section ) {
          if ( ! is_array( $section ) ) {
            return false;
          }
          $cap = isset( $section['capability'] ) && is_string( $section['capability'] ) && $section['capability'] !== ''
            ? $section['capability']
            : 'manage_translations';
          return current_user_can( $cap );
        }
      )
    );
  }


  private function currentSection(): string {
    $raw = isset( $_GET['section'] ) ? $_GET['section'] : '';
    if ( ! is_string( $raw ) ) {
      return '';
    }
    return preg_match( '/^[a-z0-9-]+$/', $raw ) === 1 ? $raw : '';
  }


  private function resolveSection( string $section ) {
    $map = array(
      'custom-fields'        => $this->customFields,
      'custom-term-meta'     => $this->customTermMeta,
      'post-types'           => $this->postTypes,
      'taxonomies'           => $this->taxonomies,
      'compatibility'        => $this->compatibility,
      'media'                => $this->media,
      'translation-editor'   => $this->translationEditor,
      'posts-pages-sync'     => $this->postsPagesSync,
      'deleting-content'     => $this->deletingContent,
      'data-sharing'         => $this->dataSharing,
      'translated-documents' => $this->translatedDocuments,
      'custom-xml'           => $this->customXml,
      'languages'            => $this->languages,
      'language-switchers'   => $this->languageSwitchers,
      'urls-and-seo'         => $this->urlsAndSeo,
      'translators'          => $this->translators,
      'ai-translation'       => $this->aiTranslation,
      'string-translation'   => $this->stringTranslation,
      'wcml'                 => $this->wcml,
      'multi-currency'       => $this->multicurrency,
    );
    return $map[ $section ] ?? null;
  }


  private function aliasLegacySubmenuParam(): void {
    $section = isset( $_GET['section'] ) && is_string( $_GET['section'] ) ? $_GET['section'] : '';
    if ( $section === '' || isset( $_GET['sm'] ) ) {
      return;
    }

    $smByCriteria = array(
      'custom-xml' => 'custom-xml-config',
    );
    if ( isset( $smByCriteria[ $section ] ) ) {
      $_GET['sm'] = $smByCriteria[ $section ];
    }
  }


  private function aliasLegacyPageOnEnqueue(): void {
    $section = isset( $_GET['section'] ) && is_string( $_GET['section'] ) ? $_GET['section'] : '';

    $languagesSections   = array( 'languages', 'language-switchers', 'urls-and-seo', 'compatibility' );
    $translatorsSections = array( 'translators' );

    if ( in_array( $section, $languagesSections, true ) ) {
      $this->scopedQueryAlias( array( 'page' => 'sitepress-multilingual-cms/menu/languages.php' ) );

      if ( in_array( $section, array( 'language-switchers', 'urls-and-seo' ), true ) ) {
        $legacy = 'sitepress-multilingual-cms/menu/languages.php';
        add_action(
          'admin_enqueue_scripts',
          function () use ( $legacy ) {
            self::fireLanguageSwitcherEnqueue( $legacy );
          },
          5
        );
      }
    }

    if ( in_array( $section, $translatorsSections, true ) ) {
      $this->scopedQueryAlias(
        array(
          'page' => 'tm/menu/main.php',
          'sm'   => 'translators',
        )
      );
    }
  }


  private function scopedQueryAlias( array $aliases ): void {
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


  private static function fireLanguageSwitcherEnqueue( string $legacyHook ): void {
    $ls = $GLOBALS['wpml_language_switcher'] ?? null;
    if ( ! $ls instanceof \WPML_Language_Switcher ) {
      return;
    }
    try {
      $reflection = new \ReflectionClass( $ls );
      $prop       = $reflection->getProperty( 'dependencies' );
      if ( PHP_VERSION_ID < 80100 ) {
        $prop->setAccessible( true );
      }
      $factory = $prop->getValue( $ls );
    } catch ( \ReflectionException $e ) {
      return;
    }
    if ( ! $factory instanceof \WPML_LS_Dependencies_Factory ) {
      return;
    }
    $factory->admin_ui()->admin_enqueue_scripts_action( $legacyHook );
  }




  private function preserveSectionInLinks( string $html, string $section ): string {
    $pattern = '#href="([^"]*\?page=tm(?:/|%2F)menu(?:/|%2F)settings)([^"]*)"#';

    return (string) preg_replace_callback(
      $pattern,
      function ( array $match ) use ( $section ) {
        $base = $match[1];
        $rest = $match[2];
        if ( strpos( $rest, 'section=' ) !== false ) {
          return $match[0];
        }
        $insert = '&section=' . rawurlencode( $section );
        return 'href="' . $base . $insert . $rest . '"';
      },
      $html
    );
  }


  private function renderBackLink(): void {
    $href = esc_url( admin_url( 'admin.php?page=tm/menu/settings' ) );
    echo '<p class="wpml-settings-back" style="margin:0 0 1em 0;">';
    echo '<a href="' . $href . '">';
    /* translators: Name of the WPML Settings screen: the admin menu item, the page heading, and the back-link that returns to it. */
    echo '&larr; ' . esc_html__( 'Settings', 'wpml' );
    echo '</a></p>';
  }


}
