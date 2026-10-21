<?php

namespace WPML\TM\ATE\Review;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class PreviewRecursionGuard {

	const OPEN_PRIORITY  = PHP_INT_MIN;
	const CLOSE_PRIORITY = PHP_INT_MAX;

	private $rendering = [];

	private $frames = [];

	public function addHooks() {
		Hooks::onFilter( 'the_content', self::OPEN_PRIORITY )->then( spreadArgs( [ $this, 'open' ] ) );
		Hooks::onFilter( 'the_content', self::CLOSE_PRIORITY )->then( spreadArgs( [ $this, 'close' ] ) );
	}

	public function open( $content ) {
		$postId = (int) get_the_ID();

		if ( $postId && in_array( $postId, $this->rendering, true ) ) {
			$this->frames[] = 0;

			return self::notice();
		}

		if ( $postId ) {
			$this->rendering[] = $postId;
		}
		$this->frames[] = $postId;

		return $content;
	}

	public function close( $content ) {
		if ( $this->frames && array_pop( $this->frames ) ) {
			array_pop( $this->rendering );
		}

		return $content;
	}

	public static function notice() {
		return sprintf(
			'<div class="wpml-review__inline-notice" role="note"><p class="wpml-review__inline-notice-title">%s</p><p class="wpml-review__inline-notice-text">%s</p></div>',
			/* translators: Title of the notice shown in place of a layout element that would render the post being previewed inside itself, on the front-end translation review page. */
			esc_html__( 'Content not available here', 'sitepress' ),
			/* translators: Body of that notice. The element normally shows the content of whatever page the layout is applied to; in a preview of the layout itself there is no such page. */
			esc_html__( 'This element shows the content of the post you are previewing. It cannot show that content inside itself, so it is empty here. The translation is not affected.', 'sitepress' )
		);
	}
}
