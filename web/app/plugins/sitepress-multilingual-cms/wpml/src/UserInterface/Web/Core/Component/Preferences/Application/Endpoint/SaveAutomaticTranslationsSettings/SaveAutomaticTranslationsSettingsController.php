<?php

namespace WPML\UserInterface\Web\Core\Component\Preferences\Application\Endpoint\SaveAutomaticTranslationsSettings;

use WPML\Core\Component\ATE\Application\Service\ActiveEngineQueryInterface;
use WPML\Core\Component\ATE\Application\Service\EngineServiceException;
use WPML\Core\Component\ATE\Application\Service\EnginesServiceInterface;
use WPML\Core\Component\Cdt\Application\Service\EngineChange;
use WPML\Core\Component\PostHog\Application\Service\Config\ConfigService;
use WPML\Core\Component\PostHog\Application\Service\Event\CaptureEventService;
use WPML\Core\Component\PostHog\Application\Service\Event\EventInstanceService;
use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\Component\Translation\Application\Service\SettingsService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\PHP\Exception\Exception;

class SaveAutomaticTranslationsSettingsController implements EndpointInterface {

  private $settingsRepository;

  private $settingsService;

  private $engineService;

  private $enginesBuilder;

  private $configService;

  private $captureEventService;

  private $eventInstanceService;

  private $activeEngineQuery;

  private $engineChange;


  public function __construct(
    SettingsRepository $settingsRepository,
    SettingsService $settingsService,
    EnginesServiceInterface $engineService,
    EnginesBuilder $enginesBuilder,
    ConfigService $configService,
    CaptureEventService $captureEventService,
    EventInstanceService $eventInstanceService,
    ActiveEngineQueryInterface $activeEngineQuery,
    EngineChange $engineChange
  ) {
    $this->settingsRepository   = $settingsRepository;
    $this->settingsService      = $settingsService;
    $this->engineService        = $engineService;
    $this->enginesBuilder       = $enginesBuilder;
    $this->configService        = $configService;
    $this->captureEventService  = $captureEventService;
    $this->eventInstanceService = $eventInstanceService;
    $this->activeEngineQuery    = $activeEngineQuery;
    $this->engineChange         = $engineChange;
  }


  public function handle( $requestData = null ): array {
    $this->engineService->flushCache();

    try {
      if (
        isset( $requestData['engines'] ) &&
        is_array( $requestData['engines'] ) &&
        ! empty( $requestData['engines'] )
      ) {
        $engines = $this->enginesBuilder->build( $requestData['engines'] );
        $this->engineService->update( $engines );
      }
    } catch ( EngineServiceException $e ) {
      return [
        'status'  => false,
        'message' => $e->getMessage(),
      ];
    }


    $this->recordEngineChange();

    if ( isset( $requestData['reviewMode'] ) && is_string( $requestData['reviewMode'] ) ) {
      $this->settingsService->saveReviewOption( $requestData['reviewMode'] );
    }

    if ( isset( $requestData['shouldTranslateAutomaticallyDrafts'] ) ) {
      $this->settingsRepository->saveShouldTranslateAutomaticallyDrafts(
        (bool) $requestData['shouldTranslateAutomaticallyDrafts']
      );
    }

    $this->captureAutomaticTranslationSettingsSaved( $requestData );

    return [
      'status'       => true,
      'activeEngine' => $this->resolveActiveEngine(),
    ];
  }


  private function resolveActiveEngine() {
    $engine = $this->activeEngineQuery->resolve()->getEngine();

    if ( $engine === null ) {
      return null;
    }

    return [
      'codeName'   => $engine->getCodeName(),
      'formalName' => $engine->getFormalName(),
    ];
  }


  private function recordEngineChange() {
    try {
      $this->engineChange->detect( time() );
    } catch ( \Throwable $e ) {
    }
  }


  private function captureAutomaticTranslationSettingsSaved( $requestData ) {
    if ( ! $requestData ) {
      return;
    }

    try {

      $config = $this->configService->create();

      $isPtcEngineSelected = isset( $requestData['isPtcEngineSelected'] ) && $requestData['isPtcEngineSelected'];

      $eventProperties = $this->buildEventProperties( $requestData, $isPtcEngineSelected );

      $eventInstance = $this
          ->eventInstanceService
          ->getAutomaticTranslationSettingsSavedEvent( $eventProperties );

      $this->captureEventService->capture(
        $config,
        $eventInstance
      );
    } catch ( Exception $e ) {
    }
  }


  private function buildEventProperties( $requestData, $isPtcEngineSelected ) {
    $currentSettings = $this->settingsRepository->getSettings();

    $reviewMode = null;
    if ( isset( $requestData['reviewMode'] ) ) {
      $reviewMode = $requestData['reviewMode'];
    } else {
      $currentReviewMode = $currentSettings->getReviewMode();
      $reviewMode        = $currentReviewMode ? $currentReviewMode->getValue() : null;
    }

    $translateDrafts = null;
    if ( isset( $requestData['shouldTranslateAutomaticallyDrafts'] ) ) {
      $translateDrafts = (bool) $requestData['shouldTranslateAutomaticallyDrafts'];
    } else {
      $translateDrafts = $this->settingsRepository->shouldTranslateAutomaticallyDrafts();
    }

    $autoTranslateWhenEditorOpens = isset( $requestData['autoTranslateWhenEditorOpens'] )
      ? (bool) $requestData['autoTranslateWhenEditorOpens']
      : null;

    $eventProperties = [
      'translation_engine'               => $isPtcEngineSelected ? 'ptc' : 'other',
      'review_mode'                      => $reviewMode,
      'translate_drafts'                 => $translateDrafts,
      'auto_translate_when_editor_opens' => $autoTranslateWhenEditorOpens,
    ];

    $eventProperties = array_merge(
      $eventProperties,
      $this->extractSwitchFunnelProperties( $requestData )
    );

    if (
      $isPtcEngineSelected &&
      isset( $requestData['websiteContext'] ) &&
      is_array( $requestData['websiteContext'] )
    ) {
      $websiteContext             = $requestData['websiteContext'];
      $eventProperties['context'] = [
        'site_topic'    => $websiteContext['site_topic'] ?? '',
        'site_purpose'  => $websiteContext['site_purpose'] ?? '',
        'site_audience' => $websiteContext['site_audience'] ?? '',
      ];
    }

    return $eventProperties;
  }


  private function extractSwitchFunnelProperties( array $requestData ): array {
    if (
      ! isset( $requestData['teaNoticeFunnel'] ) ||
      ! is_array( $requestData['teaNoticeFunnel'] )
    ) {
      return [];
    }

    $raw = $requestData['teaNoticeFunnel'];

    $allowed = [
      'source',
      'gap_profile_initial',
      'surface',
      'site_key',
      'notice_instance_id',
      'from_engine',
    ];

    $props = [];
    foreach ( $allowed as $key ) {
      if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) ) {
        $props[ $key ] = $raw[ $key ];
      }
    }

    if (
      ! isset( $props['source'] ) ||
      ! in_array( $props['source'], [ 'tea_notice', 'quality_bar' ], true )
    ) {
      return [];
    }

    if (
      isset( $props['surface'] ) &&
      ! in_array(
        $props['surface'],
        [ 'translations_dashboard', 'wp_dashboard', 'payments_tab', 'ai_settings', 'dashboard' ],
        true
      )
    ) {
      return [];
    }

    return $props;
  }


}
