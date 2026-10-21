<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslateEverything;

use WPML\Core\Component\ATE\Application\Service\TranslateEverythingPrerequisites;
use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\Component\Translation\Application\Service\SettingsService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\AtePingerInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\TeaLoggerInterface;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\Core\SharedKernel\Component\Setting\Domain\TranslationEditorSetting;
use WPML\PHP\Exception\Exception;

class EnableController implements EndpointInterface {

  const LOG_REASONS = [
    TranslateEverythingPrerequisites::REFUSAL_AI_SETUP         => 'ai_setup_skipped',
    TranslateEverythingPrerequisites::REFUSAL_SITE_DESCRIPTION => 'site_description_missing',
  ];

  private $settingsService;

  private $translationSettingsRepository;

  private $languagesQuery;

  private $atePinger;

  private $logger;

  private $prerequisites;


  public function __construct(
    SettingsService $settingsService,
    SettingsRepository $translationSettingsRepository,
    LanguagesQueryInterface $languagesQuery,
    AtePingerInterface $atePinger,
    TeaLoggerInterface $logger,
    TranslateEverythingPrerequisites $prerequisites
  ) {
    $this->settingsService               = $settingsService;
    $this->translationSettingsRepository = $translationSettingsRepository;
    $this->languagesQuery                = $languagesQuery;
    $this->atePinger                     = $atePinger;
    $this->logger                        = $logger;
    $this->prerequisites                 = $prerequisites;
  }


  public function handle( $requestData = null ): array {
    $this->logger->beginDashboardEnable();

    try {
      $translationEditor = $this->translationSettingsRepository
          ->getSettings()
          ->getTranslationEditor();

      if ( ! isset( $translationEditor ) || $translationEditor->getValue() !== TranslationEditorSetting::ATE ) {
        $this->logger->dashboardEnableSkipped( 'ate_not_active' );
        return [
          'success' => false,
          'error'   => [
            'code'    => 'ate_not_active',
            'message' => __(
              'In order to translate automatically, you first need to enable WPML\'s Advanced Translation Editor',
              'wpml'
            ),
          ],
        ];
      }

      $refusal = $this->prerequisites->getRefusalKey();
      if ( $refusal !== null ) {
        $this->logger->dashboardEnableSkipped( self::LOG_REASONS[ $refusal ] ?? $refusal );
        return [
          'success' => false,
          'error'   => [
            'code'    => $refusal,
            'message' => $this->refusalMessage( $refusal ),
          ],
        ];
      }

      $defaultLanguage = $this->languagesQuery->getDefault();
      $defaultSupport  = $defaultLanguage->doesSupportAutomaticTranslations();

      if ( $defaultSupport === false ) {
        $this->logger->dashboardEnableSkipped( 'default_language_does_not_support_automatic_translations' );
        return [
          'success' => false,
          'error' => [
            'code' => 'default_language_does_not_support_automatic_translations',
            'message' => $defaultLanguage->getDisplayName(),
          ],
        ];
      }

      if ( $defaultSupport === null ) {
        $this->logger->dashboardEnableSkipped( 'default_language_support_unknown' );
        return [
          'success' => false,
          'error' => [
            'code' => 'default_language_support_unknown',
            'message' => __(
              'WPML could not check with our translation system if your default language can be translated automatically. Try again in a moment.',
              'wpml'
            ),
          ],
        ];
      }

      $requestData = $requestData ?: [];

      $reviewMode = isset( $requestData['reviewMode'] ) && is_scalar( $requestData['reviewMode'] )
        ? (string) $requestData['reviewMode']
        : null;

      $translateExisting = (bool) ( $requestData['translateExistingContent'] ?? false );

      $preflight  = apply_filters( 'wpml_translate_everything_preflight_advisories', [] );
      $advisories = is_array( $preflight ) ? $preflight : [];

      try {
        $this->settingsService->enableTranslateEverything( $translateExisting, $reviewMode );

        $response = [
          'success'   => true,
          'reachable' => $this->atePinger->notifyTeaEnabled( AtePingerInterface::TRIGGER_DASHBOARD_ENABLE ),
        ];

        if ( $advisories ) {
          $response['advisories'] = $advisories;
        }

        return $response;

      } catch ( Exception $e ) {
        return [
          'success' => false,
          'error' => [
            'code' => 'unexpected_error',
            'message' => $e->getMessage(),
          ],
        ];
      }
    } finally {
      $this->logger->end();
    }
  }


  private function refusalMessage( string $refusal ): string {
    switch ( $refusal ) {
      case TranslateEverythingPrerequisites::REFUSAL_SITE_DESCRIPTION:
        return __(
          'Add your site description before you can turn this on.',
          'wpml'
        );

      case TranslateEverythingPrerequisites::REFUSAL_AI_SETUP:
      default:
        return __(
          'Full-site automatic translation needs AI translation set up. Set it up under WPML > Settings > AI translation first.',
          'wpml'
        );
    }
  }


}
