<?php

namespace WPML\Infrastructure\WordPress\Component\WpmlProxy\Domain\Repository;

use WPML\Core\Component\WpmlProxy\Domain\Repository\WpmlProxyRepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class WpmlProxyRepository implements WpmlProxyRepositoryInterface {

  const OPTION_NAME = 'wpml_proxy_enabled';

  const AUTOMATIC_RECORD_OPTION_NAME = 'wpml_proxy_automatic_flip';

  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  public function isEnabled(): bool {
    $isEnabled = $this->options->get( self::OPTION_NAME, false );

    return $isEnabled;
  }


  public function setIsEnabled( bool $isEnabled ) {
    $this->options->save( self::OPTION_NAME, $isEnabled ? 1 : 0 );
  }


  public function secondsSinceLastAutomaticFlip() {
    $record = $this->automaticRecord();

    if ( ! isset( $record['applied_at'] ) || ! is_numeric( $record['applied_at'] ) ) {
      return null;
    }

    return max( 0, time() - (int) $record['applied_at'] );
  }


  public function recordAutomaticFlip( bool $isEnabled ) {
    $this->options->save(
      self::AUTOMATIC_RECORD_OPTION_NAME,
      [
        'applied_at' => time(),
        'enabled'    => $isEnabled,
      ]
    );
  }


  public function recordRefusedAutomaticFlip( bool $isEnabled, string $reason ) {
    $record = $this->automaticRecord();

    $record['refused'] = [
      'at'      => time(),
      'enabled' => $isEnabled,
      'reason'  => $reason,
    ];

    $this->options->save( self::AUTOMATIC_RECORD_OPTION_NAME, $record );
  }


  public function clearAutomaticFlipRecord() {
    $this->options->delete( self::AUTOMATIC_RECORD_OPTION_NAME );
  }


  private function automaticRecord(): array {
    $record = $this->options->get( self::AUTOMATIC_RECORD_OPTION_NAME, [] );

    return is_array( $record ) ? $record : [];
  }


}
