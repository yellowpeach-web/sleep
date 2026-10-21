<?php

namespace WPML\Core\Component\Cdt\Application\Service;

use WPML\Core\Component\Cdt\Application\Repository\CdtStoreInterface;
use WPML\Core\Component\Cdt\Domain\DateTimeFormat;

class AnswerReader {

  const ERROR_MAX_LENGTH = 128;

  private $store;

  private $checkinRequest;

  private $events;


  public function __construct( CdtStoreInterface $store, CheckinRequest $checkinRequest, EventQueue $events ) {
    $this->store          = $store;
    $this->checkinRequest = $checkinRequest;
    $this->events         = $events;
  }


  public function read( array $answer, int $now ): void {
    $sentAt = $this->checkinRequest->getSentAt();
    if ( $sentAt === null ) {
      return;
    }

    $previous = $this->store->getAnswer();

    $offset = isset( $previous['clock_offset'] ) && is_numeric( $previous['clock_offset'] ) ? (int) $previous['clock_offset'] : 0;
    if ( isset( $answer['server_ts'] ) && is_int( $answer['server_ts'] ) ) {
      $offset = $answer['server_ts'] - $sentAt;
    }

    $error = isset( $answer['error'] ) && is_string( $answer['error'] ) && $answer['error'] !== ''
      ? substr( $answer['error'], 0, self::ERROR_MAX_LENGTH )
      : null;

    $this->store->saveAnswer(
      [
        'participates' => isset( $answer['participates'] ) ? (bool) $answer['participates'] : (bool) ( $previous['participates'] ?? true ),
        'report_now'   => ! empty( $answer['report_now'] ) || ! empty( $previous['report_now'] ),
        'clock_offset' => $offset,
        'last_error'   => $error,
        'received_at'  => $now,
      ]
    );

    if ( $error === null && empty( $answer['duplicate'] ) ) {
      $this->events->confirmReported();
    }
  }


  public function getDebugInfo(): array {
    $answer = $this->store->getAnswer();

    return [
      'last_checkin_at' => DateTimeFormat::fromUnix( $this->store->getLastCheckinAt() ),
      'received_at'     => DateTimeFormat::fromUnix( isset( $answer['received_at'] ) && is_numeric( $answer['received_at'] ) ? (int) $answer['received_at'] : null ),
      'participates'    => $answer['participates'] ?? null,
      'report_now'      => $answer['report_now'] ?? null,
      'clock_offset'    => $answer['clock_offset'] ?? null,
      'last_error'      => $answer['last_error'] ?? null,
    ];
  }


}
