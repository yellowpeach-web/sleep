<?php

namespace WPML\Core\Component\Cdt\Application\Service;

use WPML\Core\Component\Cdt\Application\Query\HealthStatementsQueryInterface;
use WPML\Core\Component\Cdt\Application\Repository\CdtStoreInterface;
use WPML\Core\Component\Cdt\Domain\HealthCache;
use WPML\Core\Component\Translation\Domain\Repository\JobErrorRepositoryInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;

class HealthRefresh {

  const HOOK                = 'wpml_cdt_refresh_health';
  const MEDIAN_WINDOW_DAYS  = 30;
  const SECONDS_PER_DAY     = 86400;
  const SECONDS_PER_HOUR    = 3600;

  private $statements;

  private $jobErrors;

  private $store;

  private $scheduler;


  public function __construct(
    HealthStatementsQueryInterface $statements,
    JobErrorRepositoryInterface $jobErrors,
    CdtStoreInterface $store,
    CronSchedulerInterface $scheduler
  ) {
    $this->statements = $statements;
    $this->jobErrors  = $jobErrors;
    $this->store      = $store;
    $this->scheduler  = $scheduler;
  }


  public function scheduleIfStale( ?HealthCache $cache, int $now ): bool {
    if ( $cache !== null && ! $cache->isStale( $now ) ) {
      return false;
    }

    return $this->scheduler->scheduleOnceNow( self::HOOK );
  }


  public function refresh( int $now ): HealthCache {
    $started = microtime( true );

    $failedJobs = $this->jobErrors->count();
    $newest     = $this->statements->getNewestError();
    $pass       = $this->statements->getStuckAndDeliveries( $now - self::MEDIAN_WINDOW_DAYS * self::SECONDS_PER_DAY );
    $lastManual = $this->statements->getLastManualTranslationAt();

    $cache = new HealthCache(
      $now,
      (int) round( ( microtime( true ) - $started ) * 1000 ),
      $failedJobs,
      $newest['type'],
      $newest['at'],
      $pass['stuck'],
      self::medianHours( $pass['deliverySeconds'] ),
      $lastManual
    );

    $this->store->saveHealth( $cache->toArray() );

    return $cache;
  }


  public static function medianHours( array $seconds ): ?float {
    $count = count( $seconds );
    if ( $count === 0 ) {
      return null;
    }

    sort( $seconds );
    $middle = intdiv( $count, 2 );
    $median = $count % 2 === 1
      ? (float) $seconds[ $middle ]
      : ( $seconds[ $middle - 1 ] + $seconds[ $middle ] ) / 2;

    return round( $median / self::SECONDS_PER_HOUR, 2 );
  }


}
