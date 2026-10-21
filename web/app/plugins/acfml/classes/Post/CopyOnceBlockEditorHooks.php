<?php

namespace ACFML\Post;

class CopyOnceBlockEditorHooks implements \IWPML_REST_Action, \IWPML_DIC_Action {

	private $acfForm;

	private $savedPostId;

	public function __construct( BlockEditorAcfForm $acfForm ) {
		$this->acfForm = $acfForm;
	}

	public function add_hooks() {
		add_filter( 'rest_request_before_callbacks', [ $this, 'rememberSavedPost' ], 10, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'forgetSavedPost' ], 10, 3 );
		add_filter( 'wpml_custom_field_values', [ $this, 'postponeAcfCopyOnce' ], 10, 2 );
	}

	public function rememberSavedPost( $response, $handler, $request ) {
		if ( $this->isPostUpdate( $handler, $request ) && $this->acfForm->isSentFromEditScreen() ) {
			$this->savedPostId = (int) $request->get_param( 'id' );
		}

		return $response;
	}

	public function forgetSavedPost( $response, $handler, $request ) {
		if ( $this->isPostUpdate( $handler, $request ) ) {
			$this->savedPostId = null;
		}

		return $response;
	}

	private function isPostUpdate( array $handler, $request ): bool {
		$callback = $handler['callback'] ?? null;

		return $request instanceof \WP_REST_Request
			&& $request->get_param( 'id' )
			&& is_array( $callback )
			&& ( $callback[0] ?? null ) instanceof \WP_REST_Posts_Controller
			&& 'update_item' === ( $callback[1] ?? null );
	}

	public function postponeAcfCopyOnce( $values, array $args ) {
		if ( ! $this->savedPostId || ! $this->isMissingCopyOnceValue( $values, $args ) ) {
			return $values;
		}

		$postId = (int) $args['post_id'];

		if ( $postId === $this->savedPostId
			&& $this->acfForm->ownsField( $postId, (string) $args['meta_key'] )
			&& $this->acfForm->isShownOn( $postId )
		) {
			return [ '' ];
		}

		return $values;
	}

	private function isMissingCopyOnceValue( $values, array $args ): bool {
		return empty( $values )
			&& isset( $args['post_id'], $args['meta_key'], $args['custom_fields_translation'] )
			&& WPML_COPY_ONCE_CUSTOM_FIELD === (int) $args['custom_fields_translation'];
	}
}
