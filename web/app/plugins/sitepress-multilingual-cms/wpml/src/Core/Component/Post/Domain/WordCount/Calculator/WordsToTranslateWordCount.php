<?php

namespace WPML\Core\Component\Post\Domain\WordCount\Calculator;

use WPML\Core\Component\WordsToTranslate\Domain\Calculator\PrepareContent\Ideogram\PrepareContent as PrepareContentIdeogram;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\PrepareContent\Letter\PrepareContent as PrepareContentLetter;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\PrepareContent\PrepareContentAbstract;
use WPML\Core\Component\WordsToTranslate\Domain\Config;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;

class WordsToTranslateWordCount implements WordCountInterface {

  private $prepareLetter;

  private $prepareIdeogram;

  private $languages;


  public function __construct(
    PrepareContentLetter $prepareLetter,
    PrepareContentIdeogram $prepareIdeogram,
    LanguagesQueryInterface $languages
  ) {
    $this->prepareLetter   = $prepareLetter;
    $this->prepareIdeogram = $prepareIdeogram;
    $this->languages       = $languages;
  }


  public function words( string $content ): int {
    $language = $this->sourceLanguage();

    $prepare = $this->prepareLetter;
    $factor  = 1.0;

    if ( isset( Config::LANGS[ $language ][ Config::KEY_WORDS_PER_IDEOGRAM ] ) ) {
      $prepare = $this->prepareIdeogram;
      $factor  = Config::LANGS[ $language ][ Config::KEY_WORDS_PER_IDEOGRAM ];
    }

    $tokens = array_filter(
      $prepare->prepareForDiff( $content ),
      function ( $token ) {
        return $token !== '';
      }
    );

    return (int) round( count( $tokens ) * $factor );
  }


  private function sourceLanguage(): string {
    try {
      return $this->languages->getDefaultCode();
    } catch ( \Throwable $e ) {
      return '';
    }
  }


}
