<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\LanguageEditor;

class LanguageEditorEmbed {

  const ELEMENT = 'wc-language-editor';

  const SURFACE_SETTINGS = 'settings';

  const SURFACE_MODAL_HOST = 'translation-improvements';

  const BUNDLE = 'public/js/wc-language-editor.js';


  public static function isBundleShipped( ?string $publicDir = null ): bool {
    if ( $publicDir === null ) {
      if ( ! defined( 'WPML_PUBLIC_DIR' ) ) {
        return false;
      }
      $publicDir = dirname( WPML_PUBLIC_DIR );
    }

    return is_file( rtrim( $publicDir, '/' ) . '/' . self::BUNDLE );
  }


  public static function enqueue(): void {
    if ( ! class_exists( \WPML\LanguageEditor\PageData::class ) ) {
      return;
    }

    $base = defined( 'WPML_PUBLIC_DIR' ) ? WPML_PUBLIC_DIR : __FILE__;

    wp_enqueue_script(
      'wpml-node-modules',
      plugins_url( 'public/js/node-modules.js', $base ),
      array(),
      ICL_SITEPRESS_VERSION,
      true
    );
    wp_enqueue_script(
      'wpml-wc-language-editor',
      plugins_url( self::BUNDLE, $base ),
      array( 'wpml-node-modules', 'wp-i18n' ),
      ICL_SITEPRESS_VERSION,
      true
    );
    wp_set_script_translations(
      'wpml-wc-language-editor',
      'wpml',
      WPML_ROOT_DIR . '/languages/'
    );
    wp_enqueue_style(
      'wpml-language-editor-tailwind',
      plugins_url( 'public/css/tailwind.css', $base ),
      array(),
      ICL_SITEPRESS_VERSION
    );
    wp_enqueue_style(
      'wpml-wc-language-editor',
      plugins_url( 'public/css/wc-language-editor.css', $base ),
      array( 'wpml-language-editor-tailwind' ),
      ICL_SITEPRESS_VERSION
    );

    $endpoints = array();
    foreach ( \WPML\LanguageEditor\Endpoints::get() as $action => $class ) {
      $endpoints[ $action ] = array(
        'endpoint' => $class,
        'nonce'    => \WPML\LIB\WP\Nonce::create( $class ),
      );
    }
    $data = array_merge(
      array( 'endpoints' => $endpoints ),
      \WPML\LanguageEditor\PageData::bootstrap()
    );
    wp_add_inline_script(
      'wpml-wc-language-editor',
      'window.wpmlLanguageEditor = ' . (string) wp_json_encode( $data ) . ';',
      'before'
    );

    if (
      class_exists( \WPML\LanguageEditor\Save\Endpoints::class )
      && \WPML\LanguageEditor\PageData::structuralSaveEnabled()
    ) {
      $saveEndpoints = array();
      foreach ( \WPML\LanguageEditor\Save\Endpoints::get() as $action => $class ) {
        $saveEndpoints[ $action ] = array(
          'endpoint' => $class,
          'nonce'    => \WPML\LIB\WP\Nonce::create( $class ),
        );
      }
      $saveData = array(
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'endpoints' => $saveEndpoints,
      );
      $saveData = apply_filters( 'wpml_language_editor_save_bootstrap', $saveData );
      wp_add_inline_script(
        'wpml-wc-language-editor',
        'window.wpmlLanguageSave = ' . (string) wp_json_encode( $saveData ) . ';',
        'before'
      );
    }
  }


  public static function hostMarkup( string $surface, array $attributes = array() ): string {
    $extra = '';
    foreach ( $attributes as $name => $value ) {
      if ( ! is_string( $name ) || preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $name ) !== 1 ) {
        continue;
      }
      $extra .= ' ' . $name . '="' . esc_attr( $value ) . '"';
    }

    return '<' . self::ELEMENT . ' surface="' . esc_attr( $surface ) . '"' . $extra . '></' . self::ELEMENT . '>';
  }


  public static function renderHost( string $surface, array $attributes = array() ): void {
    echo '<' . esc_html( self::ELEMENT ) . ' surface="' . esc_attr( $surface ) . '"';
    foreach ( $attributes as $name => $value ) {
      if ( ! is_string( $name ) || preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $name ) !== 1 ) {
        continue;
      }
      echo ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
    }
    echo '></' . esc_html( self::ELEMENT ) . '>';
  }


  public static function renderModalHost(): void {
    if ( ! class_exists( \WPML\LanguageEditor\PageData::class ) || ! self::isBundleShipped() ) {
      return;
    }

    self::enqueue();

    echo '<div class="wpml-language-editor-host wpml-language-editor-host--modal-only">';
    self::renderHost(
      self::SURFACE_MODAL_HOST,
      array( 'default-code' => \WPML\LanguageEditor\PageData::defaultCode() )
    );
    echo '</div>';
  }


}
