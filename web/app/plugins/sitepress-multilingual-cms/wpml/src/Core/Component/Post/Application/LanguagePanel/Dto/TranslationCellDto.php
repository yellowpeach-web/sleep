<?php

namespace WPML\Core\Component\Post\Application\LanguagePanel\Dto;

final class TranslationCellDto {

  private $languageCode;

  private $displayName;

  private $hasTranslation;

  private $translationId;

  private $isDuplicate;

  private $status;

  private $verb;

  private $link;

  private $text;

  private $cssClass;

  private $data;

  private $duplicateEnabled;

  private $duplicateTitle;


  public function __construct(
    string $languageCode,
    string $displayName,
    bool $hasTranslation,
    ?int $translationId,
    bool $isDuplicate,
    ?int $status,
    ?string $verb,
    ?string $link,
    string $text,
    string $cssClass,
    array $data,
    bool $duplicateEnabled,
    string $duplicateTitle
  ) {
    $this->languageCode     = $languageCode;
    $this->displayName      = $displayName;
    $this->hasTranslation   = $hasTranslation;
    $this->translationId    = $translationId;
    $this->isDuplicate      = $isDuplicate;
    $this->status           = $status;
    $this->verb             = $verb;
    $this->link             = $link;
    $this->text             = $text;
    $this->cssClass         = $cssClass;
    $this->data             = $data;
    $this->duplicateEnabled = $duplicateEnabled;
    $this->duplicateTitle   = $duplicateTitle;
  }


  public function languageCode(): string {
    return $this->languageCode;
  }


  public function hasTranslation(): bool {
    return $this->hasTranslation;
  }


  public function toArray(): array {
    return [
      'language_code'   => $this->languageCode,
      'display_name'    => $this->displayName,
      'has_translation' => $this->hasTranslation,
      'translation_id'  => $this->translationId,
      'is_duplicate'    => $this->isDuplicate,
      'status'          => $this->status,
      'verb'            => $this->verb,
      'link'            => $this->link,
      'text'            => $this->text,
      'css_class'       => $this->cssClass,
      'data'            => $this->data,
      'duplicate'       => [
        'enabled' => $this->duplicateEnabled,
        'title'   => $this->duplicateTitle,
      ],
    ];
  }


}
