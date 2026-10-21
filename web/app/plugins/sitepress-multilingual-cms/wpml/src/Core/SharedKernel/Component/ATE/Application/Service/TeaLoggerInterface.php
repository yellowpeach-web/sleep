<?php

namespace WPML\Core\SharedKernel\Component\ATE\Application\Service;

interface TeaLoggerInterface {


  public function beginDashboardEnable(): void;


  public function beginDashboardDisable(): void;


  public function beginWizardCompletion(): void;


  public function beginPublicCreatePing(): void;


  public function beginReachabilityRecheck(): void;


  public function beginLanguageAdded(): void;


  public function beginPostTypeBecameTranslatable(): void;


  public function beginTeaRecheckScope(): void;


  public function end(): void;



  public function setupCompletedListenerFired( bool $teaEnabled, bool $tmAllowed ): void;


  public function setupCompletedNotifySkipped(): void;




  public function dashboardEnableSkipped( string $reason ): void;




  public function languageAddedSkipped( string $reason ): void;


  public function postTypeBecameTranslatableSkipped( string $reason ): void;


  public function teaRecheckScopeSkipped( string $reason ): void;


  public function teaRecheckScopeParked( array $types ): void;



  public function teaStatusRequest( string $trigger, bool $active, int $estimatedJobs ): void;


  public function teaStatusResponse( string $trigger, ?bool $accepted, bool $reachable, int $elapsedMs ): void;


  public function teaStatusHttpError( string $trigger, string $errorMessage, int $elapsedMs ): void;


  public function teaStatusMalformedBody(
    string $trigger,
    string $bodySnippet,
    int $elapsedMs,
    ?string $reason = null
  ): void;


  public function reachabilityPersisted( string $trigger, bool $reachable ): void;



  public function reachabilityProbeRequest( string $trigger ): void;


  public function reachabilityProbeResponse(
    string $trigger,
    ?bool $accepted,
    bool $reachable,
    ?string $checkedUrl,
    int $elapsedMs
  ): void;


  public function reachabilityProbeHttpError( string $trigger, string $errorMessage, int $elapsedMs ): void;


  public function reachabilityProbeMalformedBody(
    string $trigger,
    string $bodySnippet,
    int $elapsedMs,
    ?string $reason = null
  ): void;


  public function reachabilityProbeUnsupported( string $trigger ): void;



  public function publicCreateReceived( string $receivedSiteIdentifier, bool $check, ?string $requestIp ): void;


  public function siteIdentifierMismatch( string $expectedUuid, string $receivedUuid ): void;


  public function reachabilityCheckOk(): void;


  public function lockAttempt( string $lockKey ): void;


  public function lockHeldByOther(): void;


  public function lockAcquired(): void;


  public function batchProcessed( int $created, int $remaining, int $elapsedMs ): void;


  public function envelopeReturned( string $action, ?string $reason, int $httpStatus ): void;


  public function publicCreateException( string $exceptionClass, string $message, ?string $code = null ): void;


  public function setupStateLeft( string $leftKey ): void;


  public function mediaSetupNotFinishedDeferred(): void;


}
