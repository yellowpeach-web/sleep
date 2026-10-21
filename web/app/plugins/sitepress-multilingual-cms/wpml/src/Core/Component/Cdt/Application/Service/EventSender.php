<?php

namespace WPML\Core\Component\Cdt\Application\Service;

use WPML\Core\Component\Cdt\Application\Query\SiteIdentityQueryInterface;
use WPML\Core\Component\Cdt\Application\Repository\CdtStoreInterface;
use WPML\Core\Component\Cdt\Domain\CdtEvent;
use WPML\Core\Component\Cdt\Domain\Signer;

class EventSender {

  const ACTION              = 'cdt_event';
  const TIMEOUT_QUEUED      = 5;
  const TIMEOUT_DEACTIVATE  = 3;
  const RETRY_AFTER_SECONDS = 600;

  private $store;

  private $identity;

  private $poster;

  private $json;

  private $signer;

  private $scheduler;


  public function __construct(
    CdtStoreInterface $store,
    SiteIdentityQueryInterface $identity,
    HttpPosterInterface $poster,
    JsonEncoderInterface $json,
    Signer $signer,
    CronSchedulerInterface $scheduler
  ) {
    $this->store     = $store;
    $this->identity  = $identity;
    $this->poster    = $poster;
    $this->json      = $json;
    $this->signer    = $signer;
    $this->scheduler = $scheduler;
  }


  public function sendQueued( int $now ): void {
    $siteKey = $this->identity->getSiteKey();
    if ( $siteKey === null ) {
      return;
    }
    $siteUrl = $this->identity->getSiteUrl();

    $decisions = [];
    foreach ( $this->store->getEvents( true )['queue'] as $item ) {
      $event = CdtEvent::fromArray( $item['event'] ?? null );
      if ( $event === null ) {
        continue;
      }
      $status = $this->post( $event, $siteKey, $siteUrl, $now, self::TIMEOUT_QUEUED )['status'];

      if ( $status >= 200 && $status < 300 ) {
        $decisions[ $event->getDedupKey() ] = 'sent';
      } elseif ( $status >= 400 && $status < 500 ) {
        $decisions[ $event->getDedupKey() ] = 'dropped';
      } else {
        $attempts                           = isset( $item['attempts'] ) && is_numeric( $item['attempts'] ) ? (int) $item['attempts'] : 0;
        $decisions[ $event->getDedupKey() ] = $attempts >= 1 ? 'dropped' : 'retry';
      }
    }

    $this->writeBack( $decisions, $now );
  }


  public function sendNow( CdtEvent $event, int $now ): bool {
    $siteKey = $this->identity->getSiteKey();
    if ( $siteKey === null ) {
      return false;
    }

    $status = $this->post( $event, $siteKey, $this->identity->getSiteUrl(), $now, self::TIMEOUT_DEACTIVATE )['status'];

    return $status >= 200 && $status < 300;
  }


  private function writeBack( array $decisions, int $now ): void {
    if ( ! $decisions ) {
      return;
    }

    $events = $this->store->getEvents( true );
    $queue  = [];
    $retry  = false;
    foreach ( $events['queue'] as $item ) {
      $key      = is_array( $item['event'] ?? null ) && is_string( $item['event']['dedup_key'] ?? null ) ? $item['event']['dedup_key'] : '';
      $decision = $decisions[ $key ] ?? 'untouched';
      if ( $decision === 'sent' ) {
        continue;
      }
      if ( $decision === 'dropped' ) {
        $events['dropped']++;
        continue;
      }
      if ( $decision === 'retry' ) {
        $item['attempts'] = 1;
        $retry            = true;
      }
      $queue[] = $item;
    }
    $events['queue'] = $queue;

    $this->store->saveEvents( $events );
    if ( $retry ) {
      $this->scheduler->scheduleOnceAt( EventQueue::HOOK, $now + self::RETRY_AFTER_SECONDS );
    }
  }


  private function post( CdtEvent $event, string $siteKey, string $siteUrl, int $now, int $timeout ): array {
    $body = $this->json->encode( $event->toArray() );
    $ts   = $now + $this->clockOffset();

    return $this->poster->post(
      [
        'action'   => self::ACTION,
        'site_key' => $siteKey,
        'site_url' => $siteUrl,
        'ts'       => $ts,
        'sig'      => $this->signer->sign( $siteKey, $siteUrl, $ts, $body ),
        'body'     => $body,
      ],
      $timeout
    );
  }


  private function clockOffset(): int {
    $offset = $this->store->getAnswer()['clock_offset'] ?? 0;

    return is_numeric( $offset ) ? (int) $offset : 0;
  }


}
