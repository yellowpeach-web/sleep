<?php

namespace WPML\WPSEO\RankMathSEO\PrimaryCategory;

use WPML\WPSEO\Shared\PrimaryCategory\BaseHooks;

class Hooks extends BaseHooks {

	const PRIMARY_TERM_META_KEY_PREFIX = 'rank_math_primary_';

	protected function getPrimaryTermMetaKeyPrefix() {
		return self::PRIMARY_TERM_META_KEY_PREFIX;
	}
}
