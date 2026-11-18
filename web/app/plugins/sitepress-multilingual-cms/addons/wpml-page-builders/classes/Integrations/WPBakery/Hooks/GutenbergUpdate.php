<?php

namespace WPML\Compatibility\WPBakery\Hooks;

use WPML\LIB\WP\Hooks;

use function WPML\FP\spreadArgs;

class GutenbergUpdate implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_REST_Action {

	const EDITOR_STATUS_META_KEY        = '_wpb_vc_js_status';
	const ROW_OPENING_TAG               = '[vc_row';
	const GUTENBERG_ELEMENT_OPENING_TAG = '[vc_gutenberg';

	public function add_hooks() {
		Hooks::onFilter( 'wpml_pb_is_post_built_with_shortcodes', 10, 2 )
			->then( spreadArgs( [ self::class, 'isPostBuiltWithShortcodes' ] ) );
	}

	public static function isPostBuiltWithShortcodes( $isBuiltWithShortcodes, $post ) {
		if (
			! self::isWpBakeryPost( $post->ID )
			|| ! self::hasRow( $post->post_content )
			|| self::hasGutenbergElement( $post->post_content )
		) {
			return $isBuiltWithShortcodes;
		}

		return true;
	}

	private static function isWpBakeryPost( int $postId ): bool {
		return 'true' === get_post_meta( $postId, self::EDITOR_STATUS_META_KEY, true );
	}

	private static function hasRow( string $content ): bool {
		return false !== strpos( $content, self::ROW_OPENING_TAG );
	}

	private static function hasGutenbergElement( string $content ): bool {
		return false !== strpos( $content, self::GUTENBERG_ELEMENT_OPENING_TAG );
	}
}
