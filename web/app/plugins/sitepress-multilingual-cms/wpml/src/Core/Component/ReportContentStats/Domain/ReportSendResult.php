<?php

namespace WPML\Core\Component\ReportContentStats\Domain;

class ReportSendResult {

  const SENT    = 'sent';
  const RETRY   = 'retry';
  const REFUSED = 'refused';

  private $outcome;


  private function __construct( string $outcome ) {
    $this->outcome = $outcome;
  }


  public static function sent(): self {
    return new self( self::SENT );
  }


  public static function retry(): self {
    return new self( self::RETRY );
  }


  public static function refused(): self {
    return new self( self::REFUSED );
  }


  public function isSent(): bool {
    return $this->outcome === self::SENT;
  }


  public function isRefused(): bool {
    return $this->outcome === self::REFUSED;
  }


}
