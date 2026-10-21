<?php

namespace WPML\TM\ATE\Release;

class BlockEditorNotice implements \IWPML_Action {

	const SCRIPT_HANDLE = 'wpml-release-notice-block-editor';

	const REST_NAMESPACE = 'wpml/tm/v1';

	const REST_ROUTE = '/release-notice';

	const POLL_DELAYS_MS = [ 1000, 2000, 3000, 5000, 8000 ];


	public function add_hooks() {
		add_action( 'rest_api_init', [ $this, 'registerRoute' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueueScript' ] );
	}


	public function registerRoute() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'readNotice' ],
				'permission_callback' => \WPML\Request\Adapter\Rest::permission(
					\WPML\Request\Policy\Policy::authorize(
						[ $this, 'canReadNotice' ],
						\WPML\Request\Policy\Authenticity::restTransport(),
						'edit_post on the post the notice is queued for (canReadNotice)'
					),
					self::REST_NAMESPACE . self::REST_ROUTE
				),
				'args'                => [
					'post_id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}


	public function canReadNotice( $request ) {
		$postId = $this->postIdOf( $request );

		return $postId > 0 && current_user_can( 'edit_post', $postId );
	}


	public function readNotice( $request ) {
		$postId = $this->postIdOf( $request );

		return $postId > 0 ? ReleaseNotices::consumeUpdatePayload( $postId ) : null;
	}


	private function postIdOf( $request ) {
		if ( ! $request instanceof \WP_REST_Request ) {
			return 0;
		}

		return (int) $request->get_param( 'post_id' );
	}


	public function enqueueScript() {
		$postId = (int) get_the_ID();

		if ( $postId <= 0 ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			WPML_TM_URL . '/res/js/release-notice-block-editor.js',
			[ 'wp-data', 'wp-dom-ready', 'wp-notices', 'wp-edit-post', 'wp-api-fetch' ],
			ICL_SITEPRESS_SCRIPT_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'wpmlReleaseNoticeData',
			[
				'path'       => '/' . self::REST_NAMESPACE . self::REST_ROUTE . '?post_id=' . $postId,
				'postId'     => $postId,
				/* translators: Link text inside a sentence about automatic translation; it opens the page that lists how many words were used. It starts in lower case because it sits inside the sentence. */
				'linkLabel'  => __( 'usage ledger', 'sitepress' ),
				'pollDelays' => self::POLL_DELAYS_MS,
			]
		);
	}

}
