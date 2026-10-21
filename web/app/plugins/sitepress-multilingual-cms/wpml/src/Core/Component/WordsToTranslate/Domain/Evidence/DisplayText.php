<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Evidence;

use WPML\Core\Component\WordsToTranslate\Domain\Calculator\PrepareContent\Rules\HTMLTrait;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\PrepareContent\Rules\ShortcodeInterface;

class DisplayText {

  use HTMLTrait;

  const BOUNDARY_MIN_WORDS = 24;

  const BOUNDARY_WORDS = 12;

  const ELLIPSIS = '…';

  const NUMBER_PATTERN = '/\b\d+\b/u';

  private $shortcode;


  public function __construct( ShortcodeInterface $shortcode ) {
    $this->shortcode = $shortcode;
  }


  public function tokens( string $content ) {
    return $this->splitTokens(
      $this->shortcode->removeShortcodes( $this->stripHtml( $content ) )
    );
  }


  public function boundaries( string $content ) {
    $tokens = $this->tokens( $content );

    if ( count( $tokens ) <= self::BOUNDARY_MIN_WORDS ) {
      return null;
    }

    return [
      'begins' => implode( ' ', array_slice( $tokens, 0, self::BOUNDARY_WORDS ) ) . ' ' . self::ELLIPSIS,
      'ends'   => self::ELLIPSIS . ' ' . implode( ' ', array_slice( $tokens, - self::BOUNDARY_WORDS ) ),
    ];
  }


  public function countShortcodeTokens( string $content ) {
    $html = $this->stripHtml( $content );

    $withShortcodes = count( $this->splitTokens( $html ) );
    $withoutShortcodes = count( $this->splitTokens( $this->shortcode->removeShortcodes( $html ) ) );

    return max( 0, $withShortcodes - $withoutShortcodes );
  }


  public function countStandaloneNumbers( string $content ) {
    return (int) preg_match_all(
      self::NUMBER_PATTERN,
      $this->shortcode->removeShortcodes( $this->stripHtml( $content ) )
    );
  }


  private function stripHtml( string $content ) {
    return $this->removeHTMLExceptTranslatableAttributes( $content );
  }


  private function splitTokens( string $text ) {
    $tokens = preg_split( '/\s+/', trim( $text ) );

    return $tokens === false ? [] : array_values( array_filter( $tokens, 'strlen' ) );
  }


}
