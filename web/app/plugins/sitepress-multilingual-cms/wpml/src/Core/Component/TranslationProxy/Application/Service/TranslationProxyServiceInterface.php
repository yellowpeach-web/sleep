<?php

namespace WPML\Core\Component\TranslationProxy\Application\Service;

interface TranslationProxyServiceInterface {


  public function sendCommitRequest( $batchName = null, $batchId = null );


  public function getTPUrl(): string;


}
