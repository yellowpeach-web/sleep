<?php

namespace WPML\Core\Component\Post\Application\LanguagePanel;

final class SiteEditorEntityTypes {

  const TEMPLATE      = 'wp_template';
  const TEMPLATE_PART = 'wp_template_part';
  const PATTERN       = 'wp_block';
  const NAVIGATION    = 'wp_navigation';
  const PAGE          = 'page';

  const ALL = [ self::TEMPLATE, self::TEMPLATE_PART, self::PATTERN, self::NAVIGATION, self::PAGE ];

  const TEMPLATE_TYPES = [ self::TEMPLATE, self::TEMPLATE_PART ];

  const SHARED_TYPES = [ self::PATTERN, self::NAVIGATION, self::PAGE ];

  const SCREEN = 'site-editor';


  public static function isTemplateType( string $postType ): bool {
    return in_array( $postType, self::TEMPLATE_TYPES, true );
  }


  public static function isSharedType( string $postType ): bool {
    return in_array( $postType, self::SHARED_TYPES, true );
  }


  public static function getsSiteEditorControls( string $postType, ?string $screen ): bool {
    if ( self::isTemplateType( $postType ) ) {
      return true;
    }

    return self::SCREEN === $screen && self::isSharedType( $postType );
  }


  /**
   * Whether WordPress offers a post editor for the type. A template or part has
   * none (`show_ui=false`, "Invalid post type." on post.php / post-new.php,
   * SE-B13); a navigation menu's post.php is a title-only form (core disables
   * the block and the content editor for `wp_navigation`) and its own editor
   * is the Site Editor (core's `_edit_link`), so a `post.php` / `post-new.php`
   * door is no door for it either (ruling Q-SE-6 (c), M8). A synced pattern's
   * `post.php` IS its editor.
   */
  public static function hasNoPostEditor( string $postType ): bool {
    return self::isTemplateType( $postType ) || self::NAVIGATION === $postType;
  }


  /**
   * Ruling Q-SE-6 (c), M8: the WordPress Editor is never offered for a
   * navigation menu - not as the "+" / pencil arm (the editor resolver sends a
   * menu to WPML's editors whatever the stored choice), not in the editor
   * choice control. Takes a post type (`wp_navigation`) or an element type
   * (`post_wp_navigation`).
   */
  public static function offersNativeEditor( string $type ): bool {
    if ( 0 === strpos( $type, 'post_' ) ) {
      $type = substr( $type, 5 );
    }

    return self::NAVIGATION !== $type;
  }


  public static function translatable( array $translatableRestTypes ): array {
    return array_values( array_intersect( self::ALL, $translatableRestTypes ) );
  }


  public static function pageEditorPath( int $postId, string $language ): string {
    return 'site-editor.php?p=' . rawurlencode( '/page/' . $postId ) . '&canvas=edit&lang=' . rawurlencode( $language );
  }


}
