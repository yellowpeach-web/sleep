<?php

namespace WPML\WPSEO\RankMathSEO\Terms\Meta;

class Hooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_AJAX_Action, \IWPML_REST_Action {

	const TRANSLATABLE_TERM_META = [
		'rank_math_title',
		'rank_math_description',
		'rank_math_focus_keyword',
		'rank_math_facebook_title',
		'rank_math_facebook_description',
		'rank_math_twitter_title',
		'rank_math_twitter_description',
		'rank_math_breadcrumb_title',
	];

	public function add_hooks() {
		add_filter( 'wpml_translatable_term_meta', [ $this, 'addTermMetaKeys' ] );
	}

	public function addTermMetaKeys( $keys ) {
		return array_merge( is_array( $keys ) ? $keys : [], self::TRANSLATABLE_TERM_META );
	}
}
