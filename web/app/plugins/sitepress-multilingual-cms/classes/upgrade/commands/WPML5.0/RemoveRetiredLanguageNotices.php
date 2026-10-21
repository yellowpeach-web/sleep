<?php

namespace WPML\Upgrade\Commands;

use WPML\Notices\NoticeStorePurge;
use WPML\WP\OptionManager;

class RemoveRetiredLanguageNotices implements \IWPML_Upgrade_Command {

	const NOTICES = [
		[ 'default', 'language-type-undetermined' ],
		[ 'default', 'orphan-language-codes' ],
		[ 'default', 'language-completion-pending' ],
	];

	const OPTIONS = [
		'wpml_orphan_language_codes_detected',
		'wpml_language_completion_pending_flag',
		'wpml_language_completion_pending',
	];

	private $schema;

	private $options;

	private $results;

	public function __construct( array $args ) {
		$this->schema  = $args[0];
		$this->options = isset( $args[1] ) ? $args[1] : new OptionManager();
	}

	public function run() {
		$notices = wpml_get_admin_notices();

		foreach ( self::NOTICES as list( $group, $id ) ) {
			$notices->remove_notice( $group, $id );
		}

		NoticeStorePurge::removeEntries( $this->schema->get_wpdb(), $this->options, self::NOTICES );

		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}

		$this->results = true;

		return $this->results;
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
		return $this->results;
	}
}
