<?php

namespace WPML\Compatibility\Divi\V5;

class GlobalLayout implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

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
				return '"globalModule":"' . self::getPublishedTranslationId( (int) $matches[1] ) . '"';
			},
			$content
		);
	}

	public static function getPublishedTranslationId( int $moduleId, ?string $language = null ): int {
		$convertedId = self::getTranslationId( $moduleId, $language );

		if ( $convertedId !== $moduleId && 'publish' !== get_post_status( $convertedId ) ) {
			return $moduleId;
		}

		return $convertedId;
	}

	public static function getTranslationId( int $moduleId, ?string $language = null ): int {
		if ( post_type_exists( self::LAYOUT_POST_TYPE ) ) {
			return (int) apply_filters( 'wpml_object_id', $moduleId, self::LAYOUT_POST_TYPE, true, $language );
		}

		$language = $language ?: apply_filters( 'wpml_current_language', null );
		if ( ! apply_filters( 'wpml_is_translated_post_type', false, self::LAYOUT_POST_TYPE ) ) {
			return $moduleId;
		}

		$elementType  = 'post_' . self::LAYOUT_POST_TYPE;
		$trid         = apply_filters( 'wpml_element_trid', null, $moduleId, $elementType );
		$translations = $trid ? (array) apply_filters( 'wpml_get_element_translations', null, $trid, $elementType ) : [];

		return isset( $translations[ $language ]->element_id ) ? (int) $translations[ $language ]->element_id : $moduleId;
	}
}
