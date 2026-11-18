<?php

namespace WPML\TranslationPolicy;

use WPML\TranslationPolicy\Migration\PolicyMigration;
use WPML\Upgrade\CommandsStatus;

abstract class LateCutoverStep implements \IWPML_Upgrade_Command, CutoverCommand {

	const PRIORITY = 1000;

	private $status;

	private $sitepress;

	private $migration;

	private $results;

	public function __construct( array $args = [] ) {
		global $sitepress;

		$this->status    = isset( $args[0] ) && $args[0] instanceof CommandsStatus ? $args[0] : new CommandsStatus();
		$this->sitepress = isset( $args[1] ) && $args[1] instanceof \SitePress ? $args[1] : ( $sitepress instanceof \SitePress ? $sitepress : null );
		$this->migration = isset( $args[2] ) && $args[2] instanceof PolicyMigration ? $args[2] : null;
	}

	abstract protected function work(): bool;

	public function run() {
		if ( did_action( 'wp_loaded' ) ) {
			return $this->workAndMark();
		}

		add_action( 'wp_loaded', [ $this, 'runLate' ], self::PRIORITY );
		$this->results = false;

		return false;
	}

	public function runLate() {
		$this->workAndMark();
	}

	private function workAndMark(): bool {
		$this->results = $this->work();
		if ( ! $this->results ) {
			return false;
		}

		$this->status->markAsExecuted( static::class );

		if ( $this->sitepress && ( new MarkCutover( [ $this->sitepress, $this->status, $this->migration ] ) )->run() ) {
			$this->status->markAsExecuted( MarkCutover::class );
		}

		return true;
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
