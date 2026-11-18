<?php

namespace WPML\Legacy\Component\Post\Application\LanguagePanel;

use WPML\Core\Component\CustomFieldPreferences\Domain\StorableModes;
use WPML\Core\Component\Post\Application\LanguagePanel\Dto\PostLanguagePanelDto;
use WPML\Core\Component\Post\Application\LanguagePanel\Dto\TranslationCellDto;
use WPML\Core\Component\Post\Application\LanguagePanel\PanelRequestHints;
use WPML\Core\Component\Post\Application\LanguagePanel\Query\PostLanguagePanelQueryInterface;
use WPML\Core\Component\Post\Application\LanguagePanel\SiteEditorEntityTypes;
use WPML\PHP\Exception\InvalidItemIdException;

class PostLanguagePanelQuery implements PostLanguagePanelQueryInterface {

  private $gateway;

  private $status;

  private $active = null;

  private $default = '';

  private $current = '';


  public function __construct( LegacyGateway $gateway, StatusDisplayAdapter $status ) {
    $this->gateway = $gateway;
    $this->status  = $status;
  }


  public function get( int $postId, PanelRequestHints $hints ): PostLanguagePanelDto {
    $post = $this->gateway->getPost( $postId );
    if ( ! $post ) {
      throw new InvalidItemIdException( 'id: ' . esc_html( (string) $postId ) . ' is not a post.' );
    }

    $this->loadShared();

    return $this->build( $post, $hints );
  }


  public function getMany( array $postIds, PanelRequestHints $hints ): array {
    $ids = array_values(
      array_unique(
        array_filter(
          array_map( 'intval', $postIds ),
          function ( int $id ) {
            return $id > 0;
          }
        )
      )
    );

    if ( ! $ids ) {
      return [];
    }

    $this->loadShared();
    $this->gateway->prefetch( $ids );

    $result = [];
    foreach ( $ids as $id ) {
      $post = $this->gateway->getPost( $id );
      if ( $post ) {
        $result[ $id ] = $this->build( $post, $hints );
      }
    }

    return $result;
  }


  private function loadShared(): void {
    if ( null === $this->active ) {
      $this->active  = $this->gateway->getActiveLanguages();
      $this->default = $this->gateway->getDefaultLanguage();
      $this->current = $this->gateway->getCurrentLanguage();
    }
  }


  private function build( array $post, PanelRequestHints $hints ): PostLanguagePanelDto {
    $postId   = $post['ID'];
    $postType = $post['post_type'];
    $status   = $post['post_status'];
    $active   = (array) $this->active;

    $translatable = $this->gateway->isPostTypeTranslated( $postType );
    $dto          = ( new PostLanguagePanelDto( $postId, $postType, $status, $translatable ) )
      ->setLanguages( $this->default, $this->current );

    $siteEditorSet = SiteEditorEntityTypes::getsSiteEditorControls( $postType, $hints->screen() );
    $controls      = $siteEditorSet ? PostLanguagePanelDto::siteEditorControls() : PostLanguagePanelDto::fullControls();

    // D-29 (M4 review P2-2): without Translation Management (a Blog licence)
    // (Q-SE-6 (c)): the same for a navigation menu, whose post-new.php is a
    if ( SiteEditorEntityTypes::hasNoPostEditor( $postType ) && ! $this->gateway->isTmLoaded() ) {
      $controls['translate'] = false;
    }
    if ( ! \WPML\TranslationPolicy\Duplicate\DuplicateControls::offered() ) {
      $controls['duplicate'] = false;
    }
    $dto->setControls( $controls );

    $dto->setContentSetup( $this->contentSetup( $postId, $postType, $translatable ) );

    if ( ! $translatable ) {
      return $dto;
    }

    $isAutoDraft  = 'auto-draft' === $status;
    $hintLang     = $isAutoDraft && $hints->lang() ? $this->gateway->resolveLanguageHint( $hints->lang() ) : null;
    $hintSource   = $isAutoDraft ? $this->resolveSourceHint( $hints->sourceLang() ) : null;
    $recordedCode = $this->gateway->getElementLanguageCode( $postId );
    $recorded     = $isAutoDraft ? null : $recordedCode;
    $trid         = $isAutoDraft ? $hints->trid() : $this->gateway->getElementTrid( $postId );
    $missing      = ! $isAutoDraft && ! $trid;

    $selected = $recorded ?: $this->current;
    if ( $hintLang && isset( $active[ $hintLang ] ) ) {
      $selected = $hintLang;
    }

    $dto->setLanguage( $missing ? null : $selected )->setMissingLanguage( $missing );

    $translations   = $trid ? $this->gateway->getTranslations( $trid ) : [];
    $sourceLanguage = $hintSource;
    $isOriginal     = false;
    if ( ! $sourceLanguage && isset( $translations[ $selected ] ) ) {
      $selectedSource = $this->gateway->getSourceLanguageCode( $translations[ $selected ] );
      $isOriginal     = null === $selectedSource;
      if ( ! $isOriginal ) {
        $sourceLanguage = $selectedSource;
      }
    }
    $isTranslation = ! $missing
      && ! $isOriginal
      && ( $selected !== (string) $sourceLanguage || ( $hintLang && $sourceLanguage !== $hintLang ) )
      && 'all' !== $this->current;
    $dto->setGroup( $trid, $sourceLanguage ?: null, $isOriginal, $isTranslation );

    if ( $trid ) {
      $dto->setAdminBarLinks( $this->gateway->getAdminBarLinks( $postId, $postType, $trid, $translations, $selected, $active, $isAutoDraft ) );
    }

    $postSourceLanguage = $isAutoDraft ? null : $this->gateway->getSourceLanguageCode( $postId );

    $allowed = apply_filters(
      'wpml_allowed_target_langs',
      array_diff( array_keys( $active ), [ $recordedCode ] ),
      $postId,
      'post'
    );
    $allowed = array_values( array_map( [ self::class, 'str' ], self::arr( $allowed ) ) );

    $selectorLangs = self::languageRows( apply_filters( 'wpml_active_languages_access', $active, [ 'action' => 'edit' ] ) );
    $dto->setLanguageSelector(
      ! empty( $allowed ),
      $allowed,
      $this->languageOptions( $postId, $selectorLangs, $allowed, $translations, $recorded, $selected )
    );

    $duplicateOf = $this->gateway->getDuplicateOf( $postId );
    $dto->setDuplicateOf(
      $duplicateOf
        ? [ 'id' => $duplicateOf, 'title' => $this->gateway->getPostTitle( $duplicateOf ), 'edit_link' => $this->gateway->getEditPostLink( $duplicateOf ) ]
        : null
    );

    if ( ! $isAutoDraft && $trid && $this->gateway->hasOrphanTranslations( $postType, $this->current ) && 1 === $this->gateway->countGroupMembers( $trid, $postType ) ) {
      $languageName = apply_filters( 'wpml_display_single_language_name', null, [ 'language_code' => $selected, 'display_code' => $this->default ] );
      $dto->setConnectTranslations( [
        'trid'          => $trid,
        'post_type'     => $postType,
        'language'      => $this->current,
        'language_name' => self::str( $languageName ),
      ] );
    }

    $isEditAction = ! $isAutoDraft;
    if ( ! $isAutoDraft && ( $isTranslation || ! $translations ) ) {
      $dto->setTranslationOf( $this->translationOf( $postId, $postType, $trid, $sourceLanguage, $isOriginal, $selected, $isEditAction ) );
    }

    $canTranslate = (bool) $trid && ( $isEditAction || self::truthy( apply_filters( 'wpml_post_edit_can_translate', false, [ 'trid' => $trid, 'translations' => $translations ] ) ) );

    $showFlag = (bool) apply_filters( 'wpml_setting', true, 'show_translations_flag' );

    $cells = [];
    if ( $canTranslate && $trid ) {
      $this->gateway->beginEditorScreen( $this->gateway->getEditPostLink( $postId ) );
      $this->gateway->suspendGhostCleanup();
      try {
        $cells = $this->cells( $postId, $postType, $trid, $translations, $selected, $active, $this->pageOpensInSiteEditor( $postId, $postType, $hints ) );
      } finally {
        $this->gateway->resumeGhostCleanup();
        $this->gateway->endEditorScreen();
      }
    }
    $dto->setTranslations( $canTranslate, $cells, $showFlag );

    $hasUntranslatedCell = false;
    foreach ( $cells as $cell ) {
      if ( ! $cell->hasTranslation() ) {
        $hasUntranslatedCell = true;
        break;
      }
    }
    $dto->setDraftsHint( 'draft' === $status && $hasUntranslatedCell && $this->gateway->isDraftsHintApplicable() );

    list( $copy, $overwrite ) = $this->originalContentActions( $post, $trid, $hintLang, $hintSource, $missing ? null : $selected, $isAutoDraft );
    $dto->setOriginalContentActions( $copy, $overwrite );

    if ( $this->gateway->isMediaTranslatable() ) {
      $originalId = $trid ? $this->gateway->getOriginalElementId( $trid ) : null;
      $flags      = $this->gateway->getMediaFlags( $postId, $originalId );
      $dto->setMediaOptions( [
        'duplicate_media'    => $flags['duplicate_media'],
        'duplicate_featured' => $flags['duplicate_featured'],
        'is_original'        => ! $originalId || $originalId === $postId,
      ] );
    }

    $dto->setMinorEdit( ! $isAutoDraft && null === $postSourceLanguage && $this->gateway->shouldShowMinorEdit( $postId, $postType ) );

    // backend loader; nothing on a Blog licence), an existing post (not the
    if ( $this->gateway->isTmLoaded() && ! $isAutoDraft && $trid && $canTranslate && $cells && null === $postSourceLanguage ) {
      if ( self::truthy( apply_filters( 'wpml_tm_post_edit_tm_editor_selector_display', true, $postId ) ) ) {
        $dto->setEditorMode( $this->editorMode( $postId, $postType, $selected ) );
      }
    }

    $dto->setRootPage( $this->gateway->getRootPageId() === $postId );

    return $dto;
  }


  /**
   * The editor choice's data: the stored settings (H-28/H-29 inside), plus
   * - M8, ruling Q-SE-6 (c) - whether the WordPress Editor is OFFERED at all.
   * For a navigation menu it is not: the control shows WPML's two choices
   * only, and a stored `native` choice (the site's or the type's translation
   * editor set to the WordPress Editor, or the menu's own) reads as WPML's
   * editor - the one the editor resolver actually opens for a menu
   * (`WPML\TM\Editor\Editor`, the "+" and the pencil), so the pressed segment
   * says what happens. The scope (`mode_for`) is reported as stored; the
   * `blocked` flag and reason too.
   *
   * M8 review P2-1: the stored fact travels beside the effective editor -
   * `stored_native` is true when the stored mode for that scope IS the
   * WordPress Editor. The client reads a click on such a panel as a switch
   * away from the WordPress Editor (the dialog, its scope options, the
   * overwrite warning before the cutover, `is_switching_wpml_native`), never as a switch inside
   * WPML's editors that would write the stored scope - the site - at once.
   *
   * @return array<string, mixed>
   */
  private function editorMode( int $postId, string $postType, string $selected ): array {
    $mode = [ 'selector_display' => true, 'source_language' => $selected ] + $this->gateway->getEditorSettings( $postId );

    $mode['stored_native']  = 'native' === ( $mode['enabled_editor'] ?? null );
    $mode['native_offered'] = SiteEditorEntityTypes::offersNativeEditor( $postType );
    if ( ! $mode['native_offered'] && $mode['stored_native'] ) {
      $mode['enabled_editor'] = 'wpml';
    }

    return $mode;
  }


  private function resolveSourceHint( ?string $raw ): ?string {
    if ( ! $raw ) {
      return null;
    }
    if ( 'all' === $raw ) {
      return $this->default;
    }

    return $this->gateway->resolveLanguageHint( $raw );
  }


  private static function truthy( $value ): bool {
    return is_scalar( $value ) ? (bool) $value : ! empty( $value );
  }


  private static function arr( $value ): array {
    return is_array( $value ) ? $value : [];
  }


  private static function languageRows( $value ): array {
    $rows = [];
    foreach ( self::arr( $value ) as $code => $row ) {
      if ( is_string( $code ) && is_array( $row ) ) {
        $rows[ $code ] = $row;
      }
    }

    return $rows;
  }


  private static function intOrNull( $value ): ?int {
    return is_numeric( $value ) ? (int) $value : null;
  }


  private static function str( $value ): string {
    if ( is_string( $value ) ) {
      return $value;
    }

    return is_scalar( $value ) ? (string) $value : '';
  }


  private static function displayName( $language, string $code ): string {
    return is_array( $language ) && isset( $language['display_name'] ) && is_string( $language['display_name'] )
      ? $language['display_name']
      : $code;
  }


  private function languageOptions( int $postId, array $selectorLangs, array $allowed, array $translations, ?string $recorded, string $selected ): array {
    $options = [];

    if ( $recorded && $recorded === $selected && ! isset( $selectorLangs[ $recorded ] ) ) {
      $options[] = [
        'code'     => $recorded,
        /* translators: %s: language name, e.g. "French". */
        'label'    => sprintf( __( '%s (inactive)', 'wpml' ), $this->gateway->getLanguageDisplayName( $recorded ) ),
        'selected' => true,
      ];
    }

    foreach ( $selectorLangs as $code => $language ) {
      if ( ( $code !== $selected && ! in_array( $code, $allowed, true ) )
        || ( isset( $translations[ $code ] ) && $translations[ $code ] !== $postId )
      ) {
        continue;
      }
      $options[] = [
        'code'     => $code,
        'label'    => self::displayName( $language, $code ),
        'selected' => $recorded ? $code === $selected : $this->isSelectedLang( $code, $selected ),
      ];
    }

    return $options;
  }


  private function isSelectedLang( string $code, string $selected ): bool {
    return $code === $selected
      || ( ! $this->gateway->isActiveLanguage( $selected ) && $code === $this->default );
  }


  private function translationOf( int $postId, string $postType, ?int $trid, ?string $sourceLanguage, bool $isOriginal, string $selected, bool $isEditAction ): array {
    $options   = [];
    $elementId = null;

    if ( $trid ) {
      $source    = $sourceLanguage ?: ( $this->gateway->getSourceLanguageCode( $postId ) ?: $this->default );
      $elementId = $this->gateway->getElementId( $source, $trid );
    }

    /* translators: First option in the dropdown that picks the original post this one is a translation of: no post chosen. The dashes keep it apart from the post titles. */
    $options['none'] = [ 'value' => 'none', 'label' => __( '--None--', 'wpml' ), 'selected' => false ];

    if ( $elementId && $elementId !== $postId ) {
      $options[ $elementId ] = [ 'value' => $elementId, 'label' => $this->titleOrPlaceholder( $elementId ), 'selected' => true ];
    }

    $handleAsOriginal = $isOriginal || ! $sourceLanguage || $sourceLanguage === $selected;
    if ( $handleAsOriginal && self::truthy( apply_filters( 'wpml_language_is_active', null, $selected ) ) && $selected !== $this->default ) {
      $untranslated = apply_filters(
        'wpml_elements_without_translations',
        null,
        [ 'element_type' => 'post_' . $postType, 'target_language' => $selected, 'source_language' => $this->default ]
      );
      foreach ( self::arr( $untranslated ) as $id ) {
        $id = is_numeric( $id ) ? (int) $id : 0;
        if ( $id > 0 && ! isset( $options[ $id ] ) ) {
          $options[ $id ] = [ 'value' => $id, 'label' => $this->titleOrPlaceholder( $id ), 'selected' => false ];
        }
      }
    }

    return [
      'disabled'          => ! ( $isEditAction && $trid ),
      'source_element_id' => $elementId,
      'options'           => array_values( $options ),
    ];
  }


  private function titleOrPlaceholder( int $postId ): string {
    $title = trim( $this->gateway->getPostTitle( $postId ) );

    return '' === $title ? '{' . __( 'Post without a title', 'wpml' ) . '}' : $title;
  }


  private function cells( int $postId, string $postType, int $trid, array $translations, string $selected, array $active, bool $pageInSiteEditor = false ): array {
    $duplicates = $this->gateway->getDuplicates( $postId );
    $labels     = $this->gateway->getPostTypeLabels( $postType );
    $typeLabel  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $labels['singular'] ) : strtolower( $labels['singular'] );

    $tableLangs = self::languageRows( apply_filters( 'wpml_active_languages_access', $active, [ 'action' => 'edit', 'post_type' => $typeLabel, 'post_id' => $postId ] ) );
    $tableLangs = array_intersect_key(
      $tableLangs,
      array_flip( $this->gateway->filterTranslatableLanguages( array_map( 'strval', array_keys( $tableLangs ) ) ) )
    );

    // (Q-SE-6 (c)): a navigation menu's neither - its editor is the Site Editor.
    $noPostEditor = SiteEditorEntityTypes::hasNoPostEditor( $postType );

    $cells = [];
    foreach ( $active as $code => $language ) {
      if ( $code === $selected ) {
        continue;
      }

      $hasTranslation = isset( $translations[ $code ] );
      if ( ! $hasTranslation && ! isset( $tableLangs[ $code ] ) ) {
        continue;
      }

      $cell          = $this->status->cell( $postId, $code, $active );
      $translationId = $hasTranslation ? $translations[ $code ] : null;
      if ( $pageInSiteEditor ) {
        $link = $this->pageSiteEditorLink( $cell['link'], $translationId, $code, $postId, $selected );
      } else {
        $link = $noPostEditor ? $this->siteEditorLink( $cell['link'], $translationId, $postId ) : $cell['link'];
      }

      $duplicateEnabled = true;
      /* translators: Name of the checkbox that makes a copy of the post in another language, used as its tooltip and, with the language name, as its screen reader name. Verb, imperative. */
      $duplicateTitle = __( 'Create duplicate', 'wpml' );

      if ( ! $hasTranslation ) {
        $translationStatus = self::intOrNull( apply_filters( 'wpml_tm_translation_status', null, [ 'trid' => $trid, 'language_code' => $code ] ) );
        if ( $translationStatus && $translationStatus < ICL_TM_COMPLETE ) {
          $duplicateEnabled = false;
          $duplicateTitle   = ICL_TM_DUPLICATE === $translationStatus
            ? __( 'This post is already duplicated.', 'wpml' )
            : __( "Can't create a duplicate. A translation is in progress.", 'wpml' );
        }

        if ( ! $this->isLangPairAllowed( $selected, $code, $postId ) ) {
          if ( $duplicateEnabled ) {
            $duplicateTitle = __( "You don't have the rights to duplicate to this language.", 'wpml' );
          }
          $duplicateEnabled = false;
        }
      }

      $cells[] = new TranslationCellDto(
        $code,
        self::displayName( $language, $code ),
        $hasTranslation,
        $translationId,
        isset( $duplicates[ $code ] ),
        $cell['status'],
        $cell['verb'],
        $link,
        $cell['text'],
        $cell['css_class'],
        $cell['data'],
        $duplicateEnabled,
        $duplicateTitle
      );
    }

    return $cells;
  }


  /**
   * SE-S3 (wpmldev-8629 P2 M1): a template or template part has no post editor
   * (`show_ui=false`; WordPress answers "Invalid post type." to `post.php` and
   * `post-new.php` for it, SE-B13), so the native arm's cell links - the shape
   * `WPML_Post_Status_Display` builds before TM's filter, and keeps on a Blog
   * licence or where TM has no control - must never reach the panel. An
   * existing translation links where `get_edit_post_link()` points: the Site
   * Editor, with WPML's `&lang=` (H-33). Any other link - TM's queue, an
   * integration's own editor - passes through untouched.
   *
   * P2 M4 (SE-S5, replacing D-23.3's `link: null`): a missing translation's
   * cell is a REAL "+" intent. `IntentControls.js` fires the intent only for an
   * anchor WITH an href, and the href is the control's own door (middle-click,
   * no JS). Where TM did not replace the native `post-new.php` link (a Blog
   * licence, a "no control" state) that door is the ORIGINAL's own Site Editor
   * URL - the page the "+" is on, never WordPress's refused screen - while the
   * left click travels `POST /wpml/v1/posts/<id>/translate`, whose WordPress-
   * editor arm creates the copy and answers its Site Editor URL (Q-SE-2 (a)).
   *
   * P2 M8 (Q-SE-6 (c)): a navigation menu too. Its `post.php` is a title-only
   * form (core disables the editor for `wp_navigation`) and `get_edit_post_link()`
   * answers the Site Editor (core's `_edit_link`), so an existing translation's
   * cell links there and a missing one's "+" door is the original's Site
   * Editor URL; the left click's `/translate` never opens the WordPress editor
   * for a menu (`WPML\TM\Editor\Editor`).
   */
  private function siteEditorLink( ?string $link, ?int $translationId, int $postId ): ?string {
    if ( null === $link || ! $this->isPostScreenLink( $link ) ) {
      return $link;
    }

    return $this->gateway->getEditPostLink( $translationId ?: $postId );
  }


  private function pageOpensInSiteEditor( int $postId, string $postType, PanelRequestHints $hints ): bool {
    if ( SiteEditorEntityTypes::PAGE !== $postType || SiteEditorEntityTypes::SCREEN !== $hints->screen() ) {
      return false;
    }

    return 'native' === ( $this->gateway->getEditorSettings( $postId )['enabled_editor'] ?? null );
  }


  /**
   * D-36 G3 (M9), the cells of a page that opens in the Site Editor
   * ({@see self::pageOpensInSiteEditor()}): an EXISTING translation links to its
   * own Site Editor route in its language, whatever door the status display
   * built (TM's queue door and post.php both open the post editor on a
   * middle-click / no-JS GET); the pencil's left click travels the intent POST
   * with the hint and the editor tree answers the same URL. A MISSING
   * translation's "+" keeps TM's intent door (its left click is the POST that
   * creates the copy; the GET is the Translation Tasks transport, as develop),
   * and where TM did not replace the native `post-new.php` link (a Blog
   * licence, a "no control" state) the door is the Site Editor route of the
   * PAGE READ - the page the "+" is on, the original or a translation, in its
   * own language (the template rule) - so no cell links to a post screen. A
   * cell the status display left WITHOUT a link (a `-disabled` icon: not the
   * person's language pair, an original they may not edit, another
   * translator's job) stays without one, existing translation or not (D-37,
   * M9 c review P3-1): the client follows any href on a disabled icon. The
   * intent payload (`data`) is untouched.
   *
   * @param string|null $link          The status display's link.
   * @param int|null    $translationId The existing translation, or null.
   * @param string      $language      The cell's language.
   * @param int         $postId        The page read.
   * @param string      $ownLanguage   The page's language.
   */
  private function pageSiteEditorLink( ?string $link, ?int $translationId, string $language, int $postId, string $ownLanguage ): ?string {
    if ( null === $link ) {
      return null;
    }

    if ( $translationId ) {
      return admin_url( SiteEditorEntityTypes::pageEditorPath( $translationId, $language ) );
    }

    if ( ! $this->isPostScreenLink( $link ) ) {
      return $link;
    }

    return admin_url( SiteEditorEntityTypes::pageEditorPath( $postId, $ownLanguage ) );
  }


  private function isPostScreenLink( string $link ): bool {
    $path = wp_parse_url( $link, PHP_URL_PATH );

    return is_string( $path ) && 1 === preg_match( '#(?:^|/)post(?:-new)?\.php$#', $path );
  }


  private function isLangPairAllowed( string $from, string $to, int $postId ): bool {
    return $this->gateway->isAdministrator() || $this->gateway->isLangPairAllowed( $from, $to, $postId );
  }


  private function originalContentActions( array $post, ?int $trid, ?string $hintLang, ?string $hintSource, ?string $language, bool $isAutoDraft ): array {
    $source = $hintSource;
    $lang   = $hintLang ?: ( $language ?: $this->current );

    if ( ! $source && ! $isAutoDraft && $lang !== $this->default ) {
      $source = $this->gateway->getSourceLanguageCode( $post['ID'] );
    }

    if ( ! $trid || ! $source || $source === $lang ) {
      return [ null, null ];
    }

    $sourceName = $this->gateway->getLanguageDisplayName( $source );

    $copy = [
      'source_language'      => $source,
      'source_language_name' => $sourceName,
      'trid'                 => $trid,
      'disabled'             => '' !== trim( $post['post_content'] ),
    ];

    $overwrite = $this->gateway->isTranslationPaused( $lang )
      ? null
      : [
        'original_post_id'     => (int) $this->gateway->getElementId( $source, $trid ),
        'language'             => $lang,
        'source_language_name' => $sourceName,
      ];

    return [ $copy, $overwrite ];
  }


  private function contentSetup( int $postId, string $postType, bool $translatable ): ?array {
    if ( ! $this->gateway->currentUserCan( 'manage_options' ) || ! $this->gateway->isCfMetaBoxEnabled() ) {
      return null;
    }

    $labels = $this->gateway->getPostTypeLabels( $postType );
    $keys   = $this->gateway->getCustomFieldKeys( $postId );

    $modes = $keys ? $this->gateway->getCustomFieldModes( $keys ) : [];

    $nullKeys = array_values(
      array_filter(
        $keys,
        function ( $key ) use ( $modes ) {
          return ! isset( $modes[ $key ] );
        }
      )
    );
    if ( $nullKeys ) {
      foreach ( $this->gateway->resolveCustomFieldModes( $nullKeys ) as $key => $mode ) {
        $modes[ $key ] = $mode;
      }
    }

    $translatableFields = count(
      array_filter(
        $modes,
        function ( $mode ) {
          return StorableModes::TRANSLATE === $mode;
        }
      )
    );

    return [
      'post_type'                        => [
        'name'         => $postType,
        'label'        => $labels['singular'],
        'translatable' => $translatable,
      ],
      'taxonomies'                       => array_values( $this->gateway->getTaxonomiesOf( $postType ) ),
      'custom_fields_count'              => count( $keys ),
      'custom_fields_translatable_count' => $translatableFields,
      'acf_field_groups'                 => [],
      'urls'                             => [
        'post_types'    => $this->gateway->getSettingsUrl( 'post-types' ),
        'taxonomies'    => $this->gateway->getSettingsUrl( 'taxonomies' ),
        'custom_fields' => $this->gateway->getSettingsUrl( 'custom-fields' ),
      ],
    ];
  }


}
