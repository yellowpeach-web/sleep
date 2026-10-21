<?php

namespace WPML\WPSEO\RankMathSEO\TranslationJob;

use WPML\WPSEO\RankMathSEO\Terms\Meta\Hooks as TermsMetaHooks;
use WPML\WPSEO\Shared\TranslationJob\BaseHooks;

class Hooks extends BaseHooks {

	const OPTION_PREFIX = 'rank-math-';

	const META_KEY_PREFIX = 'rank_math_';

	protected function getFieldPrefix() {
		return 'field-' . self::META_KEY_PREFIX;
	}

	protected function getTopLevelGroup() {
		return [ self::OPTION_PREFIX => 'Rank Math SEO' ];
	}

	protected function getKeyPurposeMap() {
		return [
			'rank_math_title'       => self::PURPOSE_SEO_TITLE,
			'rank_math_description' => self::PURPOSE_SEO_META_DESC,
		];
	}

	protected function getTermJobFieldLabels() {
		return wpml_collect( TermsMetaHooks::TRANSLATABLE_TERM_META )
			->mapWithKeys(
				function ( $metaKey ) {
					return [ $metaKey => substr( $metaKey, strlen( self::META_KEY_PREFIX ) ) ];
				}
			)
			->all();
	}
}
