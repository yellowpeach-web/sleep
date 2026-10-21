<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\PostAddSetup;
use WPML\Localization\LanguagePacksOnVersionBump;
use WPML\Notices\NoticeStorePurge;
use WPML\WP\OptionManager;

class RefreshMissingLanguagePacksNotice implements \IWPML_Upgrade_Command {

	const NOTICES = [
		[ \WPML_Languages_Notices::NOTICE_GROUP, \WPML_Languages_Notices::NOTICE_ID_MISSING_DOWNLOADED_LANGUAGES ],
	];

	private $schema;

	private $options;

	private $defer;

	private $results;

	public function __construct( array $args ) {
		$this->schema  = $args[0];
		$this->options = isset( $args[1] ) ? $args[1] : new OptionManager();
		$this->defer   = isset( $args[2] ) ? $args[2] : null;
	}

	public function run() {
		$notices = wpml_get_admin_notices();

		foreach ( self::NOTICES as list( $group, $id ) ) {
			$notices->remove_notice( $group, $id );
		}

		$purged = NoticeStorePurge::removeEntries( $this->schema->get_wpdb(), $this->options, self::NOTICES );

		if ( $purged ) {
			$this->schedule_re_evaluation();
		}

		$this->results = true;

		return $this->results;
	}

	private function schedule_re_evaluation() {
		LanguagePacksOnVersionBump::queueForActiveLanguages( $this->defer );
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
