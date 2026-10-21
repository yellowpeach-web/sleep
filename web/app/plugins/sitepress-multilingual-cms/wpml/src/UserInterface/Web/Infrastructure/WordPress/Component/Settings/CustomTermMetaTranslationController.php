<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML_TM_MCS_Custom_Field_Settings_Menu_Factory;

class CustomTermMetaTranslationController implements PageRenderInterface {


  public function render() {
    wp_enqueue_script( 'wpml-tm-mcs' );
    wp_enqueue_script( 'wpml-tm-mcs-translate-link-targets' );
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      __( 'Custom Term Meta Translation', 'wpml' ),
      __( 'Decide how WPML handles each meta key attached to taxonomy terms (categories, tags and custom taxonomies).', 'wpml' )
    );

    $wpdb = $GLOBALS['wpdb'] ?? null;
    if ( ! $wpdb || empty( $wpdb->termmeta ) ) {
      /* translators: Message on WPML → Settings. A term is a category or a tag; term meta are the extra fields attached to it. */
      echo '<p>' . esc_html__( 'Term meta is not available on this site.', 'wpml' ) . '</p>';
      return;
    }

    $factory   = new WPML_TM_MCS_Custom_Field_Settings_Menu_Factory();
    $menu_item = $factory->create_term();
    $menu_item->init_data();

    echo LegacySectionExtractor::dissolveHiddenCollision( $menu_item->render() );
  }


}
