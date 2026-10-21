<?php

namespace WPML\Infrastructure\WordPress\Component\ReportContentStats\Domain\Repository;

use WPML\Core\Component\ReportContentStats\Domain\Repository\TeaSnapshotRepositoryInterface;
use WPML\Infrastructure\WordPress\Port\Persistence\Options;

class TeaSnapshotRepository implements TeaSnapshotRepositoryInterface {

  const OPTION_KEY = 'wpml-stats-tea-snapshot';

  private $options;


  public function __construct( Options $options ) {
    $this->options = $options;
  }


  public function get(): ?bool {
    $enabled = $this->options->get( self::OPTION_KEY, false );

    return $enabled !== false ? (bool) intval( $enabled ) : null;
  }


  public function save( bool $enabled ): void {
    $this->options->save( self::OPTION_KEY, $enabled ? 1 : 0 );
  }


}
