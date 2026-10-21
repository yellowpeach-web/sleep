<?php

namespace WPML\Legacy\Component\ATE\Application\Service;

use WPML\Core\SharedKernel\Component\ATE\Application\Service\TeaLoggerInterface;
use WPML\TM\Jobs\JobLog;

class TeaLogger implements TeaLoggerInterface {


  const LABEL_DASHBOARD_ENABLE              = 'Translate Everything enable via dashboard';
  const LABEL_DASHBOARD_DISABLE             = 'Translate Everything disable via dashboard';
  const LABEL_WIZARD_COMPLETION             = 'Translate Everything notify on wizard completion';
  const LABEL_PUBLIC_CREATE_PING            = 'Translate Everything ATE-driven ping (PublicCreate)';
  const LABEL_REACHABILITY_RECHECK          = 'Reachability re-check';
  const LABEL_LANGUAGE_ADDED                = 'TEA scope grew (language added)';
  const LABEL_POST_TYPE_BECAME_TRANSLATABLE = 'TEA scope grew (post type became translatable)';
  const LABEL_TEA_RECHECK_SCOPE             = 'TEA notify on translatable scope recheck';


  const EVENT_SETUP_COMPLETED_LISTENER_FIRED = 'setup_completed_listener_fired';
  const EVENT_SETUP_COMPLETED_NOTIFY_SKIPPED = 'setup_completed_notify_skipped';

  const EVENT_DASHBOARD_ENABLE_SKIPPED                = 'dashboard_enable_skipped';
  const EVENT_LANGUAGE_ADDED_SKIPPED                  = 'language_added_skipped';
  const EVENT_POST_TYPE_BECAME_TRANSLATABLE_SKIPPED   = 'post_type_became_translatable_skipped';
  const EVENT_TEA_RECHECK_SCOPE_SKIPPED               = 'tea_recheck_scope_skipped';
  const EVENT_TEA_RECHECK_SCOPE_PARKED                = 'tea_recheck_scope_parked';

  const EVENT_TEA_STATUS_REQUEST        = 'tea_status_request';
  const EVENT_TEA_STATUS_RESPONSE       = 'tea_status_response';
  const EVENT_TEA_STATUS_HTTP_ERROR     = 'tea_status_http_error';
  const EVENT_TEA_STATUS_MALFORMED_BODY = 'tea_status_malformed_body';
  const EVENT_REACHABILITY_PERSISTED    = 'reachability_persisted';

  const EVENT_REACHABILITY_PROBE_REQUEST        = 'reachability_probe_request';
  const EVENT_REACHABILITY_PROBE_RESPONSE       = 'reachability_probe_response';
  const EVENT_REACHABILITY_PROBE_HTTP_ERROR     = 'reachability_probe_http_error';
  const EVENT_REACHABILITY_PROBE_MALFORMED_BODY = 'reachability_probe_malformed_body';
  const EVENT_REACHABILITY_PROBE_UNSUPPORTED    = 'reachability_probe_unsupported';

  const EVENT_PUBLIC_CREATE_RECEIVED   = 'public_create_received';
  const EVENT_SITE_IDENTIFIER_MISMATCH = 'site_identifier_mismatch';
  const EVENT_REACHABILITY_CHECK_OK    = 'reachability_check_ok';
  const EVENT_LOCK_ATTEMPT             = 'lock_attempt';
  const EVENT_LOCK_HELD_BY_OTHER       = 'lock_held_by_other';
  const EVENT_LOCK_ACQUIRED            = 'lock_acquired';
  const EVENT_BATCH_PROCESSED          = 'batch_processed';
  const EVENT_ENVELOPE_RETURNED        = 'envelope_returned';
  const EVENT_PUBLIC_CREATE_EXCEPTION  = 'public_create_exception';
  const EVENT_SETUP_STATE_LEFT                  = 'setup_state_left';
  const EVENT_MEDIA_SETUP_NOT_FINISHED_DEFERRED = 'media_setup_not_finished_deferred';

  const ENDPOINT_TEA_STATUS = '/api/wpml/tea/status';
  const ENDPOINT_DELIVERY_REACHABILITY = '/api/wpml/delivery/reachability';



  public function beginDashboardEnable(): void {
    $this->beginGroup( self::LABEL_DASHBOARD_ENABLE );
  }


  public function beginDashboardDisable(): void {
    $this->beginGroup( self::LABEL_DASHBOARD_DISABLE );
  }


  public function beginWizardCompletion(): void {
    $this->beginGroup( self::LABEL_WIZARD_COMPLETION );
  }


  public function beginPublicCreatePing(): void {
    $this->beginGroup( self::LABEL_PUBLIC_CREATE_PING );
  }


  public function beginReachabilityRecheck(): void {
    $this->beginGroup( self::LABEL_REACHABILITY_RECHECK );
  }


  public function beginLanguageAdded(): void {
    $this->beginGroup( self::LABEL_LANGUAGE_ADDED );
  }


  public function beginPostTypeBecameTranslatable(): void {
    $this->beginGroup( self::LABEL_POST_TYPE_BECAME_TRANSLATABLE );
  }


  public function beginTeaRecheckScope(): void {
    $this->beginGroup( self::LABEL_TEA_RECHECK_SCOPE );
  }


  public function end(): void {
    JobLog::finishCurrentGroup();
  }


  private function beginGroup( string $label ): void {
    JobLog::maybeInitRequest();
    JobLog::createNewGroup( JobLog::GROUP_ID_TRANSLATE_EVERYTHING, $label, [] );
  }



  public function setupCompletedListenerFired( bool $teaEnabled, bool $tmAllowed ): void {
    JobLog::add(
      self::EVENT_SETUP_COMPLETED_LISTENER_FIRED,
      [
      'tea_enabled'   => $teaEnabled,
      'is_tm_allowed' => $tmAllowed,
       ]
    );
  }


  public function setupCompletedNotifySkipped(): void {
    JobLog::add( self::EVENT_SETUP_COMPLETED_NOTIFY_SKIPPED, [] );
  }



  public function dashboardEnableSkipped( string $reason ): void {
    JobLog::add(
      self::EVENT_DASHBOARD_ENABLE_SKIPPED,
      [
      'reason' => $reason,
       ]
    );
  }



  public function languageAddedSkipped( string $reason ): void {
    JobLog::add(
      self::EVENT_LANGUAGE_ADDED_SKIPPED,
      [
      'reason' => $reason,
       ]
    );
  }


  public function postTypeBecameTranslatableSkipped( string $reason ): void {
    JobLog::add(
      self::EVENT_POST_TYPE_BECAME_TRANSLATABLE_SKIPPED,
      [
      'reason' => $reason,
       ]
    );
  }


  public function teaRecheckScopeSkipped( string $reason ): void {
    JobLog::add(
      self::EVENT_TEA_RECHECK_SCOPE_SKIPPED,
      [
      'reason' => $reason,
       ]
    );
  }


  public function teaRecheckScopeParked( array $types ): void {
    JobLog::add(
      self::EVENT_TEA_RECHECK_SCOPE_PARKED,
      [
      'types' => array_values( $types ),
       ]
    );
  }



  public function teaStatusRequest( string $trigger, bool $active, int $estimatedJobs ): void {
    JobLog::add(
      self::EVENT_TEA_STATUS_REQUEST,
      [
      'trigger'        => $trigger,
      'active'         => $active,
      'endpoint'       => self::ENDPOINT_TEA_STATUS,
      'estimated_jobs' => $estimatedJobs,
       ]
    );
  }


  public function teaStatusResponse( string $trigger, ?bool $accepted, bool $reachable, int $elapsedMs ): void {
    JobLog::add(
      self::EVENT_TEA_STATUS_RESPONSE,
      [
      'trigger'    => $trigger,
      'accepted'   => $accepted,
      'reachable'  => $reachable,
      'elapsed_ms' => $elapsedMs,
       ]
    );
  }


  public function teaStatusHttpError( string $trigger, string $errorMessage, int $elapsedMs ): void {
    JobLog::addError(
      self::EVENT_TEA_STATUS_HTTP_ERROR,
      [
      'trigger'          => $trigger,
      'wp_error_message' => $errorMessage,
      'body_snippet'     => self::truncateBody( $errorMessage ),
      'elapsed_ms'       => $elapsedMs,
       ]
    );
  }


  public function teaStatusMalformedBody(
    string $trigger,
    string $bodySnippet,
    int $elapsedMs,
    ?string $reason = null
  ): void {
    $payload = [
      'trigger'      => $trigger,
      'body_snippet' => self::truncateBody( $bodySnippet ),
      'elapsed_ms'   => $elapsedMs,
    ];

    if ( $reason !== null ) {
      $payload['reason'] = $reason;
    }

    JobLog::addError( self::EVENT_TEA_STATUS_MALFORMED_BODY, $payload );
  }


  public function reachabilityPersisted( string $trigger, bool $reachable ): void {
    JobLog::add(
      self::EVENT_REACHABILITY_PERSISTED,
      [
      'trigger'   => $trigger,
      'reachable' => $reachable,
       ]
    );
  }



  public function reachabilityProbeRequest( string $trigger ): void {
    JobLog::add(
      self::EVENT_REACHABILITY_PROBE_REQUEST,
      [
      'trigger'  => $trigger,
      'endpoint' => self::ENDPOINT_DELIVERY_REACHABILITY,
       ]
    );
  }


  public function reachabilityProbeResponse(
    string $trigger,
    ?bool $accepted,
    bool $reachable,
    ?string $checkedUrl,
    int $elapsedMs
  ): void {
    JobLog::add(
      self::EVENT_REACHABILITY_PROBE_RESPONSE,
      [
      'trigger'     => $trigger,
      'accepted'    => $accepted,
      'reachable'   => $reachable,
      'checked_url' => $checkedUrl,
      'elapsed_ms'  => $elapsedMs,
       ]
    );
  }


  public function reachabilityProbeHttpError( string $trigger, string $errorMessage, int $elapsedMs ): void {
    JobLog::addError(
      self::EVENT_REACHABILITY_PROBE_HTTP_ERROR,
      [
      'trigger'          => $trigger,
      'wp_error_message' => $errorMessage,
      'body_snippet'     => self::truncateBody( $errorMessage ),
      'elapsed_ms'       => $elapsedMs,
       ]
    );
  }


  public function reachabilityProbeMalformedBody(
    string $trigger,
    string $bodySnippet,
    int $elapsedMs,
    ?string $reason = null
  ): void {
    $payload = [
      'trigger'      => $trigger,
      'body_snippet' => self::truncateBody( $bodySnippet ),
      'elapsed_ms'   => $elapsedMs,
    ];

    if ( $reason !== null ) {
      $payload['reason'] = $reason;
    }

    JobLog::addError( self::EVENT_REACHABILITY_PROBE_MALFORMED_BODY, $payload );
  }


  public function reachabilityProbeUnsupported( string $trigger ): void {
    JobLog::add(
      self::EVENT_REACHABILITY_PROBE_UNSUPPORTED,
      [
      'trigger'  => $trigger,
      'fallback' => self::ENDPOINT_TEA_STATUS,
       ]
    );
  }



  public function publicCreateReceived( string $receivedSiteIdentifier, bool $check, ?string $requestIp ): void {
    JobLog::add(
      self::EVENT_PUBLIC_CREATE_RECEIVED,
      [
      'received_site_identifier' => $receivedSiteIdentifier,
      'check'                    => $check,
      'request_ip'               => $requestIp,
       ]
    );
  }


  public function siteIdentifierMismatch( string $expectedUuid, string $receivedUuid ): void {
    JobLog::addError(
      self::EVENT_SITE_IDENTIFIER_MISMATCH,
      [
      'expected_uuid' => $expectedUuid,
      'received_uuid' => $receivedUuid,
       ]
    );
  }


  public function reachabilityCheckOk(): void {
    JobLog::add( self::EVENT_REACHABILITY_CHECK_OK, [] );
  }


  public function lockAttempt( string $lockKey ): void {
    JobLog::add(
      self::EVENT_LOCK_ATTEMPT,
      [
      'lock_key' => $lockKey,
       ]
    );
  }


  public function lockHeldByOther(): void {
    JobLog::add( self::EVENT_LOCK_HELD_BY_OTHER, [] );
  }


  public function lockAcquired(): void {
    JobLog::add( self::EVENT_LOCK_ACQUIRED, [] );
  }


  public function batchProcessed( int $created, int $remaining, int $elapsedMs ): void {
    JobLog::add(
      self::EVENT_BATCH_PROCESSED,
      [
      'created'    => $created,
      'remaining'  => $remaining,
      'elapsed_ms' => $elapsedMs,
       ]
    );
  }


  public function envelopeReturned( string $action, ?string $reason, int $httpStatus ): void {
    JobLog::add(
      self::EVENT_ENVELOPE_RETURNED,
      [
      'action'      => $action,
      'reason'      => $reason,
      'http_status' => $httpStatus,
       ]
    );
  }


  public function publicCreateException( string $exceptionClass, string $message, ?string $code = null ): void {
    JobLog::addError(
      self::EVENT_PUBLIC_CREATE_EXCEPTION,
      [
      'exception_class' => $exceptionClass,
      'message'         => $message,
      'code'            => $code,
       ]
    );
  }


  public function setupStateLeft( string $leftKey ): void {
    JobLog::addError(
      self::EVENT_SETUP_STATE_LEFT,
      [
      'left_key' => $leftKey,
       ]
    );
  }


  public function mediaSetupNotFinishedDeferred(): void {
    JobLog::add( self::EVENT_MEDIA_SETUP_NOT_FINISHED_DEFERRED, [] );
  }



  const BODY_SNIPPET_MAX_CHARS = 500;


  private static function truncateBody( string $body ): string {
    return mb_strlen( $body ) > self::BODY_SNIPPET_MAX_CHARS
      ? mb_substr( $body, 0, self::BODY_SNIPPET_MAX_CHARS ) . '…'
      : $body;
  }


}
