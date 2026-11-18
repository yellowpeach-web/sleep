<?php

namespace WPML\TranslationPolicy\Panel;

use WPML\Element\API\Translations;
use WPML\LIB\WP\User;
use WPML\Request\Adapter\Rest;
use WPML\Request\Policy\Authenticity;
use WPML\Request\Policy\Policy;
use WPML\TranslationPolicy\Policy\PolicyTable;
use WPML\TranslationPolicy\Ptc\PtcConfirm;
use WPML\TranslationPolicy\Settings\LockedChoice;

class SaveEndpoint {

	const REST_NAMESPACE = 'wpml/v1';

	const ROUTE = '/posts/(?P<id>\d+)/translation-policy';

	private $saveChoice;

	private $panelData;

	public function __construct( callable $saveChoice, callable $panelData ) {
		$this->saveChoice = $saveChoice;
		$this->panelData  = $panelData;
	}

	public static function readPanel( int $postId ) {
		return rest_do_request( new \WP_REST_Request( 'GET', '/' . self::REST_NAMESPACE . '/posts/' . $postId . '/language-panel' ) );
	}

	public static function path( int $postId ): string {
		return '/' . self::REST_NAMESPACE . '/posts/' . $postId . '/translation-policy';
	}

	public function registerRoute() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => Rest::permission(
					Policy::authorize( [ self::class, 'mayChange' ], Authenticity::restTransport(), 'a translation manager, or edit_post on the addressed post for this post only' ),
					self::REST_NAMESPACE . self::ROUTE
				),
				'args'                => [
					'id'     => [ 'type' => 'integer' ],
					'editor' => [ 'type' => 'string' ],
					'method' => [ 'type' => 'string' ],
					'reset'  => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
					'scope'  => [
						'type' => 'string',
						'enum' => [ PolicyTable::SCOPE_POST, PolicyTable::SCOPE_TYPE ],
					],
					'confirm_ptc' => [ 'type' => 'boolean' ],
				],
			]
		);
	}

	public static function mayChange( $request = null ): bool {
		$postId = $request instanceof \WP_REST_Request ? (int) $request->get_param( 'id' ) : 0;
		if ( $postId <= 0 ) {
			return false;
		}

		if ( User::canManageTranslations() ) {
			return true;
		}

		return PolicyTable::SCOPE_TYPE !== $request->get_param( 'scope' ) && current_user_can( 'edit_post', $postId );
	}

	public function handle( \WP_REST_Request $request ) {
		$postId   = (int) $request->get_param( 'id' );
		$postType = (string) get_post_type( $postId );

		if ( '' === $postType || (int) Translations::getOriginalId( $postId, 'post_' . $postType ) !== $postId ) {
			return new \WP_Error( 'wpml_translation_policy_not_original', Words::notSaved(), [ 'status' => 400 ] );
		}

		$changes = [];
		foreach ( [ 'editor', 'method' ] as $column ) {
			if ( null !== $request->get_param( $column ) ) {
				$changes[ $column ] = (string) $request->get_param( $column );
			}
		}
		$reset = $request->get_param( 'reset' );
		$scope = $request->get_param( 'scope' );

		try {
			call_user_func( $this->saveChoice )->save(
				$postId,
				$postType,
				$changes,
				is_array( $reset ) ? array_map( 'strval', $reset ) : [],
				null === $scope ? PolicyTable::SCOPE_POST : (string) $scope
			);
		} catch ( LockedChoice $e ) {
			return new \WP_Error( 'wpml_translation_policy_locked', wp_specialchars_decode( $e->getMessage(), ENT_QUOTES ), [ 'status' => 409 ] );
		} catch ( \InvalidArgumentException | \RuntimeException $e ) {
			return new \WP_Error( 'wpml_translation_policy_not_saved', Words::notSaved(), [ 'status' => 400 ] );
		}

		if ( true === $request->get_param( 'confirm_ptc' ) && PolicyTable::METHOD_PTC === ( $changes['method'] ?? null ) ) {
			PtcConfirm::markConfirmed( get_current_user_id() );
		}

		return call_user_func( $this->panelData, $postId );
	}
}
