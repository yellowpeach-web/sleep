<?php

namespace WPML\Legacy\Component\Translation\Sender\Taxonomy;

class TaxonomySendOperationCleanupCron implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

  const EVENT    = 'wpml_taxonomy_send_operation_cleanup';
  const SCHEDULE = 'hourly';

  private $sweeper;


  public function __construct( ?TaxonomySendOperationSweeper $sweeper = null ) {
    $this->sweeper = $sweeper;
  }


  public function add_hooks(): void {
    add_action( 'init', [ $this, 'schedule' ] );
    add_action( self::EVENT, [ $this, 'run' ] );
  }


  public function schedule(): void {
    if ( ! wp_next_scheduled( self::EVENT ) ) {
      wp_schedule_event( time() + HOUR_IN_SECONDS, self::SCHEDULE, self::EVENT );
    }
  }


  public function run(): void {
    try {
      ( $this->sweeper ?: new TaxonomySendOperationSweeper() )->sweep();
    } catch ( \Throwable $e ) {
      if ( class_exists( '\\WPML\\TM\\Jobs\\JobLog' ) ) {
        \WPML\TM\Jobs\JobLog::maybeInitRequest();
        \WPML\TM\Jobs\JobLog::createNewGroup(
          \WPML\TM\Jobs\JobLog::GROUP_ID_SEND_JOBS,
          'Taxonomy send operation cleanup'
        );
        \WPML\TM\Jobs\JobLog::addError(
          'taxonomy_send_operations_cron_sweep_failed',
          [ 'error' => get_class( $e ) ]
        );
        \WPML\TM\Jobs\JobLog::finishCurrentGroup();
      }
    }
  }

}
