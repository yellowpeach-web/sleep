<?php

namespace WPML\Infrastructure\WordPress\Component\LanguagePanel\Rest;

use WPML\Core\Component\Post\Application\LanguagePanel\TranslatableRestPostTypesQueryInterface;

class SaveFlagsArgs {

  private $postTypes;


  public function __construct( TranslatableRestPostTypesQueryInterface $postTypes ) {
    $this->postTypes = $postTypes;
  }


  public function register() {
    add_filter( 'rest_endpoints', [ $this, 'restEndpoints' ] );
  }


  public function restEndpoints( array $endpoints ) {
    $args   = self::args();
    $routes = array_fill_keys( $this->routes(), true );

    foreach ( $endpoints as $routeKey => &$route ) {
      if ( ! isset( $routes[ $routeKey ] ) || ! is_array( $route ) ) {
        continue;
      }
      foreach ( $route as $key => &$endpoint ) {
        if ( is_numeric( $key ) && is_array( $endpoint ) && SaveFlags::isPostSave( $endpoint ) ) {
          $endpoint['args'] = ( isset( $endpoint['args'] ) && is_array( $endpoint['args'] ) ? $endpoint['args'] : [] ) + $args;
        }
      }
      unset( $endpoint );
    }
    unset( $route );

    return $endpoints;
  }


  public function routes(): array {
    $routes = [];

    foreach ( $this->postTypes->names() as $name ) {
      $type = get_post_type_object( $name );
      if ( ! $type ) {
        continue;
      }

      $base      = ! empty( $type->rest_base ) && is_string( $type->rest_base ) ? $type->rest_base : $name;
      $namespace = ! empty( $type->rest_namespace ) && is_string( $type->rest_namespace ) ? $type->rest_namespace : 'wp/v2';

      $routes[] = '/' . $namespace . '/' . $base;
      $routes[] = '/' . $namespace . '/' . $base . '/(?P<id>[\d]+)';
    }

    return $routes;
  }


  public static function args(): array {
    return [
      SaveFlags::ARG_MINOR_EDIT         => [
        'type'        => 'boolean',
        'description' => __( 'WPML: a minor edit, which does not mark the translations as needing an update and sends no automatic translation.', 'wpml' ),
        'required'    => false,
      ],
      SaveFlags::ARG_DUPLICATE_MEDIA    => [
        'type'        => 'boolean',
        'description' => __( 'WPML: use the same media attachments in the translations (read when wpml_has_media_options is sent, on a save from the post editor).', 'wpml' ),
        'required'    => false,
      ],
      SaveFlags::ARG_DUPLICATE_FEATURED => [
        'type'        => 'boolean',
        'description' => __( 'WPML: use the same featured image in the translations (read when wpml_has_media_options is sent, on a save from the post editor).', 'wpml' ),
        'required'    => false,
      ],
      SaveFlags::ARG_HAS_MEDIA_OPTIONS  => [
        'type'        => 'boolean',
        'description' => __( 'WPML: the save from the post editor carries a media-duplication choice; without it the stored choice is left alone.', 'wpml' ),
        'required'    => false,
      ],
      SaveFlags::ARG_META_BOX_SAVE_FOLLOWS => [
        'type'        => 'boolean',
        'description' => __( 'WPML: the editor will save the screen\'s meta boxes in a second request right after this one; automatic translation waits for that request.', 'wpml' ),
        'required'    => false,
      ],
    ];
  }


}
