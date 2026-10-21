<?php

namespace WPML\Core\Component\WpmlProxy\Application\Service;

use WPML\Core\Component\WpmlProxy\Application\Exception\WpmlProxyException;
use WPML\Core\Component\WpmlProxy\Domain\Repository\WpmlProxyRepositoryInterface;

class WpmlProxyService {

  const AUTOMATIC_FLIP_COOLDOWN_SECONDS = 300;

  private $proxyRepository;


  public function __construct( WpmlProxyRepositoryInterface $proxyRepository ) {
    $this->proxyRepository = $proxyRepository;
  }


  public function enable() {
    $this->throwExceptionIfProxyIsControlledViaWPConstVariable();

    $this->proxyRepository->clearAutomaticFlipRecord();

    if ( $this->proxyRepository->isEnabled() ) {
      return;
    }

    $this->proxyRepository->setIsEnabled( true );
  }


  public function disable() {
    $this->throwExceptionIfProxyIsControlledViaWPConstVariable();

    $this->proxyRepository->clearAutomaticFlipRecord();

    if ( ! $this->proxyRepository->isEnabled() ) {
      return;
    }

    $this->proxyRepository->setIsEnabled( false );
  }


  public function applyAutomaticDecision( bool $isEnabled ): bool {
    $this->throwExceptionIfProxyIsControlledViaWPConstVariable();

    if ( $this->proxyRepository->isEnabled() === $isEnabled ) {
      return true;
    }

    $sinceLastFlip = $this->proxyRepository->secondsSinceLastAutomaticFlip();

    if ( null !== $sinceLastFlip && $sinceLastFlip < self::AUTOMATIC_FLIP_COOLDOWN_SECONDS ) {
      $this->proxyRepository->recordRefusedAutomaticFlip(
        $isEnabled,
        sprintf(
          'WPML changed the proxy by itself %d seconds ago and waits %d seconds between automatic changes.',
          $sinceLastFlip,
          self::AUTOMATIC_FLIP_COOLDOWN_SECONDS
        )
      );

      return false;
    }

    $this->proxyRepository->setIsEnabled( $isEnabled );
    $this->proxyRepository->recordAutomaticFlip( $isEnabled );

    return true;
  }


  public function isEnabled(): bool {
    if ( defined( 'WPML_DISABLE_PROXY' ) ) {
      return ! WPML_DISABLE_PROXY;
    }

    return $this->proxyRepository->isEnabled();
  }


  public function toggle(): bool {
    if ( $this->isEnabled() ) {
      $this->disable();

      return false;
    } else {
      $this->enable();

      return true;
    }
  }


  private function throwExceptionIfProxyIsControlledViaWPConstVariable() {
    if ( defined( 'WPML_DISABLE_PROXY' ) ) {
      throw new WpmlProxyException(
        'WPML_DISABLE_PROXY is defined in your wp-config.php,' .
        ' so WPML PROXY cannot be disabled or enabled automatically. Please remove it. '
      );
    }
  }


}
