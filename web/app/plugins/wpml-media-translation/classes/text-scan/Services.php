<?php

namespace WPML\Media\TextScan;

class Services {

	private static $instance;

	private $candidates;

	private $verdictStore;

	private $outdatedMarker;

	private $scanProgress;

	private $scanQueue;

	private $singleImage;

	private $restRoutes;

	private $hooks;

	public static function instance() {
		if ( ! Feature::isEnabled() ) {
			return null;
		}

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		global $wpdb, $sitepress;

		$clock = function () {
			return microtime( true );
		};

		$candidates = new Candidates( $sitepress, $wpdb );
		$gate       = new MetaGate();
		$keys       = new ContentKey( $clock );
		$thumbnail  = new Thumbnail();
		$store      = new VerdictStore( $candidates, $clock );
		$outdated   = new OutdatedMarker( $candidates, $clock );
		$client     = new HttpClassifierClient();
		$progress   = new ScanProgress();

		$queue = new ScanQueue(
			$wpdb,
			$candidates,
			$gate,
			$keys,
			$store,
			$thumbnail,
			$client,
			new ScanLock( $wpdb, $clock ),
			$progress,
			$clock
		);

		$single = new SingleImage( $candidates, $gate, $keys, $store, $thumbnail, $client );
		$routes = new RestRoutes( $queue, $single );

		$this->candidates     = $candidates;
		$this->verdictStore   = $store;
		$this->outdatedMarker = $outdated;
		$this->scanProgress   = $progress;
		$this->scanQueue      = $queue;
		$this->singleImage    = $single;
		$this->restRoutes     = $routes;
		$this->hooks          = new Hooks( $candidates, $gate, $keys, $store, $outdated, $queue, $routes );
	}

	public function candidates() {
		return $this->candidates;
	}

	public function verdictStore() {
		return $this->verdictStore;
	}

	public function outdatedMarker() {
		return $this->outdatedMarker;
	}

	public function scanProgress() {
		return $this->scanProgress;
	}

	public function scanQueue() {
		return $this->scanQueue;
	}

	public function singleImage() {
		return $this->singleImage;
	}

	public function restRoutes() {
		return $this->restRoutes;
	}

	public function hooks() {
		return $this->hooks;
	}
}
