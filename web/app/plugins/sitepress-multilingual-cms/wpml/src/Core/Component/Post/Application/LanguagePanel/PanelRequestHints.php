<?php

namespace WPML\Core\Component\Post\Application\LanguagePanel;

final class PanelRequestHints {

  private $trid;

  private $lang;

  private $sourceLang;

  private $screen;


  private function __construct( ?int $trid, ?string $lang, ?string $sourceLang, ?string $screen = null ) {
    $this->trid       = $trid;
    $this->lang       = $lang;
    $this->sourceLang = $sourceLang;
    $this->screen     = $screen;
  }


  public static function fromArray( array $request ): self {
    return new self(
      self::positiveInt( $request['trid'] ?? null ),
      self::languageCode( $request['lang'] ?? null ),
      self::languageCode( $request['source_lang'] ?? null ),
      self::screenName( $request['screen'] ?? null )
    );
  }


  public static function none(): self {
    return new self( null, null, null );
  }


  public function screen(): ?string {
    return $this->screen;
  }


  public function trid(): ?int {
    return $this->trid;
  }


  public function lang(): ?string {
    return $this->lang;
  }


  public function sourceLang(): ?string {
    return $this->sourceLang;
  }


  private static function positiveInt( $value ): ?int {
    if ( is_int( $value ) && $value > 0 ) {
      return $value;
    }
    if ( is_string( $value ) && ctype_digit( $value ) && (int) $value > 0 ) {
      return (int) $value;
    }

    return null;
  }


  private static function languageCode( $value ): ?string {
    if ( ! is_string( $value ) || '' === $value ) {
      return null;
    }

    return preg_match( '/^[a-zA-Z0-9-]{2,20}$/', $value ) ? $value : null;
  }


  private static function screenName( $value ): ?string {
    return SiteEditorEntityTypes::SCREEN === $value ? SiteEditorEntityTypes::SCREEN : null;
  }


}
