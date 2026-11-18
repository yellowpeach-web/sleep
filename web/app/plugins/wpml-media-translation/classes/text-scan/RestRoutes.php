<?php

namespace WPML\Media\TextScan;

use WPML\TM\Jobs\EditAccess\AutomaticTranslationPermission;

class RestRoutes extends \WPML_REST_Base {

	const ROUTE_BASE = '/media/text-scan/';

	const CAPABILITIES = [ 'wpml_manage_media_translation', 'manage_translations' ];

	private $queue;

	private $single;

	public function __construct( ScanQueue $queue, SingleImage $single ) {
		parent::__construct();
		$this->queue  = $queue;
		$this->single = $single;
	}

	public function add_hooks() {
		add_action( 'rest_api_init', [ $this, 'registerRoutes' ] );
	}

	public function registerRoutes() {
		$this->register_route(
			self::ROUTE_BASE . 'status',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'getStatus' ],
			]
		);
		$this->register_route(
			self::ROUTE_BASE . 'start',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'postStart' ],
			]
		);
		$this->register_route(
			self::ROUTE_BASE . 'tick',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'postTick' ],
				'args'     => [
					'force' => [
						'type'    => 'boolean',
						'default' => false,
					],
				],
			]
		);
		$this->register_route(
			self::ROUTE_BASE . 'classify-one',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'postClassifyOne' ],
				'args'     => [
					'id' => [
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					],
				],
			]
		);
		$this->register_route(
			self::ROUTE_BASE . 'settings',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'getSettings' ],
			]
		);
		$this->register_route(
			self::ROUTE_BASE . 'settings',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'postSettings' ],
			]
		);
	}

	public function get_allowed_capabilities( \WP_REST_Request $request ) {
		return self::CAPABILITIES;
	}

	public function getStatus() {
		return $this->queue->status();
	}

	public function postStart( \WP_REST_Request $request ) {
		$denied = $this->automaticTranslationDenied();
		if ( $denied ) {
			return $denied;
		}

		$scope = $this->scopeOf( $request );
		if ( null === $scope ) {
			return new \WP_Error(
				'wpml_text_scan_invalid_scope',
				__( 'The scan scope must be all images or a list of image ids.', 'wpml-media' ),
				[ 'status' => 400 ]
			);
		}

		return $this->queue->start( $scope );
	}

	public function postTick( \WP_REST_Request $request ) {
		$denied = $this->automaticTranslationDenied();
		if ( $denied ) {
			return $denied;
		}

		return $this->queue->tick( ScanQueue::DEFAULT_BUDGET, true === $request->get_param( 'force' ) );
	}

	public function postClassifyOne( \WP_REST_Request $request ) {
		$denied = $this->automaticTranslationDenied();
		if ( $denied ) {
			return $denied;
		}

		return $this->single->classify( (int) $request->get_param( 'id' ) );
	}

	public function getSettings() {
		return [ 'mode' => Mode::get() ];
	}

	public function postSettings( \WP_REST_Request $request ) {
		$denied = $this->automaticTranslationDenied();
		if ( $denied ) {
			return $denied;
		}

		$mode = $request->get_param( 'mode' );
		if ( ! is_string( $mode ) || ! Mode::isValid( $mode ) ) {
			return new \WP_Error(
				'wpml_text_scan_invalid_mode',
				__( 'The image scan setting must be off, likely or all.', 'wpml-media' ),
				[ 'status' => 400 ]
			);
		}

		Mode::set( $mode );
		$this->queue->resumeIfPaused();

		return [ 'mode' => Mode::get() ];
	}

	private function automaticTranslationDenied() {
		if ( AutomaticTranslationPermission::isAllowedForCurrentUser() ) {
			return null;
		}

		return new \WP_Error(
			'wpml_text_scan_not_allowed',
			__( 'You are not allowed to use automatic translation.', 'wpml-media' ),
			[ 'status' => 403 ]
		);
	}

	private function scopeOf( \WP_REST_Request $request ) {
		$scope = $request->get_param( 'scope' );

		if ( 'all' === $scope ) {
			return 'all';
		}

		if ( 'ids' !== $scope ) {
			return null;
		}

		$ids = $request->get_param( 'ids' );
		if ( ! is_array( $ids ) || [] === $ids ) {
			return null;
		}

		$list = [];
		foreach ( $ids as $id ) {
			if ( is_int( $id ) && $id > 0 ) {
				$list[] = $id;
			} elseif ( is_string( $id ) && ctype_digit( $id ) && (int) $id > 0 ) {
				$list[] = (int) $id;
			} else {
				return null;
			}
		}

		return array_values( array_unique( $list ) );
	}
}
