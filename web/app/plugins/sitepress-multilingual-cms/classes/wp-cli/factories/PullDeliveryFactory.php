<?php
namespace WPML\CLI\Core\Commands;

use function WPML\Container\make;

class PullDeliveryFactory implements IWPML_Core {

	public function create() {
		return make( PullDelivery::class );
	}
}
