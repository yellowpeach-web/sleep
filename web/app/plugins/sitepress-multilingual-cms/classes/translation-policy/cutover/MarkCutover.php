<?php

namespace WPML\TranslationPolicy;

use WPML\TranslationPolicy\Migration\PolicyMigration;
use WPML\Upgrade\CommandsStatus;

class MarkCutover implements \IWPML_Upgrade_Command, CutoverCommand {

	private $sitepress;

	private $status;

	private $migration;

	private $results;

	public function __construct( array $args ) {
		$this->sitepress = $args[0];
		$this->status    = $args[1] ?? new CommandsStatus();
		$this->migration = $args[2] ?? null;
	}

	public function run(): bool {
		if ( FeatureSwitch::isOn() ) {
			$this->results = true;

			return true;
		}

		if ( $this->waitingSteps() ) {
			$this->results = false;

			return false;
		}

		( $this->migration ?: PolicyMigration::create() )->sync();

		$this->sitepress->save_settings( [ 'translation-management' => [ FeatureSwitch::LATCH_KEY => (string) ICL_SITEPRESS_VERSION ] ] );
		FeatureSwitch::resetRequestCache();

		$this->results = FeatureSwitch::isOn();

		return $this->results;
	}

	public function waitingSteps(): array {
		$added = apply_filters( 'wpml_upgrade_commands', [] );
		$steps = array_values( array_unique( array_merge( AddOnlySet::COMMANDS, CutoverSet::CORE_STEPS, CutoverSet::stepsIn( is_array( $added ) ? $added : [] ) ) ) );

		return array_values(
			array_filter(
				$steps,
				fn( $class ) => ! $this->status->hasBeenExecuted( $class )
			)
		);
	}

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
		return $this->results;
	}
}
