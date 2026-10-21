<?php

namespace WPML\Core\Component\Translation\Application\Service\Event;

use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\BatchResult;

class CancelAllAutomaticJobsEvent extends \WPML\Core\Port\Event\Event {


  public function __construct( BatchResult $result ) {
    parent::__construct( 'wpml_cancel_all_automatic_jobs', [ $result ] );
  }


}
