<?php

namespace WPML\Core\Component\ReportContentStats\Application\Service;

use WPML\Core\Component\ReportContentStats\Domain\Repository\TeaSnapshotRepositoryInterface;

class TeaChangeDetectionService {

  private $teaSnapshotRepository;


  public function __construct( TeaSnapshotRepositoryInterface $teaSnapshotRepository ) {
    $this->teaSnapshotRepository = $teaSnapshotRepository;
  }


  public function saveReportedState( bool $enabled ): void {
    $this->teaSnapshotRepository->save( $enabled );
  }


  public function differsFromReported( bool $enabled ): bool {
    $reported = $this->teaSnapshotRepository->get();

    return $reported === null || $reported !== $enabled;
  }


}
