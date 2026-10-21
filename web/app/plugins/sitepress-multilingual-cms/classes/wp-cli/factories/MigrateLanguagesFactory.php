<?php
namespace WPML\CLI\Core\Commands;

class MigrateLanguagesFactory implements IWPML_Core {

	public function create() {
		global $wpdb;

		return new MigrateLanguages( new \WPML_Upgrade_Schema( $wpdb ) );
	}
}
