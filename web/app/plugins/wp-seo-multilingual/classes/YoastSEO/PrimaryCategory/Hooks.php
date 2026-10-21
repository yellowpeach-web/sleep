<?php

namespace WPML\WPSEO\YoastSEO\PrimaryCategory;

use WPML\WPSEO\Shared\PrimaryCategory\BaseHooks;

class Hooks extends BaseHooks {

	const PRIMARY_TERM_META_KEY_PREFIX = '_yoast_wpseo_primary_';

	protected function getPrimaryTermMetaKeyPrefix() {
		return self::PRIMARY_TERM_META_KEY_PREFIX;
	}
}
