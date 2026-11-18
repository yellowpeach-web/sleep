<?php

namespace WPML\Infrastructure\WordPress\Component\LanguagePanel\Save;

use Throwable;
use WPML\Core\Component\Post\Application\LanguagePanel\EditorSaveRequestInterface;
use WPML\Core\Component\Post\Application\LanguagePanel\TranslatableRestPostTypesQueryInterface;

class EditorSaveContext {

  const ACTION_BEFORE_RESPONSE = 'wpml_editor_save_before_response';

  const PRIORITY_FIRE = PHP_INT_MAX - 10;

  private static $failureLogged = false;

  private $editorSave;

  private $postTypes;

  private $isPostEditorReferer;

  private $armed = null;

  private $ran = [];


  public function __construct(
    EditorSaveRequestInterface $editorSave,
    TranslatableRestPostTypesQueryInterface $postTypes,
    ?callable $isPostEditorReferer = null
  ) {
    $this->editorSave          = $editorSave;
    $this->postTypes           = $postTypes;
    $this->isPostEditorReferer = $isPostEditorReferer ?: [ self::class, 'isPostEditorReferer' ];
  }


  public function register() {
    foreach ( $this->postTypes->names() as $postType ) {
      add_action( 'rest_after_insert_' . $postType, [ $this, 'arm' ], 10, 3 );
    }

    add_action( 'wp_after_insert_post', [ $this, 'onAfterInsertPost' ], self::PRIORITY_FIRE, 1 );
    add_filter( 'rest_request_after_callbacks', [ $this, 'conclude' ], 10, 3 );
  }


  public function arm( $post, $request, $creating ) {
    if ( ! $this->editorSave->isEditorSave() ) {
      return;
    }

    if ( ! is_object( $post ) || ! isset( $post->ID ) || ! is_numeric( $post->ID ) ) {
      return;
    }

    $postId  = (int) $post->ID;
    $routeId = $this->editorSave->savedPostId();

    if ( null !== $routeId ? $routeId !== $postId : true !== $creating ) {
      return;
    }

    if ( ! $this->editorSave->isSiteEditorSave() && true !== call_user_func( $this->isPostEditorReferer ) ) {
      return;
    }

    $this->armed = [ $postId, $request ];
  }


  public function onAfterInsertPost( $postId ) {
    if ( null === $this->armed || ! is_numeric( $postId ) || (int) $postId !== $this->armed[0] ) {
      return;
    }

    $target      = $this->armed[0];
    $this->armed = null;

    try {
      do_action( self::ACTION_BEFORE_RESPONSE, $target );

      $this->ran[ $target ] = true;
    } catch ( Throwable $e ) {
      if ( ! self::$failureLogged ) {
        self::$failureLogged = true;
        $this->log(
          sprintf(
            'WPML: the post-save pipeline could not run before the save response for post %d (the save itself succeeded; the remaining units run at shutdown): %s: %s',
            $target,
            get_class( $e ),
            $e->getMessage()
          )
        );
      }
    }
  }


  public function conclude( $response, ?array $handler = null, $request = null ) {
    if ( null !== $this->armed && ( null === $request || $this->armed[1] === $request ) ) {
      $this->armed = null;
    }

    return $response;
  }


  public function armedPostId(): ?int {
    return null === $this->armed ? null : $this->armed[0];
  }


  public function ranFor( int $postId ): bool {
    return isset( $this->ran[ $postId ] );
  }


  public static function isPostEditorReferer(): bool {
    $referer = wp_get_raw_referer();

    return is_string( $referer )
      && ( false !== strpos( $referer, 'wp-admin/post.php' ) || false !== strpos( $referer, 'wp-admin/post-new.php' ) );
  }


  protected function log( string $message ) {
    if ( function_exists( 'error_log' ) ) {
      error_log( $message );
    }
  }


  public static function resetFailureLogForTests(): void {
    self::$failureLogged = false;
  }


}
