<?php

namespace WPML\Compatibility\Divi\V5;

class GlobalLayout implements \IWPML_Frontend_Action {

	const LAYOUT_POST_TYPE = 'et_pb_layout';

	const PRIORITY_BEFORE_DIVI_BLOCK_PARSER = 1;

	public function add_hooks() {
		add_filter( 'the_content', [ $this, 'convertGlobalModuleIds' ], self::PRIORITY_BEFORE_DIVI_BLOCK_PARSER );
		add_filter( 'et_builder_render_layout', [ $this, 'convertGlobalModuleIds' ], self::PRIORITY_BEFORE_DIVI_BLOCK_PARSER );
	}

	public function convertGlobalModuleIds( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, 'wp:divi/global-layout' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/"globalModule":"(\d+)"/',
			function ( $matches ) {
				$convertedId = apply_filters( 'wpml_object_id', (int) $matches[1], self::LAYOUT_POST_TYPE, true );

				return '"globalModule":"' . (int) $convertedId . '"';
			},
			$content
		);
	}
}
