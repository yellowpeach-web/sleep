<?php

namespace WPML\Infrastructure\WordPress\Component\Cdt\Query;

use WPML\Core\Component\Cdt\Application\Repository\CdtStoreInterface;
use WPML\Core\Component\ReportContentStats\Application\Query\ReportCadenceQueryInterface;

class CdtReportCadenceQuery implements ReportCadenceQueryInterface {

  private $store;


  public function __construct( CdtStoreInterface $store ) {
    $this->store = $store;
  }


  public function isBlocked(): bool {
    $answer = $this->store->getAnswer();

    return array_key_exists( 'participates', $answer ) && $answer['participates'] === false;
  }


  public function isForced(): bool {
    return ! empty( $this->store->getAnswer()['report_now'] );
  }


}
