<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\Core\Component\Translation\Application\Service\PreviousState\PreviousStateService;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslateEverythingScope;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\Setup\Option;
use WPML\TM\ATE\Sync\Process;
use WPML\TM\ATE\TranslateEverything\PostTypesSinceRepositoryFactory;
use WPML\TM\Jobs\JobLog;
use WPML\Translation\PreviousStateServiceFactory;
use WPML\Utilities\Lock;
use function WPML\Container\make;

class RetireVariationJobsOutsideTranslateEverything implements \IWPML_Upgrade_Command {

	const STATE_OPTION  = 'wpml_tea_retire_variation_jobs';
	const RESULT_OPTION = 'wpml_tea_retire_variation_jobs_result';
	const LOCK_NAME     = 'wpml_retire_variation_jobs';
	const LOCK_TIMEOUT  = 60;
	const WINDOW_SIZE   = 5000;
	const TIME_BUDGET   = 1.5;
	const ELEMENT_TYPE  = 'post_product_variation';

	private $result = false;

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->result;
	}

	private function run(): bool {
		if ( ! get_option( self::STATE_OPTION ) && ! $this->findTypesNotOfferedInScope() && ! $this->firstInFlightJobId() ) {
			return $this->result = true;
		}

		$lock = make( Lock::class, [ ':name' => self::LOCK_NAME ] );
		if ( ! $lock->create( self::LOCK_TIMEOUT ) ) {
			return $this->result = false;
		}

		$ownsLogRequest = JobLog::maybeInitBackgroundRequest( 'wpml-retire-variation-jobs' );
		JobLog::createNewGroup( JobLog::GROUP_ID_TRANSLATE_EVERYTHING, 'Retire product variation jobs (out of Translate Everything scope)' );

		try {
			$this->result = $this->runPass();
		} catch ( DatabaseErrorException $e ) {
			JobLog::addError( 'tea_variation_jobs_retire_failed', [ 'message' => $e->getMessage() ] );
			$this->result = false;
		} finally {
			$lock->release();
			JobLog::finishCurrentGroup();
			if ( $ownsLogRequest ) {
				JobLog::flushBackgroundRequest();
			}
		}

		return $this->result;
	}

	private function runPass(): bool {
		$state = $this->readState();

		if ( ! is_array( $state ) ) {
			$state = $this->start();
			if ( null === $state ) {
				return true;
			}
		}

		$state['passes']++;
		$deadline = microtime( true ) + self::TIME_BUDGET;

		list( $windowEnd, $reachesTheEnd ) = $this->nextWindow( $state['cursor'] );
		$rows = $windowEnd > $state['cursor'] ? $this->inFlightRows( $state['cursor'], $windowEnd ) : [];

		foreach ( array_chunk( $rows, PreviousStateService::REVERT_CHUNK ) as $chunk ) {
			$this->retire( $chunk, $state );
			$state['cursor'] = (int) end( $chunk )->job_id;

			if ( microtime( true ) > $deadline ) {
				update_option( self::STATE_OPTION, $state, false );

				return false;
			}
		}

		$state['cursor'] = $windowEnd;

		if ( ! $reachesTheEnd ) {
			update_option( self::STATE_OPTION, $state, false );

			return false;
		}

		$this->finish( $state );

		return true;
	}

	private function readState() {
		global $wpdb;

		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::STATE_OPTION ) );

		return null === $value ? null : maybe_unserialize( $value );
	}

	private function start() {
		$removed = $this->removeTypesNotOfferedFromScope();
		$firstId = $this->firstInFlightJobId();

		$state = [
			'cursor'            => $firstId - 1,
			'retired'           => 0,
			'to_complete'       => 0,
			'to_not_translated' => 0,
			'with_snapshot'     => 0,
			'passes'            => 0,
			'removed_types'     => array_keys( $removed ),
			'started'           => time(),
		];

		if ( $firstId ) {
			return $state;
		}

		if ( $removed ) {
			$this->finish( $state );
		}

		return null;
	}

	private function findTypesNotOfferedInScope(): array {
		return array_intersect_key(
			Option::getTranslateEverythingPostsSinceDates(),
			array_flip( TranslateEverythingScope::TYPES_NOT_OFFERED )
		);
	}

	private function removeTypesNotOfferedFromScope(): array {
		$found = $this->findTypesNotOfferedInScope();
		if ( ! $found ) {
			return [];
		}

		$repository = PostTypesSinceRepositoryFactory::create();
		$repository->discard( array_keys( $found ) );
		foreach ( array_keys( $found ) as $type ) {
			$repository->markPostTypeAsCompleted( $type );
		}

		JobLog::add(
			'tea_type_not_offered_removed_from_scope',
			[
				'types'                => array_keys( $found ),
				'previous_since_dates' => $found,
			]
		);

		return $found;
	}

	private function firstInFlightJobId(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN( j.job_id )
				   FROM {$wpdb->prefix}icl_translations t
				  INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.translation_id = t.translation_id
				  INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = s.rid
				  WHERE t.element_type = %s
				    AND t.source_language_code IS NOT NULL
				    AND s.status IN (" . implode( ', ', array_fill( 0, count( Process::IN_FLIGHT_STATUSES ), '%d' ) ) . ')
				    AND j.automatic = 1
				    AND j.translated = 0',
				array_merge( [ self::ELEMENT_TYPE ], Process::IN_FLIGHT_STATUSES )
			)
		);
	}

	private function nextWindow( int $cursor ): array {
		global $wpdb;

		$window = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT MAX( job_id ) AS last_id, COUNT(*) AS jobs
				   FROM ( SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE job_id > %d ORDER BY job_id LIMIT %d ) w",
				$cursor,
				self::WINDOW_SIZE
			)
		);

		$jobs = (int) ( $window->jobs ?? 0 );

		return [ $jobs ? (int) $window->last_id : $cursor, $jobs < self::WINDOW_SIZE ];
	}

	private function inFlightRows( int $afterId, int $throughId ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT j.job_id, j.rid,
				        s._prevstate IS NOT NULL AS has_snapshot,
				        t.element_id IS NOT NULL AS has_element,
				        j.editor_job_id AS ate_job_id
				   FROM {$wpdb->prefix}icl_translate_job j
				  INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.rid = j.rid
				  INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = s.translation_id
				  WHERE j.job_id > %d
				    AND j.job_id <= %d
				    AND j.automatic = 1
				    AND j.translated = 0
				    AND t.element_type = %s
				    AND s.status IN (" . implode( ', ', array_fill( 0, count( Process::IN_FLIGHT_STATUSES ), '%d' ) ) . ")
				    AND j.job_id = ( SELECT MAX( n.job_id ) FROM {$wpdb->prefix}icl_translate_job n WHERE n.rid = j.rid )
				  ORDER BY j.job_id",
				array_merge( [ $afterId, $throughId, self::ELEMENT_TYPE ], Process::IN_FLIGHT_STATUSES )
			)
		);

		return (array) $rows;
	}

	private function retire( array $rows, array &$state ) {
		$previousState   = PreviousStateServiceFactory::create();
		$withSnapshot    = array_filter( $rows, fn( $row ) => (bool) $row->has_snapshot );
		$withoutSnapshot = array_filter( $rows, fn( $row ) => ! $row->has_snapshot );
		$ridsOf          = fn( array $subset ) => array_map( fn( $row ) => (int) $row->rid, array_values( $subset ) );

		$retired = $previousState->revertInFlightWithoutSnapshot(
			$ridsOf( $withoutSnapshot ),
			Process::IN_FLIGHT_STATUSES,
			TranslationStatus::NOT_TRANSLATED
		);
		$restored = $previousState->revertInFlight(
			$ridsOf( $withSnapshot ),
			Process::IN_FLIGHT_STATUSES,
			TranslationStatus::NOT_TRANSLATED
		);

		$toComplete = count( array_filter( $withoutSnapshot, fn( $row ) => (bool) $row->has_element ) )
			+ count( array_filter( $restored, fn( $status ) => TranslationStatus::NOT_TRANSLATED !== $status ) );

		$state['retired']           += $retired + count( $restored );
		$state['to_complete']       += $toComplete;
		$state['to_not_translated'] += count( $rows ) - $toComplete;
		$state['with_snapshot']     += count( $withSnapshot );

		JobLog::add(
			'tea_variation_jobs_retired',
			[
				'pass'                    => $state['passes'],
				'from_job_id'             => (int) reset( $rows )->job_id,
				'to_job_id'               => (int) end( $rows )->job_id,
				'job_ids'                 => array_map( fn( $row ) => (int) $row->job_id, $rows ),
				'ate_job_ids_left_at_ate' => count( array_filter( $rows, fn( $row ) => (bool) $row->ate_job_id ) ),
				'to_complete'             => $toComplete,
				'to_not_translated'       => count( $rows ) - $toComplete,
				'with_snapshot'           => count( $withSnapshot ),
			]
		);
	}

	private function finish( array $state ) {
		delete_option( self::STATE_OPTION );

		unset( $state['cursor'] );
		$state['finished'] = time();
		update_option( self::RESULT_OPTION, $state, false );

		JobLog::add( 'tea_variation_jobs_retire_finished', $state );
	}
}
