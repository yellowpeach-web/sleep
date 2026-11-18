<?php
namespace WPML\CLI\Core\Commands;

use WPML\Knowledge\AppearanceRepository;
use WPML\Knowledge\AppearanceService;

class AppearanceFactory implements IWPML_Core {

	public function create() {
		return new Appearance( new AppearanceService( new AppearanceRepository() ) );
	}
}
