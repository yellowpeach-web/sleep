<?php

namespace WPML\Upgrade\Commands;

use WPML\TM\OwnedPackages\OwnerNeedsUpdate;
use WPML\Upgrade\SchemaFacts;

class FlagOwnersOfStaleRidingPackages implements \IWPML_Upgrade_Command {
	const CURSOR_OPTION = 'wpml_owned_package_status_cursor';
	const BATCH         = 200;

	private $schema;

	private $flagger;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema  = $args[0];
		$this->flagger = $args[1] ?? null;
	}

	public function run_admin() {
		if ( ! $this->schemaReady() ) {
			return false;
		}

		$after  = (int) get_option( self::CURSOR_OPTION, 0 );
		$owners = $this->nextOwners( $after );
		foreach ( $owners as $owner ) {
			$this->flagger()->flagOwner( $owner->owner_kind, (int) $owner->owner_element_id );
		}

		if ( count( $owners ) === self::BATCH ) {
			$last = end( $owners );
			update_option( self::CURSOR_OPTION, (int) $last->owner_key, false );
			return false;
		}

		delete_option( self::CURSOR_OPTION );
		$this->result = true;
		return true;
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

	private function schemaReady() {
		return SchemaFacts::hasTable( 'icl_string_packages' )
			&& SchemaFacts::hasColumn( 'icl_string_packages', 'owner_kind' )
			&& SchemaFacts::hasColumn( 'icl_string_packages', 'owner_element_id' );
	}

	private function nextOwners( $after ) {
		$wpdb = $this->schema->get_wpdb();
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT owner_kind, owner_element_id, MIN(ID) AS owner_key
				FROM {$wpdb->prefix}icl_string_packages
				WHERE owner_kind = 'term' AND owner_element_id > 0
				GROUP BY owner_kind, owner_element_id
				HAVING owner_key > %d
				ORDER BY owner_key
				LIMIT %d",
				(int) $after,
				self::BATCH
			)
		);
	}

	private function flagger() {
		if ( null === $this->flagger ) {
			global $sitepress;
			$this->flagger = new OwnerNeedsUpdate( $sitepress, $this->schema->get_wpdb() );
		}
		return $this->flagger;
	}
}
