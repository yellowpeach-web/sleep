<?php

namespace WPML\Legacy\Component\Translation\Sender\Taxonomy;

class TaxonomyRemoteAteJobIdClaim {

	const OPTION_PREFIX = 'wpml.taxonomy_term_ate_remote_claim_';

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function acquire( int $wpmlJobId, int $remoteJobId ): bool {
		$optionName = $this->optionName( $remoteJobId );
		$owner      = (string) $wpmlJobId;
		$wpdb       = $this->wpdb;

		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				$optionName,
				$owner
			) ?? ''
		);
		if ( 1 === $inserted ) {
			return true;
		}

		$storedOwner = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$optionName
			) ?? ''
		);

		return empty( $wpdb->last_error ) && $owner === $storedOwner;
	}

	public function release( int $wpmlJobId, int $remoteJobId ): bool {
		$optionName = $this->optionName( $remoteJobId );
		$wpdb       = $this->wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				$optionName,
				(string) $wpmlJobId
			) ?? ''
		);
		wp_cache_delete( $optionName, 'options' );

		return false !== $deleted;
	}

	private function optionName( int $remoteJobId ): string {
		return self::OPTION_PREFIX . $remoteJobId;
	}
}
