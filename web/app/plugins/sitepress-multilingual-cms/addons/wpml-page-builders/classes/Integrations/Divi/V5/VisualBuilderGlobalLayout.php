<?php

namespace WPML\Compatibility\Divi\V5;

class VisualBuilderGlobalLayout implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const GLOBAL_MODULE_PATTERN = '/"globalModule":"(\d+)"/';

	public function add_hooks() {
		add_filter( 'divi_visual_builder_settings_data_post_content', [ $this, 'convertForBuilder' ], 10, 2 );
		add_filter( 'rest_request_before_callbacks', [ $this, 'restoreSavedIds' ], 10, 3 );
	}

	public function convertForBuilder( $content, $postId ) {
		if ( ! $this->hasGlobalLayout( $content ) || ! $postId ) {
			return $content;
		}

		$language = $this->getPostLanguage( (int) $postId );

		return preg_replace_callback(
			self::GLOBAL_MODULE_PATTERN,
			function ( $matches ) use ( $language ) {
				return $this->idAttribute( GlobalLayout::getPublishedTranslationId( (int) $matches[1], $language ) );
			},
			$content
		);
	}

	public function restoreSavedIds( $response, $handler, $request ) {
		if (
			Editor::SYNC_ROUTE !== $request->get_route()
			|| ! in_array( $request->get_method(), [ 'POST', 'PUT', 'PATCH' ], true )
		) {
			return $response;
		}

		$content = $request->get_param( 'content' );
		if ( ! is_array( $content ) || ! $this->hasGlobalLayout( $content['post_content'] ?? null ) ) {
			return $response;
		}

		$postId   = (int) $request->get_param( 'post_id' );
		$restored = $this->restoreIds( $content['post_content'], $postId );

		if ( $restored !== $content['post_content'] ) {
			$content['post_content'] = $restored;
			$request->set_param( 'content', $content );
		}

		return $response;
	}

	private function restoreIds( $content, $postId ) {
		if ( ! $postId ) {
			return $content;
		}

		$saved = get_post_field( 'post_content', $postId );
		if ( ! $this->hasGlobalLayout( $saved ) ) {
			return $content;
		}

		$language = $this->getPostLanguage( $postId );
		$savedIds = [];

		preg_match_all( self::GLOBAL_MODULE_PATTERN, $saved, $matches );
		foreach ( array_unique( array_map( 'intval', $matches[1] ) ) as $savedId ) {
			$convertedId = GlobalLayout::getTranslationId( (int) $savedId, $language );
			if ( $convertedId !== $savedId ) {
				$savedIds[ $convertedId ] = $savedId;
			}
		}

		if ( ! $savedIds ) {
			return $content;
		}

		return preg_replace_callback(
			self::GLOBAL_MODULE_PATTERN,
			function ( $matches ) use ( $savedIds ) {
				$id = (int) $matches[1];

				return $this->idAttribute( $savedIds[ $id ] ?? $id );
			},
			$content
		);
	}

	private function hasGlobalLayout( $content ) {
		return is_string( $content ) && false !== strpos( $content, 'wp:divi/global-layout' );
	}

	private function getPostLanguage( $postId ) {
		return apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $postId,
				'element_type' => get_post_type( $postId ),
			]
		);
	}

	private function idAttribute( $id ) {
		return '"globalModule":"' . (int) $id . '"';
	}
}
