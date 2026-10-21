<?php

namespace WPML\Core\Component\ReportContentStats\Application\Service\ReportSender;

use WPML\Core\Component\ReportContentStats\Domain\ContentStatsReport;
use WPML\Core\Component\ReportContentStats\Domain\ReportSenderInterface;
use WPML\Core\Component\ReportContentStats\Domain\ReportSendResult;

class ReportSenderService {

  private $reportSender;


  public function __construct( ReportSenderInterface $reportSender ) {
    $this->reportSender = $reportSender;
  }


  public function send( ContentStatsReport $report ): ReportSendResult {
    return $this->reportSender->send( $report );
  }


}
