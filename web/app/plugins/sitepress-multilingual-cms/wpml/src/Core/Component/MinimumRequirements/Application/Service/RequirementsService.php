<?php

namespace WPML\Core\Component\MinimumRequirements\Application\Service;

use Throwable;
use WPML\Core\Component\MinimumRequirements\Application\Repository\RequirementsRefreshLockInterface;
use WPML\Core\Component\MinimumRequirements\Domain\Entity\RequirementBase;
use WPML\Core\SharedKernel\Component\Server\Domain\CacheInterface;
use WPML\PHP\Exception\InvalidArgumentException;

class RequirementsService {

  private $requirements = [];

  private $cache;

  private $refreshLock;

  const CACHE_KEY = 'wpml_requirements';

  const CACHE_TTL_PASS = 60 * 60 * 12;

  const CACHE_TTL_FAIL = 60 * 10;


  public function __construct(
    array $requirements,
    CacheInterface $cache,
    RequirementsRefreshLockInterface $refreshLock
  ) {
    foreach ( $requirements as $requirement ) {
      if ( ! $requirement instanceof RequirementBase ) {
        throw new InvalidArgumentException( 'All requirements must be instances of RequirementBase' );
      }
    }

    $this->requirements = $requirements;
    $this->cache        = $cache;
    $this->refreshLock  = $refreshLock;
  }


  public function getAllRequirements( bool $useCache = false ): array {
    if ( $useCache ) {
      $cached = $this->getCachedRequirements();

      if ( $cached !== null ) {
        return $cached;
      }
    }

    return $this->computeRequirements( $useCache );
  }


  public function getInvalidRequirements( bool $useCache = false ): array {
    return $this->onlyInvalid( $this->getAllRequirements( $useCache ) );
  }


  public function getCachedRequirements() {
    $cached = $this->cache->get( self::CACHE_KEY );

    return is_array( $cached ) ? $cached : null;
  }


  public function getCachedInvalidRequirements() {
    $cached = $this->getCachedRequirements();

    return $cached === null ? null : $this->onlyInvalid( $cached );
  }


  public function getRequirementsOrPending( bool $onlyInvalid = false ) {
    $requirements = $this->getCachedRequirements();

    if ( $requirements === null ) {
      if ( ! $this->refreshLock->acquire() ) {
        return null;
      }

      try {
        $requirements = $this->getCachedRequirements() ?? $this->computeRequirements( true );
      } finally {
        $this->refreshLock->release();
      }
    }

    return $onlyInvalid ? $this->onlyInvalid( $requirements ) : $requirements;
  }


  private function computeRequirements( bool $useCache ): array {
    $output   = [];
    $allValid = true;
    foreach ( $this->requirements as $requirement ) {
      $data     = $requirement->toArray( $useCache );
      $allValid = $allValid && $data['isValid'];
      $output[] = $data;
    }

    $this->cache->set(
      self::CACHE_KEY,
      $output,
      $allValid ? self::CACHE_TTL_PASS : self::CACHE_TTL_FAIL
    );

    return $output;
  }


  private function onlyInvalid( array $requirements ): array {
    return array_values(
      array_filter(
        $requirements,
        function ( $requirement ) {
          return ! $requirement['isValid'];
        }
      )
    );
  }


}
