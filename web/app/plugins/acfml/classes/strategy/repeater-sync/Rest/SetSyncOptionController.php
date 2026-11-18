<?php

namespace ACFML\Repeater\Sync\Rest;

use ACFML\Repeater\Sync\CheckboxOption;
use WPML\LIB\WP\Hooks;

class SetSyncOptionController implements \IWPML_Action {

	const REST_NAMESPACE = 'acfml/v1';
	const ROUTE          = '/repeater-sync';

	public function add_hooks() {
		Hooks::onAction( 'rest_api_init' )
			->then( [ $this, 'registerRoute' ] );
	}

	public function registerRoute() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'setChoice' ],
				'permission_callback' => [ $this, 'canEditTheOriginal' ],
				'args'                => [
					'trid'    => [
						'required'    => true,
						'type'        => 'integer',
						'minimum'     => 1,
						/* translators: Description of a REST API argument. "trid" is WPML's translation group ID; keep it as is. */
						'description' => __( 'The translation group (trid) the original post belongs to.', 'acfml' ),
					],
					'enabled' => [
						'required'    => true,
						'type'        => 'boolean',
						/* translators: Description of a REST API argument. "repeater" and "flexible" are ACF field types. */
						'description' => __( 'Whether repeater and flexible sub-fields of the translations keep the order of the default language.', 'acfml' ),
					],
				],
			]
		);
	}

	public function canEditTheOriginal( \WP_REST_Request $request ) {
		$trid       = (int) $request->get_param( 'trid' );
		$originalId = (int) \SitePress::get_original_element_id_by_trid( $trid );

		if ( ! $originalId ) {
			return $this->refusal();
		}

		$postType = get_post_type( $originalId );
		if ( ! $postType ) {
			return $this->refusal();
		}

		$elementType = apply_filters( 'wpml_element_type', $postType );
		$postTrid    = (int) apply_filters( 'wpml_element_trid', null, $originalId, $elementType );
		if ( $postTrid !== $trid ) {
			return $this->refusal();
		}

		if ( ! current_user_can( 'edit_post', $originalId ) ) {
			return $this->refusal();
		}

		return true;
	}

	public function setChoice( \WP_REST_Request $request ) {
		$trid    = (int) $request->get_param( 'trid' );
		$enabled = (bool) $request->get_param( 'enabled' );

		$option = CheckboxOption::get();
		if ( ! is_array( $option ) ) {
			$option = [];
		}
		$option[ $trid ] = $enabled;
		CheckboxOption::update( $option );

		return rest_ensure_response(
			[
				'trid'    => $trid,
				'enabled' => $enabled,
			]
		);
	}

	private function refusal() {
		return new \WP_Error(
			'rest_forbidden',
			/* translators: REST API error shown when the user may not change the synchronisation setting of a post. */
			__( 'Sorry, you are not allowed to change the synchronisation of this post.', 'acfml' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}
}
