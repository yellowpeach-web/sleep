<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

class LegacySectionExtractor {

  private static $scriptTemplatePlaceholders = array();


  public static function dissolveHiddenCollision( string $html ): string {
    if ( $html === '' ) {
      return '';
    }
    $renamed = (string) preg_replace(
      '/\bclass\s*=\s*"((?:[^"]*\s)?)hidden(\s[^"]*)?"/',
      'class="$1wpml-legacy-hidden$2"',
      $html
    );
    $style = '<style>'
      . '.wpml-legacy-hidden{display:none}'
      . '@layer utilities{'
      . '.wpml-legacy-hidden.block,'
      . '.wpml-legacy-hidden.inline,'
      . '.wpml-legacy-hidden.inline-block,'
      . '.wpml-legacy-hidden.flex,'
      . '.wpml-legacy-hidden.inline-flex,'
      . '.wpml-legacy-hidden.grid,'
      . '.wpml-legacy-hidden.inline-grid,'
      . '.wpml-legacy-hidden.table'
      . '{display:none!important}'
      . '}'
      . '</style>';
    return $style . $renamed;
  }


  public static function promoteSectionHeadingsToH2( string $html ): string {
    $rewritten = preg_replace(
      '#(<div class="wpml-section-header">\s*)<h3(\s[^>]*)?>(.*?)</h3>#s',
      '$1<h2$2>$3</h2>',
      $html
    );

    return is_string( $rewritten ) ? $rewritten : $html;
  }


  public static function extractSectionById( string $html, string $sectionId ): string {
    if ( $html === '' ) {
      return '';
    }

    $dom = self::loadHtmlIntoDom( $html );
    if ( $dom === null ) {
      return '';
    }

    $node = self::findElementById( $dom, $sectionId );
    if ( ! $node instanceof \DOMElement ) {
      return '';
    }

    return self::restoreScriptTemplates( $dom->saveHTML( $node ) ?: '' );
  }


  public static function extractMultipleSectionsByIds(
    string $html,
    array $sectionIds,
    array $excludedElementIds = array()
  ): string {
    if ( $html === '' || empty( $sectionIds ) ) {
      return '';
    }

    $dom = self::loadHtmlIntoDom( $html );
    if ( $dom === null ) {
      return '';
    }

    foreach ( $excludedElementIds as $excludedId ) {
      $excluded = self::findElementById( $dom, $excludedId );
      if ( $excluded instanceof \DOMElement && $excluded->parentNode ) {
        $excluded->parentNode->removeChild( $excluded );
      }
    }

    $out = '';
    foreach ( $sectionIds as $id ) {
      $node = self::findElementById( $dom, $id );
      if ( $node instanceof \DOMElement ) {
        $out .= $dom->saveHTML( $node ) ?: '';
      }
    }
    return self::restoreScriptTemplates( $out );
  }


  public static function extractInnerSectionByHeading( string $html, string $parentId, string $headingText ): string {
    if ( $html === '' ) {
      return '';
    }

    $dom = self::loadHtmlIntoDom( $html );
    if ( $dom === null ) {
      return '';
    }

    $inner = self::findInnerSectionByHeading( $dom, $parentId, $headingText );
    if ( $inner === null ) {
      return '';
    }

    return self::restoreScriptTemplates( $dom->saveHTML( $inner ) ?: '' );
  }


  public static function extractSectionByIdWithoutInner(
    string $html,
    string $sectionId,
    string $headingText
  ): string {
    if ( $html === '' ) {
      return '';
    }

    $dom = self::loadHtmlIntoDom( $html );
    if ( $dom === null ) {
      return '';
    }

    $section = self::findElementById( $dom, $sectionId );
    if ( ! $section instanceof \DOMElement ) {
      return '';
    }

    $inner = self::findInnerSectionByHeading( $dom, $sectionId, $headingText );
    if ( $inner instanceof \DOMElement && $inner->parentNode ) {
      $inner->parentNode->removeChild( $inner );
    }

    return self::restoreScriptTemplates( $dom->saveHTML( $section ) ?: '' );
  }


  private static function findInnerSectionByHeading(
    \DOMDocument $dom,
    string $parentId,
    string $headingText
  ): ?\DOMElement {
    $section = self::findElementById( $dom, $parentId );
    if ( ! $section instanceof \DOMElement ) {
      return null;
    }

    $xpath  = new \DOMXPath( $dom );
    $needle = trim( $headingText );
    $classMatch = "contains(concat(' ', normalize-space(@class), ' '), ' wpml-section-content-inner ')";
    $inners = $xpath->query( ".//div[$classMatch]", $section );
    if ( ! $inners ) {
      return null;
    }

    foreach ( $inners as $inner ) {
      $heading = $xpath->query( './/h4', $inner );
      if ( ! $heading || $heading->length === 0 ) {
        continue;
      }
      $headingNode = $heading->item( 0 );
      if ( $headingNode === null ) {
        continue;
      }
      if ( trim( $headingNode->textContent ) === $needle && $inner instanceof \DOMElement ) {
        return $inner;
      }
    }

    return null;
  }


  private static function findElementById( \DOMDocument $dom, string $id ): ?\DOMElement {
    $node = $dom->getElementById( $id );
    if ( $node instanceof \DOMElement ) {
      return $node;
    }
    $xpath = new \DOMXPath( $dom );
    $nodes = $xpath->query( "//*[@id=" . self::xpathLiteral( $id ) . "]" );
    if ( $nodes === false || $nodes->length === 0 ) {
      return null;
    }
    $found = $nodes->item( 0 );
    return $found instanceof \DOMElement ? $found : null;
  }


  private static function xpathLiteral( string $value ): string {
    if ( strpos( $value, "'" ) === false ) {
      return "'" . $value . "'";
    }
    if ( strpos( $value, '"' ) === false ) {
      return '"' . $value . '"';
    }
    $parts = explode( "'", $value );
    return "concat('" . implode( "',\"'\",'", $parts ) . "')";
  }


  private static function loadHtmlIntoDom( string $html ): ?\DOMDocument {
    $html     = self::protectScriptTemplates( $html );
    $previous = libxml_use_internal_errors( true );
    $dom      = new \DOMDocument();
    $loaded = $dom->loadHTML(
      '<?xml encoding="utf-8"?><div id="wpml-extract-root">' . $html . '</div>',
      LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $previous );

    return $loaded ? $dom : null;
  }


  private static function protectScriptTemplates( string $html ): string {
    self::$scriptTemplatePlaceholders = array();
    $replaced = preg_replace_callback(
      '#(<script\b[^>]*type=["\']text/html["\'][^>]*>)(.*?)(</script>)#is',
      static function ( $m ) {
        $sentinel                                      = '__WPML_LS_TPL_PROTECTED_'
          . count( self::$scriptTemplatePlaceholders ) . '__';
        self::$scriptTemplatePlaceholders[ $sentinel ] = $m[2];
        return $m[1] . $sentinel . $m[3];
      },
      $html
    );
    return $replaced === null ? $html : $replaced;
  }


  private static function restoreScriptTemplates( string $html ): string {
    if ( empty( self::$scriptTemplatePlaceholders ) ) {
      return $html;
    }
    return str_replace(
      array_keys( self::$scriptTemplatePlaceholders ),
      array_values( self::$scriptTemplatePlaceholders ),
      $html
    );
  }


  public static function captureLegacyPartial( string $absolutePath ): string {
    if ( ! is_readable( $absolutePath ) ) {
      return '';
    }
    global $sitepress, $sitepress_settings, $wpdb, $iclTranslationManagement, $wp_taxonomies, $wpml_language_switcher;

    ob_start();
    include $absolutePath;
    return (string) ob_get_clean();
  }


  public static function captureSettingsMcsContent(): string {
    if ( ! class_exists( \WPML_TM_Menus_Settings::class ) ) {
      return '';
    }

    global $sitepress, $iclTranslationManagement;

    $page = new \WPML_TM_Menus_Settings();
    $page->init();

    ob_start();
    $page->build_content_mcs();
    return (string) ob_get_clean();
  }


}
