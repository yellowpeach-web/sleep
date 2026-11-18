<?php

namespace WPML\Legacy\Component\Post\Application\LanguagePanel;

use WPML\Core\Component\Post\Application\LanguagePanel\EditorScreenQueryInterface;

class EditorScreen implements EditorScreenQueryInterface {


  public function isBlockEditorScreen(): bool {
    return \WPML_Block_Editor_Helper::is_block_editor_screen();
  }


  public function isSiteEditorScreen(): bool {
    return \WPML_Block_Editor_Helper::is_site_editor_screen();
  }


  public function canEditThemeOptions(): bool {
    return current_user_can( 'edit_theme_options' );
  }


  public function currentPost(): ?array {
    $post = get_post();
    if ( ! $post instanceof \WP_Post || $post->ID <= 0 ) {
      return null;
    }

    return [ 'id' => $post->ID, 'type' => $post->post_type ];
  }


  public function isSetupComplete(): bool {
    return (bool) wpml_get_setting( 'setup_complete', false );
  }


  public function isRestPostType( string $postType ): bool {
    $object = get_post_type_object( $postType );

    return $object instanceof \WP_Post_Type && ! empty( $object->show_in_rest );
  }


  public function canSeeContentSetup(): bool {
    return current_user_can( 'manage_options' ) && \WPML\TM\Menu\McSetup\CfMetaBoxOption::get();
  }


  public function restRoute( string $postType ): array {
    $object    = get_post_type_object( $postType );
    $namespace = 'wp/v2';
    $base      = $postType;
    if ( $object && is_string( $object->rest_namespace ) && '' !== $object->rest_namespace ) {
      $namespace = $object->rest_namespace;
    }
    if ( $object && is_string( $object->rest_base ) && '' !== $object->rest_base ) {
      $base = $object->rest_base;
    }

    return [ 'namespace' => $namespace, 'base' => $base ];
  }


  public function postTypeLabels( string $postType ): array {
    $object   = get_post_type_object( $postType );
    $singular = $object && isset( $object->labels->singular_name ) ? (string) $object->labels->singular_name : $postType;
    $plural   = $object && isset( $object->labels->name ) ? (string) $object->labels->name : $postType;

    return [ 'singular' => $singular, 'plural' => $plural ];
  }


  public function isAutomaticTranslations(): bool {
    return \WPML\Setup\Option::shouldTranslateEverything();
  }


  public function isAdminBarShowing(): bool {
    return function_exists( 'is_admin_bar_showing' ) && is_admin_bar_showing();
  }


  public function postHogTrackingMode(): string {
    return \WPML\PostHog\State\PostHogState::getTrackingMode();
  }


  public function ajaxUrl(): string {
    return admin_url( 'admin-ajax.php' );
  }


  public function nonce( string $action ): string {
    return wp_create_nonce( $action );
  }


  public function settingsUrl( string $query ): string {
    return admin_url( \WPML\UIPage::getSettings() . $query );
  }


  public function translationDashboardUrl(): string {
    return admin_url( \WPML\UIPage::getTMDashboard() );
  }


}
