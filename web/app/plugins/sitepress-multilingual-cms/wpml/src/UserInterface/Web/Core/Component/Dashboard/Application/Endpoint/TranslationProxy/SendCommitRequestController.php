<?php

namespace WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\TranslationProxy;

use WPML\Core\Component\TranslationProxy\Application\Service\SendTranslationProxyCommitRequestException;
use WPML\Core\Component\TranslationProxy\Application\Service\SendTranslationProxyCommitRequestIndeterminateException;
use WPML\Core\Component\TranslationProxy\Application\Service\TranslationProxyServiceInterface;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Legacy\Component\TranslationProxy\Application\Service\SendLock;

class SendCommitRequestController implements EndpointInterface {

  private $translationProxyService;

  private $sendLock;


  public function __construct(
    TranslationProxyServiceInterface $translationProxyService,
    SendLock $sendLock
  ) {
    $this->translationProxyService = $translationProxyService;
    $this->sendLock                = $sendLock;
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
      return [
        'batchJobId' => $this->translationProxyService->sendCommitRequest(),
      ];
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
