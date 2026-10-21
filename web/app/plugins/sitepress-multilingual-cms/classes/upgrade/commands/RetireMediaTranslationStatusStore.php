<?php

namespace WPML\Upgrade\Commands;

class RetireMediaTranslationStatusStore implements \IWPML_Upgrade_Command {

	const META_KEY_PREFIX = '_translation_status_';

	const MEDIA_SETTINGS_OPTION = '_wpml_media';
	const MIGRATION_FLAG        = 'wpml_media_2_3_migration';

	const BATCH_SIZE = 1000;

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		$wpdb = $this->schema->get_wpdb();

		$meta_ids = $this->find_status_rows( $wpdb, $this->batch_size() );

		if ( $meta_ids ) {
			$this->delete_rows( $wpdb, $meta_ids );

			$this->result = false;

			return false;
		}

		$this->remove_migration_flag();

		$this->result = true;

		return true;
	}

	private function batch_size() {
		$size = (int) apply_filters( 'wpml_upgrade_media_translation_status_batch_size', self::BATCH_SIZE );

		return max( 1, $size );
	}

	private function find_status_rows( \wpdb $wpdb, $batch_size ) {
		$meta_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_id
					FROM {$wpdb->postmeta}
					WHERE meta_key LIKE %s
					ORDER BY meta_id
					LIMIT %d",
				$wpdb->esc_like( self::META_KEY_PREFIX ) . '%',
				$batch_size
			)
		);

		return array_values( array_filter( array_map( 'intval', (array) $meta_ids ) ) );
	}

	private function delete_rows( \wpdb $wpdb, array $meta_ids ) {
		$placeholders = implode( ', ', array_fill( 0, count( $meta_ids ), '%d' ) );

		$sql = "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ( {$placeholders} )";

		$wpdb->query( $wpdb->prepare( $sql, $meta_ids ) );
	}

	private function remove_migration_flag() {
		$settings = get_option( self::MEDIA_SETTINGS_OPTION );

		if ( ! is_array( $settings ) || ! array_key_exists( self::MIGRATION_FLAG, $settings ) ) {
			return;
		}

		unset( $settings[ self::MIGRATION_FLAG ] );

		update_option( self::MEDIA_SETTINGS_OPTION, $settings );
	}

	public function run_admin() {
		return $this->run();
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
}
