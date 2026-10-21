<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\ATE;

/**
 * The one gate the ATE event wrappers share (wpmldev-8391).
 *
 * Their hooks fire on every license: saving Post Types, changing the active
 * languages, finishing setup. Without tm.php (Blog license) there is nothing
 * to notify ATE about, and building a listener through the DIC pulls the ATE
 * graph, whose constructors call TM-only loader functions and fatal before
 * the listener can bail. So every wrapper asks this before its make().
 */
final class TranslationManagementLoaded {


  public static function forRequest() {
    return function_exists( 'wpml_tm_load_old_jobs_editor' );
  }


}
