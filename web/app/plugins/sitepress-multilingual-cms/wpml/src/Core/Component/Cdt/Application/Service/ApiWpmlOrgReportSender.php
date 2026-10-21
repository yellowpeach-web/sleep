<?php

namespace WPML\Core\Component\Cdt\Application\Service;

use WPML\Core\Component\Cdt\Application\Query\SiteIdentityQueryInterface;
use WPML\Core\Component\Cdt\Application\Query\SiteStateQueryInterface;
use WPML\Core\Component\Cdt\Application\Repository\CdtStoreInterface;
use WPML\Core\Component\Cdt\Domain\ContentReportDocument;
use WPML\Core\Component\Cdt\Domain\Signer;
use WPML\Core\Component\ReportContentStats\Application\Query\ContentStatsTranslatableTypesQueryInterface;
use WPML\Core\Component\ReportContentStats\Domain\ContentStatsReport;
use WPML\Core\Component\ReportContentStats\Domain\ReportSenderInterface;
use WPML\Core\Component\ReportContentStats\Domain\ReportSendResult;

class ApiWpmlOrgReportSender implements ReportSenderInterface {

  const ACTION  = 'cdt_report';
  const TIMEOUT = 15;

  private $store;

  private $identity;

  private $siteState;

  private $postTypes;

  private $poster;

  private $json;

  private $signer;


  public function __construct(
    CdtStoreInterface $store,
    SiteIdentityQueryInterface $identity,
    SiteStateQueryInterface $siteState,
    ContentStatsTranslatableTypesQueryInterface $postTypes,
    HttpPosterInterface $poster,
    MapJsonEncoderInterface $json,
    Signer $signer
  ) {
    $this->store     = $store;
    $this->identity  = $identity;
    $this->siteState = $siteState;
    $this->postTypes = $postTypes;
    $this->poster    = $poster;
    $this->json      = $json;
    $this->signer    = $signer;
  }


  public function send( ContentStatsReport $report ): ReportSendResult {
    $siteKey = $this->identity->getSiteKey();
    if ( $siteKey === null ) {
      return ReportSendResult::retry();
    }

    $now     = time();
    $siteUrl = $this->identity->getSiteUrl();
    $body    = $this->json->encode(
      ContentReportDocument::build(
        $this->postTypes->getTranslationModes(),
        $report->getContentStats(),
        $this->siteState->getEditor(),
        $this->store->getEngine(),
        $now
      )
    );
    $ts      = $now + $this->clockOffset();

    $status = $this->poster->post(
      [
        'action'   => self::ACTION,
        'site_key' => $siteKey,
        'site_url' => $siteUrl,
        'ts'       => $ts,
        'sig'      => $this->signer->sign( $siteKey, $siteUrl, $ts, $body ),
        'body'     => $body,
      ],
      self::TIMEOUT
    )['status'];

    if ( $status >= 200 && $status < 300 ) {
      $this->updateAnswer( null );

      return ReportSendResult::sent();
    }

    if ( $status >= 400 && $status < 500 ) {
      $this->updateAnswer( 'report:' . $status );

      return ReportSendResult::refused();
    }

    return ReportSendResult::retry();
  }


  private function updateAnswer( ?string $error ): void {
    $answer               = $this->store->getAnswer();
    $answer['report_now'] = false;
    if ( $error !== null ) {
      $answer['last_error'] = $error;
    }
    $this->store->saveAnswer( $answer );
  }


  private function clockOffset(): int {
    $offset = $this->store->getAnswer()['clock_offset'] ?? 0;

    return is_numeric( $offset ) ? (int) $offset : 0;
  }


}
