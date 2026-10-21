<?php

namespace WPML\Upgrade\Commands;

use WPML\User\CapabilityIndex;

class BackfillCapabilityIndex implements \IWPML_Upgrade_Command {

	const POSITION_OPTION = 'wpml_capability_index_backfill_position';

	const WINDOW_SIZE = 10000;

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public static function resetPosition() {
		delete_option( self::POSITION_OPTION );
	}

	public function run() {
		$wpdb = $this->schema->get_wpdb();

		$position = (int) get_option( self::POSITION_OPTION, 0 );
		$last_id = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->users}" );

		if ( $position >= $last_id ) {
			self::resetPosition();
			$this->result = true;

			return true;
		}

		$window_end = $this->windowEnd( $wpdb, $position, $last_id );

		$this->reconcileWindow( $wpdb, $position, $window_end );

		$this->result = $window_end >= $last_id;

		if ( $this->result ) {
			self::resetPosition();
		} else {
			update_option( self::POSITION_OPTION, $window_end, false );
		}

		return $this->result;
	}

	private function windowSize() {
		$size = (int) apply_filters( 'wpml_capability_index_backfill_window', self::WINDOW_SIZE );

		return max( 1, $size );
	}

	private function reconcileWindow( \wpdb $wpdb, $after_id, $through_id ) {
		$prefix            = $wpdb->prefix;
		$capabilities_key  = CapabilityIndex::capabilitiesMetaKey( $prefix );
		$index_keys        = [];
		$capability_by_key = [];
		$prefilter_values  = [];

		foreach ( CapabilityIndex::capabilities() as $capability ) {
			$key                       = CapabilityIndex::metaKey( $capability, $prefix );
			$index_keys[]              = $key;
			$capability_by_key[ $key ] = $capability;
			$prefilter_values[] = '%' . $wpdb->esc_like( $capability ) . '%';
		}

		$prefilter   = implode( ' OR ', array_fill( 0, count( $prefilter_values ), 'meta_value LIKE %s' ) );
		$index_slots = implode( ', ', array_fill( 0, count( $index_keys ), '%s' ) );

		$sql = "SELECT user_id, meta_key, meta_value
			FROM {$wpdb->usermeta}
			WHERE user_id > %d AND user_id <= %d
			  AND (
			      ( meta_key = %s AND ( {$prefilter} ) )
			      OR meta_key IN ( {$index_slots} )
			  )";

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				array_merge( [ $after_id, $through_id, $capabilities_key ], $prefilter_values, $index_keys )
			)
		);

		$wanted = [];
		$stored = [];

		foreach ( (array) $rows as $row ) {
			$user_id = (int) $row->user_id;

			if ( $capabilities_key === $row->meta_key ) {
				$capabilities = maybe_unserialize( $row->meta_value );
				$capabilities = is_array( $capabilities ) ? $capabilities : [];

				foreach ( CapabilityIndex::capabilities() as $capability ) {
					if ( CapabilityIndex::grants( $capabilities, $capability ) ) {
						$wanted[ $user_id ][ $capability ] = true;
					}
				}
			} elseif ( isset( $capability_by_key[ $row->meta_key ] ) ) {
				$stored[ $user_id ][ $capability_by_key[ $row->meta_key ] ] = true;
			}
		}

		foreach ( $wanted as $user_id => $capabilities ) {
			foreach ( array_keys( $capabilities ) as $capability ) {
				if ( ! isset( $stored[ $user_id ][ $capability ] ) ) {
					update_user_meta( $user_id, CapabilityIndex::metaKey( $capability, $prefix ), CapabilityIndex::META_VALUE );
				}
			}
		}

		foreach ( $stored as $user_id => $capabilities ) {
			foreach ( array_keys( $capabilities ) as $capability ) {
				if ( ! isset( $wanted[ $user_id ][ $capability ] ) ) {
					delete_user_meta( $user_id, CapabilityIndex::metaKey( $capability, $prefix ) );
				}
			}
		}
	}

	private function windowEnd( \wpdb $wpdb, $after_id, $last_id ) {
		$page_end = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(ID) FROM ( SELECT ID FROM {$wpdb->users} WHERE ID > %d ORDER BY ID LIMIT %d ) AS page",
				$after_id,
				$this->windowSize()
			)
		);

		return $page_end > 0 ? min( $page_end, $last_id ) : $last_id;
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
		return $this->result;
	}
}
