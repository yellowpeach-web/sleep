<?php

namespace WPML\Legacy\Component\Post\Application\LanguagePanel;

use WPML\Core\Component\Post\Application\LanguagePanel\SiteEditorEntityTypes;

class SiteEditorSavePredicate {

  const MARKER = 'wpml_current_entity';

  private $isSiteEditorReferer;

  private $postTypeOf;


  public function __construct( ?callable $isSiteEditorReferer = null, ?callable $postTypeOf = null ) {
    $this->isSiteEditorReferer = $isSiteEditorReferer ?: [ self::class, 'isSiteEditorReferer' ];
    $this->postTypeOf          = $postTypeOf ?:   function ( int $postId ) {
      return get_post_type( $postId );
    };
  }


  public function __invoke( ?array $handler, $request ): bool {
    if ( ! is_object( $request ) || ! method_exists( $request, 'get_param' ) ) {
      return false;
    }

    if ( ! is_array( $handler ) || empty( $handler['callback'] ) || ! is_array( $handler['callback'] ) ) {
      return false;
    }

    $callback = $handler['callback'];
    if ( ! isset( $callback[0], $callback[1] ) || ! $this->isSiteEditorEntityWrite( $callback[0], (string) $callback[1], $request ) ) {
      return false;
    }

    $marker = $request->get_param( self::MARKER );
    if ( empty( $marker ) || '0' === (string) $marker ) {
      return false;
    }

    return true === call_user_func( $this->isSiteEditorReferer );
  }


  private function isSiteEditorEntityWrite( $controller, string $callback, $request ): bool {
    if ( $controller instanceof \WP_REST_Templates_Controller ) {
      return in_array( $callback, [ 'create_item', 'update_item' ], true );
    }

    if ( ! $controller instanceof \WP_REST_Posts_Controller || 'update_item' !== $callback ) {
      return false;
    }

    if ( ! is_object( $request ) || ! method_exists( $request, 'get_param' ) ) {
      return false;
    }

    $id = $request->get_param( 'id' );
    if ( ! is_numeric( $id ) || (int) $id <= 0 ) {
      return false;
    }

    $postType = call_user_func( $this->postTypeOf, (int) $id );

    return is_string( $postType ) && SiteEditorEntityTypes::isSharedType( $postType );
  }


  public static function isSiteEditorReferer(): bool {
    $referer = wp_get_raw_referer();

    return is_string( $referer ) && false !== strpos( $referer, 'wp-admin/site-editor.php' );
  }


  public static function templateId( $request ): ?string {
    if ( ! is_object( $request ) || ! method_exists( $request, 'get_param' ) ) {
      return null;
    }

    $id = $request->get_param( 'id' );

    return is_string( $id ) && false !== strpos( $id, '//' ) ? $id : null;
  }


  public static function templateType( $request ): string {
    $route = is_object( $request ) && method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';

    return false !== strpos( $route, '/template-parts/' ) ? 'wp_template_part' : 'wp_template';
  }


  public static function resolveWpId( string $id, string $type ): ?int {
    $template = get_block_template( $id, $type );

    if ( null === $template || empty( $template->wp_id ) ) {
      return null;
    }

    return $template->wp_id;
  }


}
