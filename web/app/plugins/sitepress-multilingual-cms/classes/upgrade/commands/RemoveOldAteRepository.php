<?php

namespace WPML\TM\Upgrade\Commands;

class RemoveOldAteRepository extends MigrateAteRepository {

	public function run() {
		$this->result = $this->addColumnsToJobsTable() && $this->migrateOldRepository();

		if ( $this->result ) {
			delete_option( self::OPTION_NAME_REPO );
		}

		return $this->result;
	}
}
