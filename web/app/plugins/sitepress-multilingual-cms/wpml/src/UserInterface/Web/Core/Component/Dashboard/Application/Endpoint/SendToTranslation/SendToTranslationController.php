<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\SendToTranslation;

use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\ReleaseLedger;
use WPML\Core\Component\Translation\Application\Service\Dto\SendToTranslationDto;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\TaxonomySendOperationCoordinator;
use WPML\Core\Component\Translation\Application\Service\TranslationService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Legacy\Component\TranslationProxy\Application\Service\SendLock;
use WPML\PHP\Exception\Exception;
use WPML\PHP\Exception\InvalidArgumentException;

class SendToTranslationController implements EndpointInterface {

  const CODE_SEND_LOCK_BUSY = 'send_lock_busy';

  private $translationService;

  private $sendLock;

  private $taxonomyOperations;


  public function __construct(
    TranslationService $translationService,
    SendLock $sendLock,
    ?TaxonomySendOperationCoordinator $taxonomyOperations = null
  ) {
    $this->translationService = $translationService;
    $this->sendLock           = $sendLock;
    $this->taxonomyOperations = $taxonomyOperations;
  }


  public function handle( $requestData = null ): array {
    $requestData = $requestData ?: [];
    $taxonomyContext = null;
    $settledRounds = null;

    if ( ! $this->sendLock->acquire() ) {
      \WPML\TM\Jobs\JobLog::maybeInitRequest();
      \WPML\TM\Jobs\JobLog::createNewGroup( \WPML\TM\Jobs\JobLog::GROUP_ID_SEND_JOBS, 'TP send refused: lock busy' );
      \WPML\TM\Jobs\JobLog::addError( 'tp_send_lock_busy', [] );
      \WPML\TM\Jobs\JobLog::finishCurrentGroup();

      return [
        'success' => false,
        'data'    => __( 'Another translation send is already in progress. Please try again in a moment.', 'wpml' ),
        'code'    => self::CODE_SEND_LOCK_BUSY,
      ];
    }

    try {
      $startedAt       = $this->now();
      $taxonomyContext = $this->taxonomyOperations()->prepare( $requestData );
      $sendToTranslationDto = $taxonomyContext
        ? SendToTranslationDto::fromResolvedArray( $this->withIsRetry( $taxonomyContext['request'], $requestData ) )
        : SendToTranslationDto::fromArray( $requestData );

      $releaseMark = ReleaseLedger::instance()->mark();

      $result = $this->translationService->send( $sendToTranslationDto, true );

      $data = $result->toArray();
      if ( $taxonomyContext ) {
        $data = array_merge( $data, $this->taxonomyOperations()->settle( $taxonomyContext, $result ) );

        $slowestRound = $this->now() - $startedAt;
        $rounds       = 1;
        while ( $this->taxonomyOperations()->mayContinueInRequest( $requestData, $data, $rounds, $this->now() - $startedAt, $slowestRound ) ) {
          $settledRounds   = $data;
          $roundStartedAt  = $this->now();
          $taxonomyContext = $this->taxonomyOperations()->prepare( $this->taxonomyOperations()->nextRoundRequest( $requestData, $data ) );
          if ( ! $taxonomyContext ) {
            break;
          }

          $result        = $this->translationService->send(
            SendToTranslationDto::fromResolvedArray( $this->withIsRetry( $taxonomyContext['request'], $requestData ) ),
            true
          );
          $data          = $this->taxonomyOperations()->mergeRounds(
            $settledRounds,
            array_merge( $result->toArray(), $this->taxonomyOperations()->settle( $taxonomyContext, $result ) )
          );
          $settledRounds = null;
          $slowestRound  = max( $slowestRound, $this->now() - $roundStartedAt );
          $rounds++;
        }
      }

      return [
        'success' => true,
        'data'    => $data,
        'release' => ReleaseLedger::instance()->summaryFrom( $releaseMark )->toArray(),
      ];
    } catch ( InvalidArgumentException $e ) {
      $response = [
        'success' => false,
        'data' => sprintf(
          /* translators: %s is the validation error returned for the request. */
          __( 'The request data for SendToTranslation is not valid: %s', 'wpml' ),
          $e->getMessage()
        )
      ];
    } catch ( TranslationService\TranslationServiceException $e ) {
      $response = [
        'success' => false,
        'data'    => 'TranslationServiceException: ' . $e->getMessage()
      ];

      $partialResult = $e->getPartialResult();
      if ( $partialResult ) {
        $partialData = $partialResult->toArray();
        if ( $taxonomyContext ) {
          $partialData = array_merge(
            $partialData,
            $this->taxonomyOperations()->settle( $taxonomyContext, $partialResult )
          );
        }
        $response['partialData'] = $settledRounds
          ? $this->taxonomyOperations()->mergeRounds( $settledRounds, $partialData )
          : $partialData;
        $settledRounds           = null;
      }
    } catch ( Exception $e ) {
      $response = [
        'success' => false,
        'data'    => $e->getMessage()
      ];
    } finally {
      $this->sendLock->release();
    }

    if ( null !== $settledRounds ) {
      $response['partialData'] = $settledRounds;
    }

    return $response;
  }


  private function withIsRetry( array $resolvedRequest, array $requestData ): array {
    if ( array_key_exists( 'isRetry', $requestData ) ) {
      $resolvedRequest['isRetry'] = $requestData['isRetry'];
    }

    return $resolvedRequest;
  }


  protected function now(): float {
    return microtime( true );
  }


  private function taxonomyOperations(): TaxonomySendOperationCoordinator {
    if ( null === $this->taxonomyOperations ) {
      $this->taxonomyOperations = new TaxonomySendOperationCoordinator();
    }

    return $this->taxonomyOperations;
  }


}
