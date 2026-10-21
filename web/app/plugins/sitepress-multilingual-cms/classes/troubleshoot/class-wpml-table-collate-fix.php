<?php

class WPML_Table_Collate_Fix implements IWPML_AJAX_Action, IWPML_Backend_Action, IWPML_DIC_Action {

	const AJAX_ACTION = 'fix_tables_collation';

	private $wpdb;

	private $schema;

	public function __construct( wpdb $wpdb, WPML_Upgrade_Schema $schema ) {
		$this->wpdb   = $wpdb;
		$this->schema = $schema;
	}

	public function add_hooks() {
		add_action( 'upgrader_process_complete', array( $this, 'fix_collate' ), PHP_INT_MAX );
	}

	public function fix_collate_ajax() {
		if ( isset( $_POST['nonce'] )
			 && wp_verify_nonce( $_POST['nonce'], self::AJAX_ACTION )
		) {
			$failed_tables = $this->fix_collate();

			if ( $failed_tables ) {
				wp_send_json_error( array( 'failed_tables' => $failed_tables ) );
			} else {
				wp_send_json_success();
			}
		}
	}

	public function fix_collate() {
		$failed_tables = array();

		if ( did_action( 'upgrader_process_complete' ) > 1 ) {
			return $failed_tables;
		}

		$wpdb = $this->wpdb;

		$wp_default_table_data = $wpdb->get_row(
			$wpdb->prepare( 'SHOW TABLE status LIKE %s', $wpdb->posts )
		);

		if ( isset( $wp_default_table_data->Collation ) ) {
			$charset = $this->schema->get_collation_charset( $wp_default_table_data->Collation );

			foreach ( $this->get_all_wpml_tables() as $table ) {
				$table = reset( $table );

				$table_data = $wpdb->get_row(
					$wpdb->prepare( 'SHOW TABLE status LIKE %s', $table )
				);

				if ( isset( $table_data->Collation ) && $table_data->Collation !== $wp_default_table_data->Collation ) {
					$result = $wpdb->query(
						$wpdb->prepare(
							'ALTER TABLE ' . esc_sql( $table ) . ' CONVERT TO CHARACTER SET %s COLLATE %s',
							$charset,
							$wp_default_table_data->Collation
						)
					);

					if ( false === $result ) {
						$failed_tables[ $table ] = $wpdb->last_error;
					}
				}
			}
		}

		return $failed_tables;
	}

	private function get_all_wpml_tables() {
		$wpdb = $this->wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->prefix . 'icl_%'
			),
			ARRAY_A
		);
	}
}
