<?php

namespace WPML\Core\Component\ReportContentStats\Domain;

class ContentStatsReport {

  private $siteKey;

  private $siteUrl;

  private $currentTranslationEditor;

  private $defaultLanguage;

  private $translationLanguages;

  private $siteUUID;

  private $siteSharedKey;

  private $contentStats;

  private $eventReason;

  private $translateEverythingEnabled;

  private $translationEngine;


  public function __construct(
    $siteKey,
    string $siteUrl,
    string $currentTranslationEditor,
    array $defaultLanguage,
    array $translationLanguages,
    $siteUUID,
    $siteSharedKey,
    array $contentStats,
    string $eventReason,
    bool $translateEverythingEnabled,
    string $translationEngine
  ) {
    $this->siteKey                    = $siteKey;
    $this->siteUrl                    = $siteUrl;
    $this->currentTranslationEditor   = $currentTranslationEditor;
    $this->defaultLanguage            = $defaultLanguage;
    $this->translationLanguages       = $translationLanguages;
    $this->siteUUID                   = $siteUUID;
    $this->siteSharedKey              = $siteSharedKey;
    $this->contentStats               = $contentStats;
    $this->eventReason                = $eventReason;
    $this->translateEverythingEnabled = $translateEverythingEnabled;
    $this->translationEngine          = $translationEngine;
  }


  public function getAsArray(): array {
    return [
      'siteKey'                  => $this->siteKey,
      'siteUrl'                  => $this->siteUrl,
      'currentTranslationEditor' => $this->currentTranslationEditor,
      'eventReason'              => $this->eventReason,
      'siteUUID'                 => $this->siteUUID,
      'siteSharedKey'            => $this->siteSharedKey,
      'defaultLanguage'          => $this->defaultLanguage,
      'translationLanguages'     => $this->translationLanguages,
      'translateEverything'      => [ 'enabled' => $this->translateEverythingEnabled ],
      'translationEngine'        => $this->translationEngine,
      'contentStats'             => $this->contentStats,
    ];
  }


  public function getSiteKey() {
    return $this->siteKey;
  }


  public function getCurrentTranslationEditor(): string {
    return $this->currentTranslationEditor;
  }


  public function getDefaultLanguage(): array {
    return $this->defaultLanguage;
  }


  public function getTranslationLanguages(): array {
    return $this->translationLanguages;
  }


  public function getSiteUUID() {
    return $this->siteUUID;
  }


  public function getSiteSharedKey() {
    return $this->siteSharedKey;
  }


  public function getContentStats(): array {
    return $this->contentStats;
  }


  public function getEventReason(): string {
    return $this->eventReason;
  }


  public function isTranslateEverythingEnabled(): bool {
    return $this->translateEverythingEnabled;
  }


  public function getTranslationEngine(): string {
    return $this->translationEngine;
  }


}
