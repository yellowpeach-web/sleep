<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\Core\Component\Translation\Application\Query\JobQueryInterface;
use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\SharedKernel\Component\Language\Application\Query\Dto\LanguageDto;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\PendingTranslatableOfferRepositoryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class PostTypesTranslationController implements PageRenderInterface {

  const LEGACY_PARTIAL = '/menu/_custom_types_translation.php';
  const SECTION_ID     = 'ml-content-setup-sec-7';
  const SECTION_SLUG   = 'post-types';
  const TEA_BUTTON_ID  = 'wpml-settings-tea-button';
  const TEA_MODAL_ID   = 'wpml-settings-tea-modal';
  const MODE_DISPLAY_AS_IF_TRANSLATED = 2;

  const CALC_DEFAULT_ALL  = 'all';
  const CALC_DEFAULT_NONE = 'none';

  private $languagesQuery;

  private $languagesQueryWithAutomaticSupport;

  private $settingsRepository;

  private $pendingOffer;

  private $jobQuery;

  private $jobsInProgressCount = null;

  private $teaCalculatorActive = false;

  private static $enqueued = false;

  private static $pendingCalcTypes = null;


  public function __construct(
    LanguagesQueryInterface $languagesQuery,
    LanguagesQueryInterface $languagesQueryWithAutomaticSupport,
    SettingsRepository $settingsRepository,
    PendingTranslatableOfferRepositoryInterface $pendingOffer,
    JobQueryInterface $jobQuery
  ) {
    $this->languagesQuery                     = $languagesQuery;
    $this->languagesQueryWithAutomaticSupport = $languagesQueryWithAutomaticSupport;
    $this->settingsRepository                 = $settingsRepository;
    $this->pendingOffer                       = $pendingOffer;
    $this->jobQuery                           = $jobQuery;

    $section = isset( $_GET['section'] ) && is_string( $_GET['section'] ) ? $_GET['section'] : '';
    if ( $section !== self::SECTION_SLUG ) {
      return;
    }

    $this->teaCalculatorActive = $this->isTranslateEverythingEnabled()
      && count( $this->languagesQuery->getActive() ) >= 2;

    if ( $this->teaCalculatorActive ) {
      add_action( 'admin_enqueue_scripts', array( $this, 'enqueueTeaCalculator' ) );
    }
  }


  public function render() {
    wp_enqueue_script( 'wpml-tm-mcs' );
    wp_enqueue_script( 'wpml-tm-mcs-translate-link-targets' );
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      __( 'Post Types Translation', 'wpml' ),
      __( 'Choose which post types are translatable and how WPML should fall back when a translation is missing. Items with a lock are managed by WordPress or WPML and cannot be changed.', 'wpml' )
    );

    if ( $this->teaCalculatorActive ) {
      $isTeaRunning = $this->jobsInProgressCount() > 0;
      $buttonClass  = 'button-secondary wpml-button base-btn wpml-button--outlined';
      if ( $isTeaRunning ) {
        $buttonClass .= ' is-disabled';
      }

      printf(
        '<p class="wpml-settings-tea-actions">'
          . '<button type="button" class="%1$s" id="%2$s"%5$s>%3$s</button>'
          . '</p><div id="%4$s"></div>',
        esc_attr( $buttonClass ),
        esc_attr( self::TEA_BUTTON_ID ),
        \wpml_bold_names(
          __( 'Adjust content limits for <b>Translate Everything</b>', 'wpml' )
        ),
        esc_attr( self::TEA_MODAL_ID ),
        $isTeaRunning
          ? ' aria-disabled="true" title="' . esc_attr( self::teaRunningTooltip() ) . '"'
          : ''
      );
    }

    $html    = LegacySectionExtractor::captureLegacyPartial( ICL_PLUGIN_PATH . self::LEGACY_PARTIAL );
    $section = LegacySectionExtractor::extractSectionById( $html, self::SECTION_ID );

    if ( $section === '' ) {
      echo '<p>' . esc_html__( 'No translatable post types are registered.', 'wpml' ) . '</p>';
    } else {
      echo LegacySectionExtractor::dissolveHiddenCollision( $section );
    }
  }


  public function enqueueTeaCalculator() {
    if ( self::$enqueued ) {
      return;
    }
    self::$enqueued = true;

    wp_enqueue_script( 'wpml-settings-tea' );
    if ( wp_style_is( 'wpml-setup-tea', 'registered' ) ) {
      wp_enqueue_style( 'wpml-setup-tea' );
    }
    if ( wp_style_is( 'wpml-settings-tea', 'registered' ) ) {
      wp_enqueue_style( 'wpml-settings-tea' );
    }

    $pendingCalcTypes = $this->pendingCalcTypes();

    $data = wp_json_encode(
      [
        'languages'           => $this->getLanguagesPayload(),
        'defaultLanguage'     => $this->languagesQuery->getDefaultCode(),
        'prioritize'          => array_keys( $pendingCalcTypes ),
        'pendingOffer'        => $pendingCalcTypes !== [],
        'prioritizeDefaults'  => $pendingCalcTypes ?: new \stdClass(),
        'translationsUrl'     => admin_url( 'admin.php?page=tm/menu/main.php' ),
        'doorSelector'        => '#' . self::TEA_BUTTON_ID,
        'jobsInProgressCount' => $this->jobsInProgressCount(),
      ]
    );
    wp_add_inline_script(
      'wpml-settings-tea',
      'window.wpmlSettingsTeaData = ' . ( $data ?: '{}' ) . ';',
      'before'
    );
    wp_add_inline_script( 'wpml-settings-tea', $this->getMountGlue(), 'after' );
  }


  private function pendingCalcTypes() {
    if ( self::$pendingCalcTypes !== null ) {
      return self::$pendingCalcTypes;
    }

    self::$pendingCalcTypes = $this->normalizePendingCalcTypes(
      $this->pendingOffer->getPendingTypes()
    );

    return self::$pendingCalcTypes;
  }


  private function normalizePendingCalcTypes( array $pending ) {
    $normalized = [];

    foreach ( $pending as $slug => $value ) {
      if ( ! is_string( $slug ) || $slug === '' ) {
        continue;
      }

      $mode = is_array( $value ) ? ( $value['new'] ?? null ) : $value;
      if ( ! is_int( $mode ) && ! ( is_string( $mode ) && is_numeric( $mode ) ) ) {
        continue;
      }

      $normalized[ $slug ] = (int) $mode === self::MODE_DISPLAY_AS_IF_TRANSLATED
        ? self::CALC_DEFAULT_NONE
        : self::CALC_DEFAULT_ALL;
    }

    return $normalized;
  }


  private function isTranslateEverythingEnabled() {
    return $this->settingsRepository->getSettings()->getTranslateEverything()->isEnabled();
  }


  private function jobsInProgressCount() {
    if ( $this->jobsInProgressCount === null ) {
      try {
        $this->jobsInProgressCount = $this->jobQuery->countAutomaticInProgress();
      } catch ( DatabaseErrorException $exception ) {
        $this->jobsInProgressCount = 0;
      }
    }

    return $this->jobsInProgressCount;
  }


  private static function teaRunningTooltip() {
    return __(
      'You can adjust the content limits once Translate Everything finishes the current translation run.',
      'wpml'
    );
  }


  private function getLanguagesPayload() {
    $languages = $this->languagesQueryWithAutomaticSupport->getActive();

    return array_map(
      function ( LanguageDto $language ) {
        return [
          'code'                             => $language->getCode(),
          'name'                             => $language->getEnglishName(),
          'flagUrl'                          => $language->getCountryFlagUrl() ?: '',
          'homeUrl'                          => '',
          'doesSupportAutomaticTranslations' => $language->doesSupportAutomaticTranslations() !== false,
          'excludedReason'                   => $language->getAutomaticTranslationsUnavailableReason(),
          'translationPaused'                => $language->isTranslationPaused(),
          'creditsPerWord'                   => false,
        ];
      },
      array_values( $languages )
    );
  }


  private function getMountGlue() {
    $buttonId = self::TEA_BUTTON_ID;
    $modalId  = self::TEA_MODAL_ID;

    return <<<JS
( function () {
  function declineOfferAndReload() {
    var reload = function () { window.location.reload(); };
    if ( ! window.WpmlSettingsTea || ! window.WpmlSettingsTea.discardPendingOffer ) {
      reload();
      return;
    }
    // Reload either way: on failure the pending-offer sweep still reverts the
    // offer within its TTL, and the reload shows whatever state is true now.
    window.WpmlSettingsTea.discardPendingOffer().then( reload, reload );
  }

  function open( prioritizeTypes ) {
    var container = document.getElementById( '{$modalId}' );
    if ( ! container || ! window.WpmlSettingsTea || ! window.wpmlSettingsTeaData ) {
      return false;
    }
    var isOffer = Boolean( prioritizeTypes && prioritizeTypes.length && window.wpmlSettingsTeaData.pendingOffer );
    // The offer lives only while its dialog is on screen: leaving the page
    // with it open declines it (keepalive request on pagehide). Disarmed once
    // the dialog was answered either way.
    var disarmLeave = isOffer && window.WpmlSettingsTea.declinePendingOfferOnLeave
      ? window.WpmlSettingsTea.declinePendingOfferOnLeave()
      : function () {};
    window.WpmlSettingsTea.mount(
      container,
      window.wpmlSettingsTeaData.languages,
      window.wpmlSettingsTeaData.defaultLanguage,
      {
        prioritizeTypes: prioritizeTypes || [],
        // Per-type pre-selected cut-off for the pinned types ('all'|'none');
        // only meaningful on the post-save auto-open (wpmldev-7356).
        prioritizeDefaults: ( prioritizeTypes && prioritizeTypes.length
          ? window.wpmlSettingsTeaData.prioritizeDefaults
          : {} ) || {},
        translationsUrl: window.wpmlSettingsTeaData.translationsUrl,
        onSaved: function () { disarmLeave(); },
        onCancelled: isOffer
          ? function () { disarmLeave(); declineOfferAndReload(); }
          : undefined
      }
    );
    return true;
  }

  document.addEventListener( 'click', function ( event ) {
    if ( ! event.target.closest( '#{$buttonId}' ) ) {
      return;
    }
    event.preventDefault();
    open( [] );
  } );

  // On-Save handoff: the save handler recorded the just-made-translatable types
  // as a pending offer and reloaded; open the calculator with them pinned. Deferred
  // to window 'load' so the legacy MCS scripts (which rebuild the settings
  // table on DOMContentLoaded) can't detach the React root after it mounts.
  function maybeAutoOpen() {
    var data = window.wpmlSettingsTeaData;
    if ( data && Array.isArray( data.prioritize ) && data.prioritize.length ) {
      open( data.prioritize );
    }
  }

  if ( document.readyState === 'complete' ) {
    maybeAutoOpen();
  } else {
    window.addEventListener( 'load', maybeAutoOpen );
  }
}() );
JS;
  }


}
