<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Repository;

interface BlobMapsReaderInterface {


  public function getMaps(): array;


  public function resetRuntimeCache();


}
