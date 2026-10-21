<?php

namespace WPML\Core\Component\ReportContentStats\Domain\Repository;

interface TeaSnapshotRepositoryInterface {


  public function get(): ?bool;


  public function save( bool $enabled ): void;


}
