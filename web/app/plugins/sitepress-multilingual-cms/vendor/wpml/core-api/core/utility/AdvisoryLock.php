<?php

namespace WPML\Utilities;

class AdvisoryLock {

	private $wpdb;

	private $name;

	private $ttlSeconds;

	private $heldIn = null;

	public function __construct( \wpdb $wpdb, string $name, int $ttlSeconds = 0 ) {
		$this->wpdb       = $wpdb;
		$this->name       = 'wpml_' . md5( $wpdb->dbname . '|' . $wpdb->prefix . '|' . $name );
		$this->ttlSeconds = $ttlSeconds;
	}

	public function acquire( int $timeoutSeconds ): bool {
		$backend = new LockBackend( $this->wpdb );
		$named   = $backend->getNamedLock( $this->name, $timeoutSeconds );

		if ( true === $named ) {
			$this->heldIn = LockBackend::MYSQL;

			return true;
		}

		if ( null === $named
			&& LockBackend::TABLE === $backend->backend()
			&& $backend->acquireRow( $this->name, $timeoutSeconds, $this->ttlSeconds ?: null )
		) {
			$this->heldIn = LockBackend::TABLE;

			return true;
		}

		return false;
	}

	public function release(): void {
		$backend = new LockBackend( $this->wpdb );

		if ( LockBackend::TABLE === $this->heldIn ) {
			$backend->releaseRow( $this->name );
		} else {
			$backend->releaseNamedLock( $this->name );
		}

		$this->heldIn = null;
	}
}
