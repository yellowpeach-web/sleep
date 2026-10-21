<?php

namespace WPML\Legacy\Component\Translation\Domain\Links;

use WPML\Core\Component\Translation\Domain\Links\ResolvedLinksCacheInterface;

class ResolvedLinksCache implements ResolvedLinksCacheInterface {


  public function clear() {
    if ( class_exists( '\WPML_Pro_Translation' ) ) {
      \WPML_Pro_Translation::reset_translated_links_cache();
    }
  }


}
