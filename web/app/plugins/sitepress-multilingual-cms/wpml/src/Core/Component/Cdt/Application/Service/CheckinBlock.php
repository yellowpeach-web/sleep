<?php

namespace WPML\Core\Component\Cdt\Application\Service;

use WPML\Core\Component\Cdt\Application\Query\LiveAteHealthQueryInterface;
use WPML\Core\Component\Cdt\Application\Query\RequestContextQueryInterface;
use WPML\Core\Component\Cdt\Application\Query\SiteStateQueryInterface;
use WPML\Core\Component\Cdt\Application\Repository\CdtStoreInterface;
use WPML\Core\Component\Cdt\Domain\DateTimeFormat;
use WPML\Core\Component\Cdt\Domain\HealthCache;
use WPML\Core\Component\Cdt\Domain\Signer;

class CheckinBlock {

  const SCHEMA_VERSION = 1;
  const REPOSITORY     = 'wpml';
  const FIELD_BODY     = 'cdt';
  const FIELD_TS       = 'cdt_ts';
  const FIELD_SIG      = 'cdt_sig';

  private $siteState;

  private $liveHealth;

  private $store;

  private $healthRefresh;

  private $json;

  private $signer;

  private $requestContext;

  private $checkinRequest;

  private $events;


  public function __construct(
    SiteStateQueryInterface $siteState,
    LiveAteHealthQueryInterface $liveHealth,
    CdtStoreInterface $store,
    HealthRefresh $healthRefresh,
    JsonEncoderInterface $json,
    Signer $signer,
    RequestContextQueryInterface $requestContext,
    CheckinRequest $checkinRequest,
    EventQueue $events
  ) {
    $this->siteState      = $siteState;
    $this->liveHealth     = $liveHealth;
    $this->store          = $store;
    $this->healthRefresh  = $healthRefresh;
    $this->json           = $json;
    $this->signer         = $signer;
    $this->requestContext = $requestContext;
    $this->checkinRequest = $checkinRequest;
    $this->events         = $events;
  }


  public function addTo( array $request, int $now ): array {
    if ( ( $request['repository_id'] ?? null ) !== self::REPOSITORY ) {
      return $request;
    }

    $this->checkinRequest->clear();

    if ( ! $this->requestContext->isAdminPageLoad() ) {
      return $request;
    }

    $siteKey = is_string( $request['site_key'] ?? null ) ? $request['site_key'] : '';
    $siteUrl = is_string( $request['site_url'] ?? null ) ? $request['site_url'] : '';

    if ( $siteKey === '' ) {
      $request[ self::FIELD_BODY ] = $this->json->encode( $this->unsignedDocument() );

      return $request;
    }

    $document = $this->document( $now );
    $body     = $this->json->encode( $document );
    $ts       = $now + $this->clockOffset();

    $this->events->markReported( is_int( $document['events_dropped'] ) ? $document['events_dropped'] : 0 );

    $request[ self::FIELD_BODY ] = $body;
    $request[ self::FIELD_TS ]   = $ts;
    $request[ self::FIELD_SIG ]  = $this->signer->sign( $siteKey, $siteUrl, $ts, $body );

    $this->store->saveLastCheckinAt( $now );
    $this->checkinRequest->markSent( $now );

    return $request;
  }


  public function document( int $now ): array {
    $health = HealthCache::fromArray( $this->store->getHealth() );
    $this->healthRefresh->scheduleIfStale( $health, $now );

    $events = $this->events->counts();

    return [
      'schema_version'             => self::SCHEMA_VERSION,
      'wpml_version'               => $this->siteState->getWpmlVersion(),
      'setup_complete'             => $this->siteState->isSetupComplete(),
      'default_language'           => $this->siteState->getDefaultLanguage(),
      'languages'                  => $this->siteState->getLanguages(),
      'translate_everything'       => $this->siteState->isTranslateEverythingOn(),
      'engine'                     => $this->store->getEngine(),
      'editor'                     => $this->siteState->getEditor(),
      'first_auto_translation_at'  => DateTimeFormat::fromUnix( $this->store->getFirstAutoTranslationAt() ),
      'last_auto_translation_at'   => DateTimeFormat::fromUnix( $this->store->getLastAutoTranslationAt() ),
      'last_manual_translation_at' => $health ? DateTimeFormat::fromUnix( $health->getLastManualTranslationAt() ) : null,
      'woocommerce'                => $this->siteState->isWooCommerceActive(),
      'consent'                    => $this->siteState->hasConsent(),
      'events_pending'             => $events['pending'],
      'events_dropped'             => $events['dropped'],
      'ate_health'                 => $health ? $this->ateHealth( $health, $now ) : null,
    ];
  }


  private function unsignedDocument(): array {
    $events = $this->events->counts();

    return [
      'schema_version'             => self::SCHEMA_VERSION,
      'wpml_version'               => $this->siteState->getWpmlVersion(),
      'setup_complete'             => $this->siteState->isSetupComplete(),
      'default_language'           => null,
      'languages'                  => null,
      'translate_everything'       => null,
      'engine'                     => null,
      'editor'                     => null,
      'first_auto_translation_at'  => null,
      'last_auto_translation_at'   => null,
      'last_manual_translation_at' => null,
      'woocommerce'                => $this->siteState->isWooCommerceActive(),
      'consent'                    => $this->siteState->hasConsent(),
      'events_pending'             => $events['pending'],
      'events_dropped'             => $events['dropped'],
      'ate_health'                 => null,
    ];
  }


  private function ateHealth( HealthCache $health, int $now ): array {
    return [
      'computed_at'               => DateTimeFormat::fromUnix( $health->getComputedAt() ),
      'failed_jobs'               => $health->getFailedJobs(),
      'last_error_type'           => $health->getLastErrorType(),
      'last_error_at'             => DateTimeFormat::fromUnix( $health->getLastErrorAt() ),
      'stuck_at_ate'              => $health->getStuckAtAte(),
      'late_mode'                 => $this->liveHealth->getLateMode(),
      'oldest_pending_hours'      => $this->liveHealth->getOldestPendingHours( $now ),
      'reconnect_code'            => $this->liveHealth->getReconnectCode(),
      'median_delivery_hours_30d' => $health->getMedianDeliveryHours30d(),
    ];
  }


  private function clockOffset(): int {
    $offset = $this->store->getAnswer()['clock_offset'] ?? 0;

    return is_numeric( $offset ) ? (int) $offset : 0;
  }


}
