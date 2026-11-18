<?php

namespace WPML\PB\Cornerstone\Media\Modules;

class BackgroundNode extends \WPML_Cornerstone_Media_Node_With_URLs {

	const KEYS = [
		'bg_lower_image',
		'bg_upper_image',
		'bg_lower_img_src',
		'bg_upper_img_src',
	];

	protected function get_keys() {
		return self::KEYS;
	}
}
