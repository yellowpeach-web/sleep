<?php

namespace WPML\Compatibility\Divi\Hooks;

class UnusedThemeBuilderLayouts implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const META_KEY = '_et_theme_builder_marked_as_unused';

	const POST_TYPES = [
		'et_template',
		'et_header_layout',
		'et_body_layout',
		'et_footer_layout',
	];

	public function add_hooks() {
		add_filter( 'wpml_tm_dashboard_exclude_posts_with_meta_keys', [ $this, 'excludeFromDashboard' ], 10, 2 );
		add_filter( 'wpml_exclude_post_from_auto_translate', [ $this, 'excludeFromAutomaticTranslation' ], 10, 2 );
	}

	public function excludeFromDashboard( $metaKeys, $postType ) {
		if ( is_array( $metaKeys ) && in_array( $postType, self::POST_TYPES, true ) ) {
			$metaKeys[] = self::META_KEY;
		}

		return $metaKeys;
	}

	public function excludeFromAutomaticTranslation( $excluded, $postId ) {
		if ( $excluded || ! is_numeric( $postId ) ) {
			return $excluded;
		}

		$postId = (int) $postId;

		if ( ! in_array( get_post_type( $postId ), self::POST_TYPES, true ) ) {
			return $excluded;
		}

		$mark = get_post_meta( $postId, self::META_KEY, true );

		return '' !== (string) $mark && '0' !== (string) $mark ? true : $excluded;
	}
}
