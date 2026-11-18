<?php

namespace WPML\Legacy\Component\Post\Application\LanguagePanel;

use SitePress;
use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\TM\Settings\PreferenceResolver;

class LegacyGateway {

  private $sitepress;

  private $postTranslation;

  private $langPairPermissions;

  private $postStatusDisplay;

  private $postStatusDisplayLanguages;

  private $orphanTranslations = [];

  private $groupMembers = [];

  private static $reviewStatusFiltersLoaded = false;


  public function __construct( SitePress $sitepress ) {
    $this->sitepress = $sitepress;
  }


  private function postTranslation(): \WPML_Post_Translation {
    if ( null === $this->postTranslation ) {
      $this->postTranslation = $this->sitepress->post_translations();
    }

    return $this->postTranslation;
  }


  public function getPost( int $postId ): ?array {
    $post = get_post( $postId );
    if ( ! $post instanceof \WP_Post ) {
      return null;
    }

    return [
      'ID'           => $post->ID,
      'post_type'    => $post->post_type,
      'post_status'  => $post->post_status,
      'post_author'  => (int) $post->post_author,
      'post_content' => $post->post_content,
      'post_title'   => $post->post_title,
    ];
  }


  public function prefetch( array $postIds ): void {
    global $wpdb;

    if ( ! $postIds ) {
      return;
    }

    if ( function_exists( '_prime_post_caches' ) ) {
      _prime_post_caches( $postIds, false, true );
    }

    $this->postTranslation()->prefetch_ids( $postIds );

    $trids = [];
    foreach ( $postIds as $postId ) {
      $trid = (int) $this->postTranslation()->get_element_trid( $postId );
      if ( $trid > 0 ) {
        $trids[ $trid ] = $trid;
      }
    }
    $trids = array_values( $trids );
    if ( ! $trids ) {
      return;
    }

    if ( function_exists( 'wpml_tm_load_element_translations' ) ) {
      wpml_tm_load_element_translations()->init_jobs( $trids );
    }

    if ( class_exists( \WPML_TM_ICL_Translations::class ) ) {
      \WPML_TM_ICL_Translations::prime_translations_cache( $wpdb, $trids );
    }

    if ( class_exists( \WPML_Translations::class ) ) {
      $types = [];
      foreach ( $postIds as $postId ) {
        $type = get_post_type( $postId );
        if ( $type ) {
          $types[ $type ] = true;
        }
      }
      foreach ( array_keys( $types ) as $type ) {
        ( new \WPML_Translations( $this->sitepress ) )->prime_cache_for_trids( $trids, 'post_' . $type );
      }
    }
  }


  public function isPostTypeTranslated( string $postType ): bool {
    return is_post_type_translated( $postType );
  }


  public function getActiveLanguages(): array {
    return (array) $this->sitepress->get_active_languages();
  }


  public function getDefaultLanguage(): string {
    return (string) $this->sitepress->get_default_language();
  }


  public function getCurrentLanguage(): string {
    return (string) $this->sitepress->get_current_language();
  }


  public function getLanguageDisplayName( string $code ): string {
    $details = $this->sitepress->get_language_details( $code );

    return is_array( $details ) && isset( $details['display_name'] ) ? (string) $details['display_name'] : $code;
  }


  public function isActiveLanguage( string $code ): bool {
    return (bool) $this->sitepress->is_active_language( $code );
  }


  public function resolveLanguageHint( string $requested ): ?string {
    $resolved = \WPML\Language\ActiveLanguagesReadModel::canonical( $requested );

    return array_key_exists( $resolved, \WPML\Language\ActiveLanguagesReadModel::rows() ) ? $resolved : null;
  }


  public function getElementTrid( int $postId ): ?int {
    $trid = $this->postTranslation()->get_element_trid( $postId );

    return $trid ? (int) $trid : null;
  }


  public function getElementLanguageCode( int $postId ): ?string {
    $code = $this->postTranslation()->get_element_lang_code( $postId );

    return $code ?: null;
  }


  public function getSourceLanguageCode( int $postId ): ?string {
    $code = $this->postTranslation()->get_source_lang_code( $postId );

    return $code ?: null;
  }


  public function getTranslations( int $trid ): array {
    return array_map( 'intval', $this->postTranslation()->get_element_translations( false, $trid ) );
  }


  public function getElementId( string $lang, int $trid ): ?int {
    $id = $this->postTranslation()->get_element_id( $lang, $trid );

    return $id ? (int) $id : null;
  }


  public function getOriginalElementId( int $trid ): ?int {
    $id = SitePress::get_original_element_id_by_trid( $trid );

    return $id ? (int) $id : null;
  }


  public function getDuplicates( int $postId ): array {
    return array_map( 'intval', (array) $this->sitepress->get_duplicates( $postId ) );
  }


  public function getDuplicateOf( int $postId ): ?int {
    if ( ! \WPML\TranslationPolicy\Duplicate\DuplicateReaders::isKeptInStep( $postId ) ) {
      return null;
    }
    $original = get_post_meta( $postId, '_icl_lang_duplicate_of', true );

    return is_numeric( $original ) && (int) $original > 0 ? (int) $original : null;
  }


  public function hasOrphanTranslations( string $postType, string $language ): bool {
    $key = $postType . '|' . $language;
    if ( ! array_key_exists( $key, $this->orphanTranslations ) ) {
      $this->orphanTranslations[ $key ] = (bool) $this->sitepress->has_orphan_translations( $postType, $language );
    }

    return $this->orphanTranslations[ $key ];
  }


  public function countGroupMembers( int $trid, string $postType ): int {
    $key = $trid . '|' . $postType;
    if ( ! array_key_exists( $key, $this->groupMembers ) ) {
      $this->groupMembers[ $key ] = (int) $this->sitepress->count_translation_group_members( $trid, $postType );
    }

    return $this->groupMembers[ $key ];
  }


  public function getPostTitle( int $postId ): string {
    return get_the_title( $postId );
  }


  public function getEditPostLink( int $postId ): string {
    return (string) get_edit_post_link( $postId, '' );
  }


  public function getPostTypeLabels( string $postType ): array {
    $object = get_post_type_object( $postType );
    if ( ! $object ) {
      return [ 'singular' => $postType, 'plural' => $postType ];
    }

    return [
      'singular' => (string) ( $object->labels->singular_name ?: $object->labels->name ),
      'plural'   => (string) $object->labels->name,
    ];
  }


  public function isAdministrator(): bool {
    return \WPML\LIB\WP\User::isAdministrator();
  }


  public function isLangPairAllowed( string $from, string $to, int $postId ): bool {
    if ( null === $this->langPairPermissions ) {
      $this->langPairPermissions = new \WPML\TranslationRoles\LangPairPermissions();
    }

    return $this->langPairPermissions->is_allowed( $from, $to, $postId );
  }


  public function filterTranslatableLanguages( array $codes ): array {
    return \WPML\LanguageEditor\TranslationPause::filterTranslatable( $codes );
  }


  public function isTranslationPaused( string $code ): bool {
    return \WPML\LanguageEditor\TranslationPause::isPaused( $code );
  }


  public function isDraftsHintApplicable(): bool {
    return ! \WPML\Setup\Option::getTranslateEverythingDrafts()
      && \WPML\Setup\Option::getTranslateEverything()
      && \WPML\Setup\Option::getHasTranslateEverythingBeenEverUsed();
  }


  public function isMediaTranslatable(): bool {
    return $this->sitepress->is_translated_post_type( 'attachment' )
      && ! \WPML\Media\Option::shouldHandleMediaAuto();
  }


  public function getMediaFlags( int $postId, ?int $originalPostId ): array {
    $media    = \WPML\Media\Option::shouldDuplicateMedia( $postId, false );
    $featured = \WPML\Media\Option::shouldDuplicateFeatured( $postId, false );

    if ( ! $originalPostId || $originalPostId === $postId ) {
      $defaults = \WPML\Media\Option::getNewContentSettings();
      $media    = $media ?? ( $defaults['duplicate_media'] ?? false );
      $featured = $featured ?? ( $defaults['duplicate_featured'] ?? false );
    } else {
      $media    = $media ?? \WPML\Media\Option::shouldDuplicateMedia( $originalPostId );
      $featured = $featured ?? \WPML\Media\Option::shouldDuplicateFeatured( $originalPostId );
    }

    return [ 'duplicate_media' => (bool) $media, 'duplicate_featured' => (bool) $featured ];
  }


  public function shouldShowMinorEdit( int $postId, string $postType ): bool {
    global $ICL_Pro_Translation;

    return $ICL_Pro_Translation instanceof \WPML_Pro_Translation
      && $ICL_Pro_Translation->should_show_minor_edit( $postId, $postType );
  }


  public function getEditorSettings( int $postId ): array {
    list( $useTmEditor, $isBlocked, $reason ) = \WPML_TM_Post_Edit_TM_Editor_Mode::get_editor_settings( null, $postId );
    list( $enabledEditor, $editorModeFor )    = \WPML_TM_Post_Edit_TM_Editor_Mode::get_translation_editor_with_scope( null, $postId );

    if ( ! $useTmEditor || $isBlocked ) {
      $enabledEditor = \WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_NATIVE;
    }

    $method = wpml_get_tm_sub_setting( 'doc_translation_method', ICL_TM_TMETHOD_ATE );
    $isAte  = ICL_TM_TMETHOD_ATE === ( is_scalar( $method ) ? (string) $method : '' );

    return [
      'use_tm_editor'    => $useTmEditor,
      'blocked'          => $isBlocked,
      'blocked_reason'   => $reason,
      'enabled_editor'   => $enabledEditor,
      'mode_for'         => $editorModeFor,
      'wpml_editor_name' => $isAte
        ? __( 'Advanced Translation Editor', 'wpml' )
        : __( 'Classic Translation Editor', 'wpml' ),
      'switch_keeps_translations' => \WPML\TranslationPolicy\Policy\Policy::editorSwitchKeepsTranslations(),
    ];
  }


  public function getRootPageId(): ?int {
    $urls = $this->sitepress->get_setting( 'urls' );
    $root = is_array( $urls ) && isset( $urls['root_page'] ) ? (int) $urls['root_page'] : 0;

    return $root > 0 ? $root : null;
  }


  public function currentUserCan( string $capability, ...$args ): bool {
    return current_user_can( $capability, ...$args );
  }


  public function isCfMetaBoxEnabled(): bool {
    return \WPML\TM\Menu\McSetup\CfMetaBoxOption::get();
  }


  public function getCustomFieldKeys( int $postId ): array {
    $keys = get_post_custom_keys( $postId );
    if ( ! is_array( $keys ) ) {
      return [];
    }

    $factory = $this->translationManagement()->settings_factory();
    $listed  = [];
    foreach ( $factory->filter_custom_field_keys( array_map( 'strval', $keys ) ) as $key ) {
      if ( ! $factory->post_meta_setting( $key )->excluded() ) {
        $listed[] = $key;
      }
    }

    return $listed;
  }


  public function getCustomFieldModes( array $keys ): array {
    return PreferenceResolver::modes( ElementType::POST, $keys );
  }


  public function resolveCustomFieldModes( array $keys ): array {
    return wpml_resolve_custom_field_preferences( $keys, 'post' );
  }


  private function translationManagement(): \TranslationManagement {
    return wpml_load_core_tm();
  }


  const INTERNAL_TAXONOMIES = [ 'nav_menu', 'link_category', 'post_format' ];


  public function getTaxonomiesOf( string $postType ): array {
    $taxonomies = [];
    foreach ( get_object_taxonomies( $postType, 'objects' ) as $name => $taxonomy ) {
      if ( in_array( $name, self::INTERNAL_TAXONOMIES, true ) ) {
        continue;
      }
      $taxonomies[ $name ] = [
        'name'         => $name,
        'label'        => (string) ( $taxonomy->labels->name ?? $name ),
        'translatable' => (bool) $this->sitepress->is_translated_taxonomy( $name ),
      ];
    }

    return $taxonomies;
  }


  public function getSettingsUrl( string $section ): string {
    return admin_url( \WPML\UIPage::getSettings() . '&section=' . $section );
  }


  public function getAdminBarLinks(
    int $postId,
    string $postType,
    int $trid,
    array $translations,
    string $language,
    array $activeLanguages,
    bool $isAutoDraft
  ): array {
    $links = [];
    foreach ( $activeLanguages as $code => $details ) {
      if ( isset( $translations[ $code ] ) ) {
        $query = [ 'post' => $translations[ $code ], 'action' => 'edit' ];
        $page  = 'post.php';
      } else {
        $query = [ 'post_type' => $postType, 'source_lang' => $language, 'trid' => $trid ];
        $page  = 'post-new.php';
      }
      $query['lang']      = $code;
      $query['admin_bar'] = 1;

      $links[ $code ] = [
        'url'     => admin_url( $page . '?' . http_build_query( $query ) ),
        'current' => $code === $language,
        'anchor'  => isset( $details['display_name'] ) && is_string( $details['display_name'] ) ? $details['display_name'] : $code,
      ];
    }

    if ( ! $isAutoDraft ) {
      $links['all'] = [
        'url'     => admin_url( 'post.php?' . http_build_query( [ 'post' => $postId, 'action' => 'edit', 'lang' => 'all' ] ) ),
        'current' => false,
        /* translators: First option in the language dropdown of the WPML admin: show the content of every language at once. */
        'anchor'  => __( 'All languages', 'wpml' ),
      ];
    }

    return $links;
  }


  public function statusData( int $postId, string $lang, array $activeLanguages ): array {
    if ( null === $this->postStatusDisplay || $this->postStatusDisplayLanguages !== $activeLanguages ) {
      $this->postStatusDisplay          = new \WPML_Post_Status_Display( $activeLanguages );
      $this->postStatusDisplayLanguages = $activeLanguages;
    }

    return $this->postStatusDisplay->get_status_data( $postId, $lang );
  }


  public function isTmLoaded(): bool {
    return \WPML\Plugins::isTMLoadedForRequest();
  }


  public function reviewStatus( int $trid, string $lang ): ?string {
    return wpml_tm_load_element_translations()->get_translation_review_status( $trid, $lang );
  }


  public function linksData( int $postId, string $lang, int $trid, string $iconCssClass ): ?array {
    $display = $this->tmStatusDisplay();

    return $display ? $display->get_links_data( $postId, $lang, $trid, $iconCssClass ) : null;
  }


  private function tmStatusDisplay(): ?\WPML_TM_Translation_Status_Display {
    $display = $GLOBALS['wpml_tm_status_display_filter'] ?? null;

    return $display instanceof \WPML_TM_Translation_Status_Display ? $display : null;
  }


  public function loadReviewStatusFilters(): void {
    if ( self::$reviewStatusFiltersLoaded || is_admin() || ! $this->isTmLoaded()
      || ! class_exists( \WPML\TM\ATE\Review\StatusIcons::class ) ) {
      return;
    }

    self::$reviewStatusFiltersLoaded = true;

    ( new \WPML\TM\ATE\Review\StatusIcons() )->add_hooks();
    if ( did_action( 'init' ) ) {
      \WPML\TM\ATE\Review\StatusIcons::addGetReviewTitleFilter();
    }
  }


  public static function resetReviewStatusFiltersForTests(): void {
    self::$reviewStatusFiltersLoaded = false;
  }


  public function beginEditorScreen( string $editPostUrl ): void {
    if ( class_exists( \WPML_TM_Translation_Status_Display::class ) ) {
      \WPML_TM_Translation_Status_Display::set_editor_screen_url( $editPostUrl );
    }
  }


  public function endEditorScreen(): void {
    if ( class_exists( \WPML_TM_Translation_Status_Display::class ) ) {
      \WPML_TM_Translation_Status_Display::set_editor_screen_url( null );
    }
  }


  public function suspendGhostCleanup(): void {
    if ( class_exists( \WPML\TM\ATE\Review\GhostTranslationCleanup::class ) ) {
      \WPML\TM\ATE\Review\GhostTranslationCleanup::suspend();
    }
  }


  public function resumeGhostCleanup(): void {
    if ( class_exists( \WPML\TM\ATE\Review\GhostTranslationCleanup::class ) ) {
      \WPML\TM\ATE\Review\GhostTranslationCleanup::resume();
    }
  }


}
