<?php

namespace WPML\Legacy\Component\Cdt\Query;

use WPML\Core\Component\Cdt\Application\Query\AtePollLimitQueryInterface;

class AtePollLimitQuery implements AtePollLimitQueryInterface {


  public function get(): int {
    return \WPML\TM\ATE\Jobs::LONGSTANDING_AT_ATE_SYNC_COUNT;
  }


}
