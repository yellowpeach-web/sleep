<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class DeletingContentController implements PageRenderInterface {

  const MOUNT_ID = 'wpml-deleting-content-container';


  public function render() {
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      /* translators: Name of the Deleting content section of WPML → Settings: its entry in the settings search list, its sub-entry and its heading. */
      __( 'Deleting content', 'wpml' ),
      __( 'What happens to the other languages when you delete a page, a product, an image, a category or a menu.', 'wpml' )
    );

    if ( ! class_exists( \WPML\ContentDeletion\Rows::class ) ) {
      echo '<p>' . esc_html__( 'Deleting-content settings are unavailable until WPML setup completes.', 'wpml' ) . '</p>';
      return;
    }

    $this->enqueue();

    echo '<div id="' . esc_attr( self::MOUNT_ID ) . '"></div>';
  }


  private function enqueue(): void {
    $base = defined( 'WPML_PUBLIC_DIR' ) ? WPML_PUBLIC_DIR : __FILE__;

    wp_enqueue_script(
      'wpml-node-modules',
      plugins_url( 'public/js/node-modules.js', $base ),
      array(),
      ICL_SITEPRESS_VERSION,
      true
    );
    wp_enqueue_script(
      'wpml-deleting-content',
      plugins_url( 'public/js/wpml-deleting-content.js', $base ),
      array( 'wpml-node-modules', 'wp-i18n' ),
      ICL_SITEPRESS_VERSION,
      true
    );
    wp_set_script_translations(
      'wpml-deleting-content',
      'wpml',
      WPML_ROOT_DIR . '/languages/'
    );
    wp_enqueue_style(
      'wpml-language-editor-tailwind',
      plugins_url( 'public/css/tailwind.css', $base ),
      array(),
      ICL_SITEPRESS_VERSION
    );

    $endpoints = array();
    foreach ( \WPML\ContentDeletion\Endpoints::get() as $action => $class ) {
      $endpoints[ $action ] = array(
        'endpoint' => $class,
        'nonce'    => \WPML\LIB\WP\Nonce::create( $class ),
      );
    }

    $data = array(
      'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
      'endpoints'  => $endpoints,
      'saveAction' => \WPML\ContentDeletion\Endpoints::SAVE,
      'rows'       => \WPML\ContentDeletion\Rows::withValues(),
    );

    wp_add_inline_script(
      'wpml-deleting-content',
      'window.wpmlDeletingContent = ' . (string) wp_json_encode( $data ) . ';',
      'before'
    );
  }


}
