<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Calculator;

use WPML\Core\Component\WordsToTranslate\Domain\Calculator\PrepareContent\Ideogram\PrepareContent as PrepareContentIdeogram;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\PrepareContent\Letter\PrepareContent as PrepareContentLetter;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\PrepareContent\PrepareContentAbstract;
use WPML\Core\Component\WordsToTranslate\Domain\Config;
use WPML\Core\Component\WordsToTranslate\Domain\Evidence\FieldCount;
use WPML\Core\Component\WordsToTranslate\Domain\Item;
use WPML\Core\Component\WordsToTranslate\Domain\LastTranslation;

class WordsToTranslate {

  private static $freshWordCount = [];

  private static $freshContentCount = [];

  private static $freshFieldCounts = [];

  private static $sameItemDiffs = [ 'item' => '', 'diffs' => [] ];

  private $diff;

  private $count;

  private $prepareContentLetter;

  private $prepareContentIdeogram;


  public function __construct(
    Diff $diff,
    Count $count,
    PrepareContentLetter $prepareContentLetter,
    PrepareContentIdeogram $prepareContentIdeogram
  ) {
    $this->diff = $diff;
    $this->count = $count;
    $this->prepareContentLetter = $prepareContentLetter;
    $this->prepareContentIdeogram = $prepareContentIdeogram;
  }


  public function forLastTranslation( LastTranslation $lastTranslation, Item $original ) {
    $sourceLang = strtolower( $original->getSourceLang() );
    $prepare = $this->prepareContentLetter;

    $lastTranslationOriginalContent = $lastTranslation->getOriginalContent() ?? '';
    $isFreshTranslation = $lastTranslationOriginalContent === '';

    if ( $original->getType() === 'product_variation' ) {
      $lastTranslation->setWordsToTranslate( 0 );
      return;
    }

    $cacheKey = $original->getType() . ':' . $original->getId();

    if ( $isFreshTranslation && $this->restoreFromFreshCache( $lastTranslation, $original, $cacheKey ) ) {
      return;
    }

    $countFactor = 1;
    if ( isset( Config::LANGS[$sourceLang][Config::KEY_WORDS_PER_IDEOGRAM] ) ) {
      $prepare = $this->prepareContentIdeogram;
      $countFactor = Config::LANGS[$sourceLang][Config::KEY_WORDS_PER_IDEOGRAM];
    }

    $fieldContents = $original->getFieldContents();
    if ( $fieldContents !== null ) {
      $this->forLastTranslationPerField(
        $lastTranslation,
        $original,
        $fieldContents,
        $prepare,
        $countFactor,
        $isFreshTranslation,
        $cacheKey
      );
      return;
    }

    $sourceContentAsArray = $prepare->prepareForDiff( $original->getContent() ?? '' );

    $sourceContentCount = (int) round( count( $sourceContentAsArray ) * $countFactor );
    $original->setContentCount( $sourceContentCount );

    $diff = $this->diffForItem(
      $cacheKey,
      '',
      $prepare,
      $lastTranslationOriginalContent,
      $original->getContent() ?? '',
      $sourceContentAsArray
    );

    $count = (int) ( round( $this->count->wordsToTranslate( $diff ) * $countFactor ) );

    if ( $isFreshTranslation ) {
      self::$freshWordCount[ $cacheKey ] = $count;
      self::$freshContentCount[ $cacheKey ] = $sourceContentCount;
    }

    $lastTranslation->setWordsToTranslate( $count );

  }


  private function restoreFromFreshCache( LastTranslation $lastTranslation, Item $original, $cacheKey ) {
    if ( ! isset( self::$freshWordCount[ $cacheKey ] ) ) {
      return false;
    }

    $lastTranslation->setWordsToTranslate( self::$freshWordCount[ $cacheKey ] );

    if ( isset( self::$freshContentCount[ $cacheKey ] ) ) {
      $original->setContentCount( self::$freshContentCount[ $cacheKey ] );
    }
    if ( isset( self::$freshFieldCounts[ $cacheKey ] ) ) {
      $lastTranslation->setFieldCounts( self::$freshFieldCounts[ $cacheKey ] );
      $lastTranslation->setFieldDiffs( [] );
    }

    return true;
  }


  private function forLastTranslationPerField(
    LastTranslation $lastTranslation,
    Item $original,
    $fieldContents,
    PrepareContentAbstract $prepare,
    $countFactor,
    $isFreshTranslation,
    $cacheKey
  ) {
    $originalFieldContents = $isFreshTranslation
      ? []
      : ( $lastTranslation->getOriginalFieldContents() ?? [] );

    $fieldCounts = [];
    $fieldDiffs = [];

    $cumulativeChargedTokens = 0;
    $cumulativeTotalTokens = 0;
    $chargedWordsSoFar = 0;
    $totalWordsSoFar = 0;

    foreach ( $fieldContents as $fieldKey => $content ) {
      $currentTokens = $prepare->prepareForDiff( $content );

      $fieldDiff = $this->diffForItem(
        $cacheKey,
        $fieldKey,
        $prepare,
        $originalFieldContents[ $fieldKey ] ?? '',
        $content,
        $currentTokens
      );

      $chargedTokens = $this->count->wordsToTranslate( $fieldDiff );
      $chargedTokensBefore = $cumulativeChargedTokens;
      $cumulativeChargedTokens += $chargedTokens;
      $cumulativeTotalTokens += count( $currentTokens );

      $chargedWordsCumulative = (int) round( $cumulativeChargedTokens * $countFactor );
      $charged = $chargedWordsCumulative - $chargedWordsSoFar;
      $chargedWordsSoFar = $chargedWordsCumulative;

      $totalWordsCumulative = (int) round( $cumulativeTotalTokens * $countFactor );
      $total = $totalWordsCumulative - $totalWordsSoFar;
      $totalWordsSoFar = $totalWordsCumulative;

      $fieldCounts[] = new FieldCount(
        $fieldKey,
        $total,
        $charged,
        count( $currentTokens ),
        $chargedTokensBefore
      );

      if ( $chargedTokens > 0 && ( $originalFieldContents[ $fieldKey ] ?? '' ) !== '' ) {
        $fieldDiffs[ $fieldKey ] = $fieldDiff;
      }
    }

    $wordsToTranslate = $chargedWordsSoFar;

    $sourceContentCount = $totalWordsSoFar;
    $original->setContentCount( $sourceContentCount );

    if ( $isFreshTranslation ) {
      self::$freshWordCount[ $cacheKey ] = $wordsToTranslate;
      self::$freshContentCount[ $cacheKey ] = $sourceContentCount;
      self::$freshFieldCounts[ $cacheKey ] = $fieldCounts;
    }

    $lastTranslation->setWordsToTranslate( $wordsToTranslate );
    $lastTranslation->setFieldCounts( $fieldCounts );
    $lastTranslation->setFieldDiffs( $fieldDiffs );
  }


  private function diffForItem(
    $itemKey,
    $fieldKey,
    PrepareContentAbstract $prepare,
    $before,
    $current,
    $currentTokens
  ) {
    if ( self::$sameItemDiffs['item'] !== $itemKey ) {
      self::$sameItemDiffs = [ 'item' => $itemKey, 'diffs' => [] ];
    }

    $key = $fieldKey . '|' . get_class( $prepare ) . '|' . md5( $before ) . '|' . md5( $current );
    if ( ! isset( self::$sameItemDiffs['diffs'][ $key ] ) ) {
      self::$sameItemDiffs['diffs'][ $key ] = $this->diff->diffArrays(
        $prepare->prepareForDiff( $before ),
        $currentTokens
      );
    }

    return self::$sameItemDiffs['diffs'][ $key ];
  }


  public function forContent( $content, string $lang ) {
    $prepare = $this->prepareContentLetter;
    $countFactor = 1;

    if ( isset( Config::LANGS[$lang][Config::KEY_WORDS_PER_IDEOGRAM] ) ) {
      $prepare = $this->prepareContentIdeogram;
      $countFactor = Config::LANGS[$lang][Config::KEY_WORDS_PER_IDEOGRAM];
    }

    $contents = [];

    foreach ( $content as $value ) {
      $contents = array_merge( $contents, $prepare->prepareForDiff( $value ) );
    }

    return (int) ( round( count( $contents ) * $countFactor ) );
  }


  public function forFieldContents( array $fieldContents, string $lang ) {
    return $this->forContent( array_values( $fieldContents ), $lang );
  }


}
