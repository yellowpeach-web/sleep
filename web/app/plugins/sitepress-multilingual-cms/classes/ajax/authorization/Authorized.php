<?php

namespace WPML\Ajax\Authorization;

use WPML\Collect\Support\Collection;

interface Authorized {

	public function authorize( Collection $data );
}
