<?php

namespace WPML\Media\TextScan;

class ScanProgress {

	const OPTION = '_wpml_media_text_scan_progress';

	const IDLE    = 'idle';
	const RUNNING = 'running';
	const PAUSED  = 'paused';
	const DONE    = 'done';

	public static function defaults() {
		return [
			'status'       => self::IDLE,
			'total'        => 0,
			'processed'    => 0,
			'held'         => 0,
			'started_at'   => null,
			'finished_at'  => null,
			'error_count'  => 0,
			'retry_at'     => null,
			'last_error'   => null,
			'blocked_host' => null,
		];
	}

	public function get() {
		$stored   = get_option( self::OPTION );
		$defaults = self::defaults();

		if ( ! is_array( $stored ) ) {
			return $defaults;
		}

		return array_merge( $defaults, array_intersect_key( $stored, $defaults ) );
	}

	public function fresh() {
		wp_cache_delete( self::OPTION, 'options' );
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ self::OPTION ] ) ) {
			unset( $notoptions[ self::OPTION ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}

		return $this->get();
	}

	public function put( array $progress ) {
		update_option( self::OPTION, $progress, false );
	}

	public function startNew( $total, $now ) {
		$total = max( 0, (int) $total );
		$now   = (int) $now;

		$progress                = self::defaults();
		$progress['status']      = $total > 0 ? self::RUNNING : self::DONE;
		$progress['total']       = $total;
		$progress['started_at']  = $now;
		$progress['finished_at'] = $total > 0 ? null : $now;

		$this->put( $progress );

		return $progress;
	}
}
