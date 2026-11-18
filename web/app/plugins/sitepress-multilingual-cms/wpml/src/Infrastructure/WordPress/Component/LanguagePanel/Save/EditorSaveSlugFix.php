<?php

namespace WPML\Infrastructure\WordPress\Component\LanguagePanel\Save;

use WPML\Core\Component\Post\Application\LanguagePanel\EditorSaveRequestInterface;
use WPML\Core\Component\Post\Application\LanguagePanel\TranslatableRestPostTypesQueryInterface;
use WPML\Infrastructure\WordPress\Component\UrlHandling\Application\Hook\SlugPercentEncodedFixAction;

class EditorSaveSlugFix {

  const PRIORITY = 20;

  private $editorSave;

  private $postTypes;

  private $slugFix;


  public function __construct(
    EditorSaveRequestInterface $editorSave,
    TranslatableRestPostTypesQueryInterface $postTypes,
    SlugPercentEncodedFixAction $slugFix
  ) {
    $this->editorSave = $editorSave;
    $this->postTypes  = $postTypes;
    $this->slugFix    = $slugFix;
  }


  public function register() {
    add_filter( 'wp_insert_post_data', [ $this, 'filterPostData' ], self::PRIORITY, 4 );
  }


  public function filterPostData( $data, $postarr = [], $unsanitizedPostarr = [], $update = false ) {
    if ( ! is_array( $data ) || ! isset( $data['post_name'] ) || ! is_string( $data['post_name'] ) ) {
      return $data;
    }

    $slug    = $data['post_name'];
    $postarr = is_array( $postarr ) ? $postarr : [];
    if ( strpos( $slug, '%' ) === false || ! $this->isTheEditorsSaveOf( $data, $postarr, (bool) $update ) ) {
      return $data;
    }

    $fixed = $this->slugFix->fixPercentInSlug( $slug );
    if ( '' === $fixed || $fixed === $slug ) {
      return $data;
    }

    $data['post_name'] = wp_unique_post_slug(
      $fixed,
      isset( $postarr['ID'] ) && is_numeric( $postarr['ID'] ) ? (int) $postarr['ID'] : 0,
      isset( $data['post_status'] ) && is_string( $data['post_status'] ) ? $data['post_status'] : '',
      is_string( $data['post_type'] ) ? $data['post_type'] : '',
      isset( $data['post_parent'] ) && is_numeric( $data['post_parent'] ) ? (int) $data['post_parent'] : 0
    );

    return $data;
  }


  private function isTheEditorsSaveOf( array $data, array $postarr, bool $update ): bool {
    if ( ! $this->editorSave->isEditorSave() ) {
      return false;
    }

    if ( $this->editorSave->isSiteEditorSave() ) {
      return false;
    }

    $postType = isset( $data['post_type'] ) && is_string( $data['post_type'] ) ? $data['post_type'] : '';
    if ( '' === $postType || ! in_array( $postType, $this->postTypes->names(), true ) ) {
      return false;
    }

    $routeId = $this->editorSave->savedPostId();
    if ( null !== $routeId ) {
      return isset( $postarr['ID'] ) && is_numeric( $postarr['ID'] ) && (int) $postarr['ID'] === $routeId;
    }

    return ! $update;
  }


}
