<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslationProxy;

use WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomySendOperationRepository;
use WPML\Core\Component\TranslationProxy\Application\Service\SendLockInterface;
use WPML\Core\Component\TranslationProxy\Application\Service\SendTranslationProxyCommitRequestException;
use WPML\Core\Component\TranslationProxy\Application\Service\SendTranslationProxyCommitRequestIndeterminateException;
use WPML\Core\Component\TranslationProxy\Application\Service\TranslationProxyServiceInterface;
use WPML\Core\Port\Endpoint\EndpointInterface;

class SendCommitRequestController implements EndpointInterface {

  private $translationProxyService;

  private $sendLock;

  private $taxonomySendOperationRepository;


  public function __construct(
    TranslationProxyServiceInterface $translationProxyService,
    SendLockInterface $sendLock,
    ?TaxonomySendOperationRepository $taxonomySendOperationRepository = null
  ) {
    $this->translationProxyService         = $translationProxyService;
    $this->sendLock                        = $sendLock;
    $this->taxonomySendOperationRepository = $taxonomySendOperationRepository ?: new TaxonomySendOperationRepository();
  }


  public function handle( $requestData = null ): array {
    if ( ! $this->sendLock->acquire() ) {
      \WPML\TM\Jobs\JobLog::maybeInitRequest();
      \WPML\TM\Jobs\JobLog::createNewGroup( \WPML\TM\Jobs\JobLog::GROUP_ID_SEND_JOBS, 'TP commit refused: lock busy' );
      \WPML\TM\Jobs\JobLog::addError( 'tp_send_lock_busy', [] );
      \WPML\TM\Jobs\JobLog::finishCurrentGroup();

      return [
        'batchJobId' => false,
        'busy'       => true,
      ];
    }

    try {
      $batchName = is_array( $requestData ) && is_string( $requestData['batchName'] ?? null )
        ? $requestData['batchName']
        : '';
      $batchId   = is_array( $requestData ) && is_numeric( $requestData['batchId'] ?? null )
        ? (int) $requestData['batchId']
        : 0;
      $hasIdentity = '' !== $batchName && $batchId > 0;
      $operationId = is_array( $requestData ) && is_string( $requestData['operationId'] ?? null )
        ? $requestData['operationId']
        : '';
      $hasOperation = 1 === preg_match( '/^[a-f0-9]{32}$/', $operationId );
      if ( $hasIdentity && $hasOperation
        && $this->taxonomySendOperationRepository->isTpBatchCommitted( $operationId, $batchName, $batchId )
      ) {
        return [ 'batchJobId' => $batchId ];
      }

      $batchJobId = $hasIdentity
        ? $this->translationProxyService->sendCommitRequest( $batchName, $batchId )
        : $this->translationProxyService->sendCommitRequest();
      if ( $batchJobId && $hasIdentity && $hasOperation ) {
        if ( $this->taxonomySendOperationRepository->markTpBatchCommitted( $operationId, $batchName, $batchId ) ) {
          $this->taxonomySendOperationRepository->deleteCommittedOperation( $operationId, $batchName, $batchId );
        }
      }

      return [ 'batchJobId' => $batchJobId ];
    } catch ( SendTranslationProxyCommitRequestIndeterminateException $e ) {
      return [
        'batchJobId' => false,
        'busy'       => true,
      ];
    } catch ( SendTranslationProxyCommitRequestException $e ) {
      return [
        'batchJobId' => false,
      ];
    } finally {
      $this->sendLock->release();
    }
  }


}
