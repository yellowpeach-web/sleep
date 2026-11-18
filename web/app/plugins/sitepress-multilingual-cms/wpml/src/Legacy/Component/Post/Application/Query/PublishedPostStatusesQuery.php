<?php

namespace WPML\Legacy\Component\Post\Application\Query;

use WPML\Core\SharedKernel\Component\Post\Application\Query\PublishedPostStatusesQueryInterface;
use WPML\TM\AutomaticTranslation\Actions\PublishedPostStatuses;
use function WPML\Container\make;

class PublishedPostStatusesQuery implements PublishedPostStatusesQueryInterface {


  public function get(): array {
    return make( PublishedPostStatuses::class )->get();
  }


}
