<?php

namespace WPML\Legacy\Component\TranslationProxy\Application\Service;

use WPML\Core\Component\TranslationProxy\Application\Service\SendTranslationProxyCommitRequestException;
use WPML\Core\Component\TranslationProxy\Application\Service\SendTranslationProxyCommitRequestIndeterminateException;
use WPML\Core\Component\TranslationProxy\Application\Service\TranslationProxyServiceInterface;

class TranslationProxyService implements TranslationProxyServiceInterface {

  private $legacyTranslationProxyProject;


  public function __construct() {

    $currentService = \TranslationProxy::get_current_service();

    if ( is_wp_error( $currentService ) || $currentService === false ) {
      $this->legacyTranslationProxyProject = false;
    } else {
      $this->legacyTranslationProxyProject = new \TranslationProxy_Project(
        $currentService,
        'xmlrpc',
        \TranslationProxy::get_tp_client()
      );
    }
  }


  public function sendCommitRequest() {

    \WPML\TM\Jobs\JobLog::maybeInitRequest();
    \WPML\TM\Jobs\JobLog::createNewGroup(
      \WPML\TM\Jobs\JobLog::GROUP_ID_SEND_JOBS,
      'TP batch commit',
      [ 'batchName' => \WPML\TM\TranslationProxy\TpBatchState::getBatchName() ]
    );

    if ( ! $this->legacyTranslationProxyProject ) {
      \WPML\TM\Jobs\JobLog::addError( 'tp_commit_no_project', [] );
      \WPML\TM\Jobs\JobLog::finishCurrentGroup();

      return false;
    }

    try {
      $result = $this->legacyTranslationProxyProject->commit_batch_job();
      if ( ! $result ) {
        \WPML\TM\TranslationProxy\TpBatchState::clear();
        \WPML\TM\Jobs\JobLog::addError( 'tp_commit_batch_job_failed', [] );
        \WPML\TM\Jobs\JobLog::finishCurrentGroup();

        return false;
      }

      $batchJobId = $this->legacyTranslationProxyProject->get_batch_job_id();

      if ( ! is_numeric( $batchJobId ) ) {
        \WPML\TM\TranslationProxy\TpBatchState::clear();
        \WPML\TM\Jobs\JobLog::addError( 'tp_commit_no_batch_id', [ 'kind' => 'not_numeric' ] );
        \WPML\TM\Jobs\JobLog::finishCurrentGroup();

        return false;
      }

      $batchJobId = (int) $batchJobId;

      if ( ! $batchJobId ) {
        \WPML\TM\TranslationProxy\TpBatchState::clear();
        \WPML\TM\Jobs\JobLog::addError( 'tp_commit_no_batch_id', [ 'kind' => 'zero' ] );
        \WPML\TM\Jobs\JobLog::finishCurrentGroup();

        return false;
      }

      \WPML\TM\TranslationProxy\TpBatchState::clear();

      \WPML\TM\Jobs\JobLog::add( 'tp_batch_committed', [ 'batchJobId' => $batchJobId ] );
      \WPML\TM\Jobs\JobLog::finishCurrentGroup();

      return $batchJobId;
    } catch ( \Throwable $e ) {
      $verdict = \WPML\TM\TranslationProxy\CommitVerification::didPinnedBatchDispatch();

      if ( true === $verdict ) {
        $batchJobId = $this->legacyTranslationProxyProject->get_batch_job_id();
        $batchJobId = is_numeric( $batchJobId ) ? (int) $batchJobId : true;
        \WPML\TM\TranslationProxy\TpBatchState::clear();

        \WPML\TM\Jobs\JobLog::add(
          'tp_commit_recovered_as_committed',
          [
            'batchJobId' => is_int( $batchJobId ) ? $batchJobId : null,
            'message'    => $e->getMessage(),
          ]
        );
        \WPML\TM\Jobs\JobLog::finishCurrentGroup();

        return $batchJobId;
      }

      \WPML\TM\Jobs\JobLog::addError(
        'tp_commit_indeterminate',
        [
          'verified_not_dispatched_yet' => ( false === $verdict ),
          'message'                     => $e->getMessage(),
        ]
      );
      \WPML\TM\Jobs\JobLog::finishCurrentGroup();

      throw new SendTranslationProxyCommitRequestIndeterminateException( $e->getMessage() );
    }
  }


  public function getTPUrl(): string {
    return OTG_TRANSLATION_PROXY_URL;
  }


}
