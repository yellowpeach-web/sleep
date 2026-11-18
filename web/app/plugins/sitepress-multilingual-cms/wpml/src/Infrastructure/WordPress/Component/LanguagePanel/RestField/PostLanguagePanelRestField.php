<?php

namespace WPML\Infrastructure\WordPress\Component\LanguagePanel\RestField;

use Throwable;
use WPML\Core\Component\Post\Application\LanguagePanel\EditorSaveRequestInterface;
use WPML\Core\Component\Post\Application\LanguagePanel\PanelRequestHints;
use WPML\Core\Component\Post\Application\LanguagePanel\PostLanguageStatusService;
use WPML\Core\Component\Post\Application\LanguagePanel\ReleaseNoticeReaderInterface;
use WPML\Core\Component\Post\Application\LanguagePanel\SiteEditorEntityTypes;
use WPML\Core\Component\Post\Application\LanguagePanel\TranslatableRestPostTypesQueryInterface;
use WPML\Infrastructure\WordPress\Component\LanguagePanel\Save\EditorSaveContext;

class PostLanguagePanelRestField {

  const FIELD = 'wpml';

  private static $failureLogged = false;

  private $service;

  private $editorSave;

  private $postTypes;

  private $context;

  private $releaseNotice;


  public function __construct(
    PostLanguageStatusService $service,
    EditorSaveRequestInterface $editorSave,
    TranslatableRestPostTypesQueryInterface $postTypes,
    EditorSaveContext $context,
    ReleaseNoticeReaderInterface $releaseNotice
  ) {
    $this->service       = $service;
    $this->editorSave    = $editorSave;
    $this->postTypes     = $postTypes;
    $this->context       = $context;
    $this->releaseNotice = $releaseNotice;
  }


  public function register() {
    foreach ( $this->postTypes->names() as $postType ) {
      register_rest_field(
        $postType,
        self::FIELD,
        [
          'get_callback' => [ $this, 'value' ],
          'schema'       => [
            'description' => __( 'The WPML Language panel data of the post, present on the editor\'s own save only.', 'wpml' ),
            'type'        => [ 'object', 'null' ],
            'context'     => [ 'edit' ],
            'readonly'    => true,
          ],
        ]
      );
    }
  }


  public function value( $post ) {
    if ( ! $this->editorSave->isEditorSave() ) {
      return null;
    }

    $postId = $this->rowOf( $post );
    if ( $postId <= 0 ) {
      return null;
    }

    $savedId = $this->editorSave->savedPostId();
    if ( null !== $savedId && $savedId !== $postId ) {
      return null;
    }

    try {
      $data = $this->editorSave->isSiteEditorSave()
        ? $this->service->get( $postId, PanelRequestHints::fromArray( [ 'screen' => SiteEditorEntityTypes::SCREEN ] ) )
        : $this->service->get( $postId );

      if ( $this->context->ranFor( $postId ) ) {
        $notice = $this->releaseNotice->consume( $postId );
        if ( null !== $notice ) {
          $data['release_notice'] = $notice;
        }
      }

      return $data;
    } catch ( Throwable $e ) {
      if ( ! self::$failureLogged ) {
        self::$failureLogged = true;
        $this->log(
          sprintf(
            'WPML: the wpml REST field could not be computed for post %d (the save itself succeeded): %s: %s',
            $postId,
            get_class( $e ),
            $e->getMessage()
          )
        );
      }

      return null;
    }
  }


  private function rowOf( $post ): int {
    if ( ! is_array( $post ) || ! isset( $post['id'] ) ) {
      return 0;
    }

    if ( is_numeric( $post['id'] ) ) {
      return (int) $post['id'];
    }

    if ( $this->editorSave->isSiteEditorSave() && isset( $post['wp_id'] ) && is_numeric( $post['wp_id'] ) ) {
      return (int) $post['wp_id'];
    }

    return 0;
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
