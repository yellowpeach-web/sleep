<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML_TM_MCS_Custom_Field_Settings_Menu_Factory;

class CustomFieldsTranslationController implements PageRenderInterface {


  public function render() {
    wp_enqueue_script( 'wpml-tm-mcs' );
    wp_enqueue_script( 'wpml-tm-mcs-translate-link-targets' );
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      __( 'Custom Fields Translation', 'wpml' ),
      __( 'Decide how WPML handles each custom field (post-meta) when posts are translated.', 'wpml' )
    );

    $factory   = new WPML_TM_MCS_Custom_Field_Settings_Menu_Factory();
    $menu_item = $factory->create_post();
    $menu_item->init_data();

    echo LegacySectionExtractor::dissolveHiddenCollision( $menu_item->render() );
  }


}
