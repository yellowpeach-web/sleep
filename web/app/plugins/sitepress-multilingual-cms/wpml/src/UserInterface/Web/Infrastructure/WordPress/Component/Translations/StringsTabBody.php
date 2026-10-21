<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class StringsTabBody implements PageRenderInterface {

  const PARTIAL = '/menu/string-translation.php';


  public function render() {
    if ( ! defined( 'WPML_ST_VERSION' ) || ! defined( 'WPML_ST_PATH' ) ) {
      $message = \wpml_bold_names(
        __( 'String Translation requires the WPML String Translation plugin.', 'wpml' )
      );
      echo '<p>' . $message . '</p>';
      return;
    }

    $partial = WPML_ST_PATH . self::PARTIAL;
    if ( ! is_readable( $partial ) ) {
      return;
    }

    global $sitepress, $WPML_String_Translation, $wpdb, $wp_query, $sitepress_settings;

    $hadPage      = isset( $_GET['page'] );
    $originalPage = $hadPage && is_string( $_GET['page'] ) ? $_GET['page'] : null;
    $_GET['page'] = 'wpml-string-translation/menu/string-translation.php';

    ob_start();
    include $partial;
    $partialHtml = (string) ob_get_clean();

    if ( $hadPage ) {
      $_GET['page'] = $originalPage;
    } else {
      unset( $_GET['page'] );
    }

    $partialHtml = self::rewriteTropLinks( $partialHtml );

    $partialHtml = self::stripRedundantHeading( $partialHtml );

    echo $partialHtml;
  }


  private static function stripRedundantHeading( string $html ): string {
    return (string) preg_replace(
      '#(<div\s+class="wrap[^"]*"[^>]*>\s*)<h2[^>]*>[^<]*</h2>\s*#',
      '$1',
      $html,
      1
    );
  }


  private static function rewriteTropLinks( string $html ): string {
    $targetPath = 'admin.php?page=wpml-admin-texts-translation';
    $rawSlug    = 'wpml-string-translation/menu/string-translation.php';
    $encSlug    = 'wpml-string-translation%2Fmenu%2Fstring-translation.php';
    $pattern    = '#admin\.php\?page=(?:' . preg_quote( $rawSlug, '#' )
      . '|' . preg_quote( $encSlug, '#' ) . ')&(?:amp;)?trop=1#';

    return (string) preg_replace( $pattern, $targetPath, $html );
  }


}
