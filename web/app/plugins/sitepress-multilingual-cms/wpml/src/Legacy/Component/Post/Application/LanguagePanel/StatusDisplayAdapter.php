<?php

namespace WPML\Legacy\Component\Post\Application\LanguagePanel;

class StatusDisplayAdapter {

  const HOOK_PRE_STATUS_ICON = 'wpml_pre_status_icon_display';

  private $gateway;


  public function __construct( LegacyGateway $gateway ) {
    $this->gateway = $gateway;
  }


  public function cell( int $postId, string $lang, array $activeLanguages ): array {
    list( $text, $link, $trid, $cssClass, $status ) = $this->gateway->statusData( $postId, $lang, $activeLanguages );

    $trid   = $trid ? (int) $trid : null;
    $status = null === $status ? null : (int) $status;

    // Blog license: the TM loader does not exist, there is no review status.
    $reviewStatus = $this->gateway->isTmLoaded() && $trid ? $this->gateway->reviewStatus( $trid, $lang ) : null;

    $this->gateway->loadReviewStatusFilters();

    if ( ! did_action( self::HOOK_PRE_STATUS_ICON ) ) {
      do_action( self::HOOK_PRE_STATUS_ICON );
    }

    $link = apply_filters( 'wpml_link_to_translation', $link, $postId, $lang, $trid, $cssClass, $status, $reviewStatus );

    $text = apply_filters( 'wpml_text_to_translation', $text, $postId, $lang, $trid, $cssClass, $status, $reviewStatus );

    $cssClass = apply_filters( 'wpml_css_class_to_translation', $cssClass, $postId, $lang, $trid, $status, $reviewStatus );

    $cssClass = self::str( $cssClass );
    $link     = self::str( $link );

    if ( '' === $link || false !== strpos( $cssClass, 'disabled' ) ) {
      $link = null;
    }

    $data = $trid ? $this->gateway->linksData( $postId, $lang, $trid, $cssClass ) : null;
    $data = is_array( $data ) ? $data : [];

    return [
      'status'    => $status,
      'trid'      => $trid,
      'verb'      => isset( $data['verb'] ) ? (string) $data['verb'] : $this->fallbackVerb( $status ),
      'link'      => $link,
      'text'      => self::str( $text ),
      'css_class' => $cssClass,
      'data'      => $data,
    ];
  }


  private function fallbackVerb( ?int $status ): string {
    return ! $status ? 'translate' : 'edit';
  }


  private static function str( $value ): string {
    if ( is_string( $value ) ) {
      return $value;
    }

    return is_scalar( $value ) ? (string) $value : '';
  }


}
