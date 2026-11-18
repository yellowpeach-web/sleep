<?php

namespace WPML\Core\Component\Post\Application\LanguagePanel\Dto;

final class PostLanguagePanelDto {

  private $postId;

  private $postType;

  private $postStatus;

  private $translatable;

  private $language = null;

  private $missingLanguage = false;

  private $defaultLanguage = '';

  private $currentLanguage = '';

  private $trid = null;

  private $sourceLanguage = null;

  private $isOriginal = false;

  private $isTranslation = false;

  private $canTranslatePost = false;

  private $allowedTargetLanguages = [];

  private $languageOptions = [];

  private $duplicateOf = null;

  private $connectTranslations = null;

  private $translationOf = null;

  private $canTranslate = false;

  private $translations = [];

  private $showTranslationsFlag = true;

  private $draftsHint = false;

  private $copyFromOriginal = null;

  private $overwriteWithOriginal = null;

  private $mediaOptions = null;

  private $minorEdit = false;

  private $editorMode = null;

  private $rootPage = false;

  private $contentSetup = null;

  private $adminBarLinks = null;

  const CONTROLS = [
    'language',
    'translations',
    'translate',
    'editor_choice',
    'duplicate',
    'translation_of',
    'connect_translations',
    'minor_edit',
    'media_options',
    'copy_from_original',
    'root_page',
    'content_setup',
  ];

  const SITE_EDITOR_CONTROLS = [ 'language', 'translations', 'translate', 'editor_choice' ];

  private $controls;


  public function __construct( int $postId, string $postType, string $postStatus, bool $translatable ) {
    $this->postId       = $postId;
    $this->postType     = $postType;
    $this->postStatus   = $postStatus;
    $this->translatable = $translatable;
    $this->controls     = self::fullControls();
  }


  public static function fullControls(): array {
    return array_fill_keys( self::CONTROLS, true );
  }


  public static function siteEditorControls(): array {
    $controls = array_fill_keys( self::CONTROLS, false );
    foreach ( self::SITE_EDITOR_CONTROLS as $control ) {
      $controls[ $control ] = true;
    }

    return $controls;
  }


  public function setControls( array $controls ) {
    $this->controls = $controls;

    return $this;
  }


  public function postId(): int {
    return $this->postId;
  }


  public function setLanguage( ?string $language ) {
    $this->language = $language;

    return $this;
  }


  public function setMissingLanguage( bool $missingLanguage ) {
    $this->missingLanguage = $missingLanguage;

    return $this;
  }


  public function setLanguages( string $defaultLanguage, string $currentLanguage ) {
    $this->defaultLanguage = $defaultLanguage;
    $this->currentLanguage = $currentLanguage;

    return $this;
  }


  public function setGroup( ?int $trid, ?string $sourceLanguage, bool $isOriginal, bool $isTranslation ) {
    $this->trid           = $trid;
    $this->sourceLanguage = $sourceLanguage;
    $this->isOriginal     = $isOriginal;
    $this->isTranslation  = $isTranslation;

    return $this;
  }


  public function setLanguageSelector( bool $canTranslatePost, array $allowedTargetLanguages, array $languageOptions ) {
    $this->canTranslatePost       = $canTranslatePost;
    $this->allowedTargetLanguages = $allowedTargetLanguages;
    $this->languageOptions        = $languageOptions;

    return $this;
  }


  public function setDuplicateOf( ?array $duplicateOf ) {
    $this->duplicateOf = $duplicateOf;

    return $this;
  }


  public function setConnectTranslations( ?array $connectTranslations ) {
    $this->connectTranslations = $connectTranslations;

    return $this;
  }


  public function setTranslationOf( ?array $translationOf ) {
    $this->translationOf = $translationOf;

    return $this;
  }


  public function setTranslations( bool $canTranslate, array $translations, bool $showTranslationsFlag ) {
    $this->canTranslate         = $canTranslate;
    $this->translations         = $translations;
    $this->showTranslationsFlag = $showTranslationsFlag;

    return $this;
  }


  public function setDraftsHint( bool $draftsHint ) {
    $this->draftsHint = $draftsHint;

    return $this;
  }


  public function setOriginalContentActions( ?array $copyFromOriginal, ?array $overwriteWithOriginal ) {
    $this->copyFromOriginal      = $copyFromOriginal;
    $this->overwriteWithOriginal = $overwriteWithOriginal;

    return $this;
  }


  public function setMediaOptions( ?array $mediaOptions ) {
    $this->mediaOptions = $mediaOptions;

    return $this;
  }


  public function setMinorEdit( bool $minorEdit ) {
    $this->minorEdit = $minorEdit;

    return $this;
  }


  public function setEditorMode( ?array $editorMode ) {
    $this->editorMode = $editorMode;

    return $this;
  }


  public function setRootPage( bool $rootPage ) {
    $this->rootPage = $rootPage;

    return $this;
  }


  public function setContentSetup( ?array $contentSetup ) {
    $this->contentSetup = $contentSetup;

    return $this;
  }


  public function setAdminBarLinks( ?array $adminBarLinks ) {
    $this->adminBarLinks = $adminBarLinks;

    return $this;
  }


  public function toArray(): array {
    return [
      'post_id'                  => $this->postId,
      'post_type'                => $this->postType,
      'post_status'              => $this->postStatus,
      'translatable'             => $this->translatable,
      'language'                 => $this->language,
      'missing_language'         => $this->missingLanguage,
      'default_language'         => $this->defaultLanguage,
      'current_language'         => $this->currentLanguage,
      'trid'                     => $this->trid,
      'source_language'          => $this->sourceLanguage,
      'is_original'              => $this->isOriginal,
      'is_translation'           => $this->isTranslation,
      'can_translate_post'       => $this->canTranslatePost,
      'allowed_target_languages' => $this->allowedTargetLanguages,
      'language_options'         => $this->languageOptions,
      'duplicate_of'             => $this->duplicateOf,
      'connect_translations'     => $this->connectTranslations,
      'translation_of'           => $this->translationOf,
      'can_translate'            => $this->canTranslate,
      'translations'             => array_map(
        function ( TranslationCellDto $cell ) {
          return $cell->toArray();
        },
        $this->translations
      ),
      'show_translations_flag'   => $this->showTranslationsFlag,
      'drafts_hint'              => $this->draftsHint,
      'copy_from_original'       => $this->copyFromOriginal,
      'overwrite_with_original'  => $this->overwriteWithOriginal,
      'media_options'            => $this->mediaOptions,
      'minor_edit'               => $this->minorEdit,
      'editor_mode'              => $this->editorMode,
      'root_page'                => $this->rootPage,
      'content_setup'            => $this->contentSetup,
      'admin_bar_links'          => $this->adminBarLinks,
      'controls'                 => $this->controls,
      'notices'                  => [],
    ];
  }


}
