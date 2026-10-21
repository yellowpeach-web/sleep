<?php

namespace WPML\Core\Component\Translation\Application\Service\Dto;

use WPML\PHP\ConstructableFromArrayInterface;
use WPML\PHP\Exception\Exception;
use WPML\PHP\Exception\InvalidArgumentException;

class SendToTranslationDto implements ConstructableFromArrayInterface {

  private $batchName;

  private $sourceLanguageCode;

  private $targetLanguageMethods;

  private $posts = [];

  private $packages = [];

  private $strings = [];

  private $taxonomies = [];

  private $taxonomyTargets = [];

  private $operationId;

  private $extraInformation;

  private $isRetry = false;

  private $isSendAnyway = false;


  public function __construct(
    string $batchName,
    string $sourceLanguageCode,
    array $targetLanguageMethods,
    array $posts,
    array $packages,
    array $strings,
    ?SendToTranslationExtraInformationDto $extraInformation = null,
    array $taxonomies = [],
    array $taxonomyTargets = [],
    ?string $operationId = null,
    bool $isRetry = false,
    bool $isSendAnyway = false
  ) {
    $this->batchName             = $batchName;
    $this->sourceLanguageCode    = $sourceLanguageCode;
    $this->targetLanguageMethods = $targetLanguageMethods;
    $this->posts                 = $posts;
    $this->packages              = $packages;
    $this->strings               = $strings;
    $this->extraInformation      = $extraInformation;
    $this->taxonomies            = $taxonomies;
    $this->taxonomyTargets       = $taxonomyTargets;
    $this->operationId           = $operationId;
    $this->isRetry               = $isRetry;
    $this->isSendAnyway          = $isSendAnyway;
  }


  public function getBatchName(): string {
    return $this->batchName;
  }


  public function getSourceLanguageCode(): string {
    return $this->sourceLanguageCode;
  }


  public function getTargetLanguageMethods(): array {
    return $this->targetLanguageMethods;
  }


  public function getExtraInformation() {
    return $this->extraInformation;
  }


  public function getPosts(): array {
    return $this->posts;
  }


  public function getPackages(): array {
    return $this->packages;
  }


  public function getStrings(): array {
    return $this->strings;
  }


  public function getTaxonomies(): array {
    return $this->taxonomies;
  }


  public function getTaxonomyTargets(): array {
    return $this->taxonomyTargets;
  }


  public function getOperationId(): ?string {
    return $this->operationId;
  }


  public function isRetry(): bool {
    return $this->isRetry;
  }


  public function isSendAnyway(): bool {
    return $this->isSendAnyway;
  }




  public static function fromArray( $array ): SendToTranslationDto {
    return self::fromArrayInternal( $array, false );
  }


  public static function fromResolvedArray( array $array ): SendToTranslationDto {
    return self::fromArrayInternal( $array, true );
  }


  private static function fromArrayInternal( $array, bool $allowResolvedTaxonomies ): SendToTranslationDto {
    if ( ! is_array( $array ) || ! isset( $array['targetLanguageMethods'] ) || ! is_array( $array['targetLanguageMethods'] ) ) {
      throw new InvalidArgumentException( esc_html__( 'Language pairs cannot be empty', 'wpml' ) );
    }

    $targetLanguageMethods = [];
    foreach ( $array['targetLanguageMethods'] as $targetLanguageMethod ) {
      if ( ! is_array( $targetLanguageMethod ) ) {
        throw new InvalidArgumentException( esc_html__( 'A target language method is not valid.', 'wpml' ) );
      }
      $targetLanguageMethods[] = TargetLanguageMethodDto::fromArray( $targetLanguageMethod );
    }

    if ( ! isset( $array['batchName'] ) || ! is_string( $array['batchName'] ) ) {
      /* translators: Error message. A batch is the group of items sent for translation together, and its name is what is missing. */
      throw new InvalidArgumentException( esc_html__( 'Batch name cannot be empty', 'wpml' ) );
    }

    if ( ! isset( $array['sourceLanguageCode'] ) || ! is_string( $array['sourceLanguageCode'] ) ) {
      throw new InvalidArgumentException( esc_html__( 'Source language code cannot be empty', 'wpml' ) );
    }

    if ( ! isset( $array['posts'] )
         && ! isset( $array['stringPackages'] )
         && ! isset( $array['strings'] )
         && ( ! $allowResolvedTaxonomies || ! isset( $array['taxonomies'] ) ) ) {
      throw new InvalidArgumentException(
        esc_html__(
          'Posts, packages and strings cannot be empty.
          At least one of those elements must be specified.',
          'wpml'
        )
      );
    }

    $extraInformation = is_array( $array['extraInformation'] ?? null ) ? $array['extraInformation'] : [];
    $posts             = self::validateElementIds( $array['posts'] ?? [] );
    $stringPackages    = self::validateElementIds( $array['stringPackages'] ?? [] );
    $strings           = self::validateElementIds( $array['strings'] ?? [] );
    $taxonomies        = $allowResolvedTaxonomies ? self::validateElementIds( $array['taxonomies'] ?? [] ) : [];
    $taxonomyTargets   = $allowResolvedTaxonomies
      ? self::validateTaxonomyTargets( $array['taxonomyTargets'] ?? [], $taxonomies )
      : [];

    $dto = new self(
      $array['batchName'],
      $array['sourceLanguageCode'],
      $targetLanguageMethods,
      $posts,
      $stringPackages,
      $strings,
      SendToTranslationExtraInformationDto::fromArray( $extraInformation ),
      $taxonomies,
      $taxonomyTargets,
      $allowResolvedTaxonomies && is_string( $array['operationId'] ?? null ) ? $array['operationId'] : null,
      true === filter_var( $array['isRetry'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ),
      true === filter_var( $array['sendAnyway'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE )
    );

    return $dto;
  }


  private static function validateTaxonomyTargets( $targets, array $allowedIds ): array {
    if ( ! is_array( $targets ) ) {
      throw new InvalidArgumentException( esc_html__( 'Taxonomy targets are not valid.', 'wpml' ) );
    }

    $validated = [];
    foreach ( $targets as $languageCode => $elementIds ) {
      if ( ! is_string( $languageCode ) || '' === $languageCode ) {
        throw new InvalidArgumentException( esc_html__( 'Taxonomy targets are not valid.', 'wpml' ) );
      }

      $ids = self::validateElementIds( $elementIds );
      if ( array_diff( $ids, $allowedIds ) ) {
        throw new InvalidArgumentException( esc_html__( 'Taxonomy targets are outside the resolved selection.', 'wpml' ) );
      }
      $validated[ $languageCode ] = $ids;
    }

    return $validated;
  }


  private static function validateElementIds( $elementIds ): array {
    if ( ! is_array( $elementIds ) ) {
      /* translators: Error message. An element is one piece of content (a page, a post, a category); its ID is the number WordPress gives it. */
      throw new InvalidArgumentException( esc_html__( 'Element IDs must be positive integers.', 'wpml' ) );
    }

    foreach ( $elementIds as $elementId ) {
      if ( ! is_int( $elementId ) || $elementId <= 0 ) {
        /* translators: Error message. An element is one piece of content (a page, a post, a category); its ID is the number WordPress gives it. */
        throw new InvalidArgumentException( esc_html__( 'Element IDs must be positive integers.', 'wpml' ) );
      }
    }

    return $elementIds;
  }



}
