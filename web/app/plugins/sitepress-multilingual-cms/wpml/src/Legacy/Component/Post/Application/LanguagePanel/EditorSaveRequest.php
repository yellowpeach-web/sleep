<?php

namespace WPML\Legacy\Component\Post\Application\LanguagePanel;

use WPML\Core\Component\Post\Application\LanguagePanel\EditorSaveRequestInterface;

class EditorSaveRequest implements EditorSaveRequestInterface {

  private $isEditorSavePredicate;

  private $isSiteEditorSavePredicate;

  private $resolveTemplateWpId;

  private $frames = [];


  public function __construct(
    ?callable $isEditorSavePredicate = null,
    ?callable $isSiteEditorSavePredicate = null,
    ?callable $resolveTemplateWpId = null
  ) {
    $this->isEditorSavePredicate = $isEditorSavePredicate ?: function ( ?array $handler ): bool {
      return true === \WPML_REST_Posts_Hooks::is_rest_saving_post_from_editor( $handler );
    };
    $this->isSiteEditorSavePredicate = $isSiteEditorSavePredicate ?: new SiteEditorSavePredicate();
    $this->resolveTemplateWpId       = $resolveTemplateWpId ?: [ SiteEditorSavePredicate::class, 'resolveWpId' ];
  }


  public function inspect( $response, ?array $handler, $request ) {
    $isEditorSave = false;
    $isSiteEditor = false;
    $savedPostId  = null;
    $templateId   = null;
    $templateType = 'wp_template';

    if ( ! $response instanceof \WP_Error ) {
      if ( true === call_user_func( $this->isEditorSavePredicate, $handler ) ) {
        $isEditorSave = true;

        $id          = is_object( $request ) && method_exists( $request, 'get_param' ) ? $request->get_param( 'id' ) : null;
        $savedPostId = is_numeric( $id ) && (int) $id > 0 ? (int) $id : null;
      } elseif ( true === call_user_func( $this->isSiteEditorSavePredicate, $handler, $request ) ) {
        $isEditorSave = true;
        $isSiteEditor = true;
        $templateId   = SiteEditorSavePredicate::templateId( $request );
        $templateType = SiteEditorSavePredicate::templateType( $request );

        if ( null === $templateId && is_object( $request ) && method_exists( $request, 'get_param' ) ) {
          $id          = $request->get_param( 'id' );
          $savedPostId = is_numeric( $id ) && (int) $id > 0 ? (int) $id : null;
        }
      }
    }

    $this->frames[] = [ $isEditorSave, $savedPostId, $request, $isSiteEditor, $templateId, $templateType ];

    return $response;
  }


  public function conclude( $response, ?array $handler = null, $request = null ) {
    if ( null === $request ) {
      array_pop( $this->frames );

      return $response;
    }

    foreach ( array_reverse( array_keys( $this->frames ) ) as $depth ) {
      if ( $this->frames[ $depth ][2] === $request ) {
        array_splice( $this->frames, $depth );
        break;
      }
    }

    return $response;
  }


  public function isEditorSave(): bool {
    $top = end( $this->frames );

    return false !== $top && $top[0];
  }


  public function isSiteEditorSave(): bool {
    $top = end( $this->frames );

    return false !== $top && $top[3];
  }


  public function savedPostId(): ?int {
    $depth = count( $this->frames ) - 1;
    if ( $depth < 0 ) {
      return null;
    }

    $frame = $this->frames[ $depth ];
    if ( null === $frame[1] && $frame[3] && null !== $frame[4] ) {
      $resolved = call_user_func( $this->resolveTemplateWpId, $frame[4], $frame[5] );
      if ( is_int( $resolved ) && $resolved > 0 ) {
        $this->frames[ $depth ][1] = $resolved;

        return $resolved;
      }

      return null;
    }

    return $frame[1];
  }


}
