<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\Core\Port\Endpoint\EndpointInterface;


class RunPageIdLinkRepairController implements EndpointInterface {


  public function handle( $requestData = null ): array {
      $data          = is_array( $requestData ) ? $requestData : [];
      $dryRun        = ! array_key_exists( 'dryRun', $data ) || false !== filter_var( $data['dryRun'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
      $cursorPosts   = isset( $data['cursorPosts'] ) && is_numeric( $data['cursorPosts'] ) ? (int) $data['cursorPosts'] : 0;
      $cursorStrings = isset( $data['cursorStrings'] ) && is_numeric( $data['cursorStrings'] ) ? (int) $data['cursorStrings'] : 0;

      $runner = $this->runner();
    if ( $runner === null ) {
        return [ 'done' => true, 'error' => 'engine-missing' ];
    }

      return $runner->run( $dryRun, $cursorPosts, $cursorStrings );
  }


  protected function runner() {
    if ( ! class_exists( \WPML\Troubleshooting\PageIdLinkRepair\Runner::class ) ) {
        return null;
    }

      global $sitepress;

      return \WPML\Troubleshooting\PageIdLinkRepair\Runner::forSite( $sitepress );
  }


}
