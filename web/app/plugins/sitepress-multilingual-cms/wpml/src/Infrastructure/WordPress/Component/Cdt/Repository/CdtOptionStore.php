<?php

namespace WPML\Infrastructure\WordPress\Component\Cdt\Repository;

use WPML\Core\Component\Cdt\Application\Repository\CdtStoreInterface;
use WPML\Core\Port\Persistence\OptionsInterface;

class CdtOptionStore implements CdtStoreInterface {

  const ENGINE                    = 'wpml_cdt_engine';
  const FIRST_AUTO_TRANSLATION_AT = 'wpml_cdt_first_auto_translation_at';
  const LAST_AUTO_TRANSLATION_AT  = 'wpml_cdt_last_auto_translation_at';
  const HEALTH                    = 'wpml_cdt_health';
  const EVENTS                    = 'wpml_cdt_events';
  const ANSWER                    = 'wpml_cdt_answer';
  const LAST_CHECKIN_AT           = 'wpml_cdt_last_checkin_at';

  const AUTOLOAD = true;

  const NONE = 'none';

  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  public function getEngine(): ?string {
    $engine = $this->options->get( self::ENGINE, '' );

    return is_string( $engine ) && $engine !== '' && $engine !== self::NONE ? $engine : null;
  }


  public function hasEngine(): bool {
    return $this->options->get( self::ENGINE, null ) !== null;
  }


  public function isEngineKnown(): bool {
    $engine = $this->options->get( self::ENGINE, '' );

    return is_string( $engine ) && $engine !== '';
  }


  public function saveEngine( ?string $engine ): void {
    $this->options->save( self::ENGINE, $engine ?? self::NONE, self::AUTOLOAD );
  }


  public function saveEngineUnknown(): void {
    $this->options->save( self::ENGINE, '', self::AUTOLOAD );
  }


  public function getFirstAutoTranslationAt(): ?int {
    return $this->positiveInt( self::FIRST_AUTO_TRANSLATION_AT );
  }


  public function saveFirstAutoTranslationAt( int $at ): void {
    $this->options->save( self::FIRST_AUTO_TRANSLATION_AT, $at, self::AUTOLOAD );
  }


  public function getLastAutoTranslationAt(): ?int {
    return $this->positiveInt( self::LAST_AUTO_TRANSLATION_AT );
  }


  public function saveLastAutoTranslationAt( int $at ): void {
    $this->options->save( self::LAST_AUTO_TRANSLATION_AT, $at, self::AUTOLOAD );
  }


  public function getHealth(): array {
    return $this->arrayOption( self::HEALTH );
  }


  public function saveHealth( array $health ): void {
    $this->options->save( self::HEALTH, $health, self::AUTOLOAD );
  }


  public function getEvents( bool $fresh = false ): array {
    if ( $fresh ) {
      wp_cache_delete( 'alloptions', 'options' );
      wp_cache_delete( self::EVENTS, 'options' );
    }

    $events = $this->arrayOption( self::EVENTS );
    $queue  = [];
    foreach ( isset( $events['queue'] ) && is_array( $events['queue'] ) ? $events['queue'] : [] as $item ) {
      if ( is_array( $item ) ) {
        $queue[] = $item;
      }
    }

    return [
      'queue'   => $queue,
      'dropped' => isset( $events['dropped'] ) && is_numeric( $events['dropped'] ) ? max( 0, (int) $events['dropped'] ) : 0,
    ];
  }


  public function saveEvents( array $events ): void {
    $this->options->save( self::EVENTS, $events, self::AUTOLOAD );
  }


  public function getAnswer(): array {
    return $this->arrayOption( self::ANSWER );
  }


  public function saveAnswer( array $answer ): void {
    $this->options->save( self::ANSWER, $answer, self::AUTOLOAD );
  }


  public function getLastCheckinAt(): ?int {
    return $this->positiveInt( self::LAST_CHECKIN_AT );
  }


  public function saveLastCheckinAt( int $at ): void {
    $this->options->save( self::LAST_CHECKIN_AT, $at, self::AUTOLOAD );
  }


  public function seedDefaults(): void {
    $defaults = [
      self::ENGINE                    => '',
      self::FIRST_AUTO_TRANSLATION_AT => 0,
      self::LAST_AUTO_TRANSLATION_AT  => 0,
      self::HEALTH                    => [],
      self::EVENTS                    => [ 'queue' => [], 'dropped' => 0 ],
      self::ANSWER                    => [],
      self::LAST_CHECKIN_AT           => 0,
    ];

    foreach ( $defaults as $name => $value ) {
      $this->options->add( $name, $value, self::AUTOLOAD );
    }
  }


  private function positiveInt( string $name ): ?int {
    $value = $this->options->get( $name, 0 );
    $value = is_numeric( $value ) ? (int) $value : 0;

    return $value > 0 ? $value : null;
  }


  private function arrayOption( string $name ): array {
    $value = $this->options->get( $name, [] );

    return is_array( $value ) ? $value : [];
  }


}
