<?php

namespace WPML\Legacy\Component\Post\Application\LanguagePanel\Write;

use SitePress;
use WPML\Collect\Support\Collection;
use WPML\Core\Component\Post\Application\LanguagePanel\Write\PostLanguageWriteGatewayInterface;
use function WPML\Container\make;

class PostLanguageWriteGateway implements PostLanguageWriteGatewayInterface {

  private $sitepress;


  public function __construct( SitePress $sitepress ) {
    $this->sitepress = $sitepress;
  }


  public function getPost( int $postId ): ?array {
    $post = get_post( $postId );
    if ( ! $post instanceof \WP_Post ) {
      return null;
    }

    return [
      'ID'          => $post->ID,
      'post_type'   => $post->post_type,
      'post_status' => $post->post_status,
      'post_author' => (int) $post->post_author,
    ];
  }


  public function isActiveLanguage( string $code ): bool {
    return (bool) $this->sitepress->is_active_language( $code );
  }


  public function getElementTrid( int $postId, string $postType ): ?int {
    $trid = $this->sitepress->get_element_trid( $postId, 'post_' . $postType );

    return $trid ? (int) $trid : null;
  }


  public function getElementLanguageCode( int $postId ): ?string {
    $code = $this->sitepress->post_translations()->get_element_lang_code( $postId );

    return $code ? (string) $code : null;
  }


  public function getElementIdInLanguage( int $trid, string $language, string $postType ): ?int {
    $id = $this->sitepress->post_translations()->get_element_id( $language, $trid );

    return $id ? (int) $id : null;
  }


  public function getOriginalElementId( int $trid ): ?int {
    $id = SitePress::get_original_element_id_by_trid( $trid );

    return $id ? (int) $id : null;
  }


  public function countGroupMembers( int $trid, string $postType ): int {
    $translations = $this->sitepress->get_element_translations( $trid, 'post_' . $postType, false, true );

    return is_array( $translations ) ? count( $translations ) : 0;
  }


  public function switchLanguage( int $postId, string $postType, string $language ): bool {
    $switched = \WPML_Post_Edit_Ajax::switch_post_language( $postId, $language );
    if ( false === $switched ) {
      return false;
    }

    do_action( 'wpml_post_language_switched', $postId, $language, $this->getElementTrid( $postId, $postType ), $postType );

    return true;
  }


  public function setTranslationOf( int $postId, string $postType, ?int $trid, string $language, ?string $sourceLanguage ): void {
    $elementType = 'post_' . $postType;

    $this->sitepress->set_element_language_details( $postId, $elementType, $trid, $language, $sourceLanguage );

    if ( $trid ) {
      \SitePress::clear_source_language_by_trid_cache( (int) $trid );

      do_action(
        'wpml_translations_connected',
        [
          'post_id'       => $postId,
          'post_type'     => $postType,
          'new_trid'      => $trid,
          'element_type'  => $elementType,
          'set_as_source' => false,
        ]
      );
    }
  }


  public function makeDuplicates( int $postId, array $languages ): void {
    $mdata = [ 'iclpost' => [ $postId ], 'duplicate_to' => [] ];
    foreach ( $languages as $language ) {
      $mdata['duplicate_to'][ $language ] = 1;
    }

    $this->translationManagement()->make_duplicates( $mdata );
    do_action( 'wpml_new_duplicated_terms', $mdata['iclpost'], false );
  }


  public function resetDuplication( int $postId ): void {
    $this->translationManagement()->reset_duplicate_flag( $postId );
  }


  public function setDuplication( int $originalId, string $language ) {
    return $this->translationManagement()->set_duplicate( $originalId, $language );
  }


  public function setEditorMode( string $scope, string $editor, int $postId, bool $isSwitchingWpmlNative ): void {
    ( new \WPML\TM\PostEditScreen\Endpoints\SetEditorMode( $this->sitepress ) )->run(
      Collection::make(
        [
          'enabledEditor'         => $editor,
          'postId'                => $postId,
          'editorModeFor'         => $scope,
          'isSwitchingWpmlNative' => $isSwitchingWpmlNative,
        ]
      )
    );
  }


  public function setShowTranslations( ?bool $show ): bool {
    if ( null === $show ) {
      $show = ! wpml_get_setting( 'show_translations_flag', true );
    }

    icl_set_setting( 'show_translations_flag', (int) $show );
    icl_save_settings();

    return (bool) wpml_get_setting( 'show_translations_flag', true );
  }


  public function getRootPageId(): ?int {
    $urls = $this->sitepress->get_setting( 'urls' );
    $root = is_array( $urls ) && isset( $urls['root_page'] ) ? (int) $urls['root_page'] : 0;

    return $root > 0 ? $root : null;
  }


  public function assignRootPage( int $pageId ): void {
    $page = get_post( $pageId );
    if ( ! $page instanceof \WP_Post ) {
      return;
    }

    ( new \WPML\UrlHandling\RootPage\RootPageCommand() )->assign( $page );
  }


  public function resolveTranslationIntent( array $intent ): array {
    $resolver = make( \WPML\TM\Editor\TranslationActionResolver::class );

    $answer = \WPML\TM\Editor\TranslateRequestContext::within(
      function () use ( $resolver, $intent ) {
        return $resolver->resolve( $intent );
      }
    );

    return $answer;
  }


  public function enableAmsSubscription( int $userId ): void {
    $actions = ( new \WPML_TM_AMS_Synchronize_Actions_Factory() )->create();
    if ( $actions instanceof \WPML_TM_AMS_Synchronize_Actions ) {
      $actions->enable_subscription( $userId );
    }
  }


  private function translationManagement(): \TranslationManagement {
    return wpml_load_core_tm();
  }


}
