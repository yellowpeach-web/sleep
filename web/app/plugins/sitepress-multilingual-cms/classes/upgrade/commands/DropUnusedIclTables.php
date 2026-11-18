<?php

namespace WPML\Upgrade\Commands;

class DropUnusedIclTables implements \IWPML_Upgrade_Command {

	private $wpdb;

	private $result = false;

	public function __construct( array $args ) {
		$this->wpdb = $args[0];
	}

	public function run_admin() {
		$wpdb = $this->wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}icl_core_status" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}icl_string_status" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}icl_message_status" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}icl_content_status" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}icl_node" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}icl_reminders" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}icl_string_pages" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}icl_string_urls" );

		$this->result = true;

		return $this->result;
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
