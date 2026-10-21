<?php
namespace WPML\UserInterface\Web\Legacy\Component\Translation;

use WPML\PHP\Exception\RuntimeException;
use WPML\UserInterface\Web\Core\Component\Notices\WarningTranslationEdit\Application\TranslationEditorInterface;
use WP_Post;

use function WPML\Container\make;

class TranslationEditor implements TranslationEditorInterface {

  private $_statusDisplay;

  private $_elementFactory;


  private function statusDisplay() {
    if ( $this->_statusDisplay === null ) {
      $wpml_tm_status_display_filter = $GLOBALS['wpml_tm_status_display_filter'] ?? null;
      if (
        ! $wpml_tm_status_display_filter
        && function_exists( 'wpml_tm_load_status_display_filter' )
      ) {
        wpml_tm_load_status_display_filter();
        $wpml_tm_status_display_filter = $GLOBALS['wpml_tm_status_display_filter'] ?? null;
      }

      if ( ! $wpml_tm_status_display_filter ) {
        throw new RuntimeException( 'WPML Translation Management is not loaded' );
      }

      $this->_statusDisplay = $wpml_tm_status_display_filter;
    }

    return $this->_statusDisplay;
  }


  private function elementFactory() {
    if ( $this->_elementFactory === null ) {
      $this->_elementFactory = make( \WPML_Translation_Element_Factory::class );
    }

    return $this->_elementFactory;
  }


  public function usesNativeEditor( int $postId ): bool {
    try {
      return \WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( $postId );
    } catch ( \Throwable $e ) {
      return false;
    }
  }


  public function hasLocalJobInProgress( int $postId ): bool {
    try {
      $element = $this->trackedPostElement( $postId );
      if ( ! $element ) {
        return false;
      }

      return $this->statusDisplay()->has_local_job_in_progress(
        $element['trid'],
        $element['languageCode']
      );
    } catch ( \Throwable $e ) {
      return true;
    }
  }


  private function trackedPostElement( int $postId ) {
    $post = get_post( $postId );
    if ( ! $post instanceof WP_Post ) {
      return null;
    }

    $post_element = $this->elementFactory()->create( $postId, 'post' );

    if (
      ! is_object( $post_element )
      || ! method_exists( $post_element, 'get_id' )
      || ! method_exists( $post_element, 'get_source_element' )
      || ! method_exists( $post_element, 'get_language_code' )
      || ! method_exists( $post_element, 'get_trid' )
    ) {
      return null;
    }

    $languageCode = (string) $post_element->get_language_code();
    $trid         = (int) $post_element->get_trid();

    if ( '' === $languageCode || 0 === $trid ) {
      return null;
    }

    $post_id             = $post_element->get_id();
    $source_post_element = $post_element->get_source_element();
    if ( $source_post_element ) {
      $post_id = $source_post_element->get_id();
    }

    return [
      'sourcePostId' => (int) $post_id,
      'languageCode' => $languageCode,
      'trid'         => $trid,
    ];
  }


  public function getTranslationEditorLink( int $postId ): string {
    try {
      $element = $this->trackedPostElement( $postId );
      if ( ! $element ) {
        return '';
      }

      $url = $this->statusDisplay()->filter_status_link(
        '',
        $element['sourcePostId'],
        $element['languageCode'],
        $element['trid']
      );

      if ( ! $url || ! is_string( $url ) ) {
        return '';
      }

      $url = remove_query_arg( 'return_url', $url );
      $url = admin_url() . ltrim( $url, '/' );

      return $url;
    } catch ( \Throwable $e ) {
      return '';
    }
  }


}
