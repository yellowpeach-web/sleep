<?php

namespace WPML\TM\Upgrade\Commands\SynchronizeSourceIdOfATEJobs;

use WPML\Utils\Pager;
use WPML\TM\Upgrade\Commands\MigrateAteRepository;
use WPML\Collect\Support\Collection;
use WPML\Upgrade\CommandsStatus;
use function WPML\Container\make;

class Command implements \IWPML_Upgrade_Command {

	const CHUNK_SIZE = 1000;

	const REFUSED_MAP_NOTICE_ID = 'wpml-ate-sync-source-id-refused';

	private $repository;

	private $api;

	private $pager;

	private $commandStatus;

	private $result = false;

	public function __construct(
		Repository $repository,
		\WPML_TM_ATE_API $api,
		Pager $pager,
		CommandsStatus $commandStatus
	) {
		$this->repository    = $repository;
		$this->api           = $api;
		$this->pager         = $pager;
		$this->commandStatus = $commandStatus;
	}


	public function run_admin() {
		if ( ! $this->hasBeenMigrateATERepositoryUpgradeRun() ) {
			return false;
		}

		$duplicated = $this->repository->getDuplicatedAteJobIds();
		if ( $duplicated ) {
			$this->reportRefusedMap( $duplicated );

			return $this->result;
		}

		$chunks       = $this->repository->getPairs()->chunk( self::CHUNK_SIZE );
		$this->result = $this->pager->iterate(
			$chunks,
			function ( Collection $pairs ) {
				return $this->api->migrate_source_id( $pairs->toArray() );
			}
		) === 0;

		return $this->result;
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->result;
	}

	private function hasBeenMigrateATERepositoryUpgradeRun() {
		return $this->commandStatus->hasBeenExecuted( MigrateAteRepository::class );
	}

	private function reportRefusedMap( array $duplicated ) {
		$message = sprintf(
			/* translators: %s: the comma-separated list of the job ids in our translation system that local rows disagree about. */
			__(
				'Synchronize local job ids with ATE jobs did not run: the job id(s) %s are each recorded as the newest job of more than one translation, so the job map for our translation system is ambiguous and was not sent. No mapping was changed in our translation system.',
				'sitepress'
			),
			implode( ', ', $duplicated )
		);

		$entry              = \WPML\TM\ATE\Log\Entry::createForType(
			\WPML\TM\ATE\Log\EventsTypes::JOBS_SYNC,
			[ 'duplicated_ate_job_ids' => $duplicated ]
		);
		$entry->description = $message;
		\WPML\TM\ATE\Log\Storage::add( $entry );

		$notices = function_exists( 'wpml_get_admin_notices' ) ? wpml_get_admin_notices() : null;
		if ( $notices && method_exists( $notices, 'add_notice' ) ) {
			$notice = new \WPML_Notice( self::REFUSED_MAP_NOTICE_ID, $message, 'default' );
			$notice->set_css_class_types( [ 'error' ] );
			$notice->add_capability_check( [ 'manage_options', 'wpml_manage_translation_management' ] );
			$notices->add_notice( $notice );
		}
	}
}
