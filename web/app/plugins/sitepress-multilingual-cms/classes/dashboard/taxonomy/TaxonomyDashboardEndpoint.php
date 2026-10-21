<?php

namespace WPML\TM\Dashboard\Taxonomy;

use WPML\LIB\WP\User;
use WP_REST_Request;
use WP_REST_Server;

class TaxonomyDashboardEndpoint extends \WPML_REST_Base implements \IWPML_Backend_Action, \IWPML_REST_Action {
	public function __construct() {
		parent::__construct( \WPML_REST_Base::REST_NAMESPACE );
	}

	public function add_hooks() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		$this->register_route(
			'/dashboard/taxonomies',
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ $this, 'getRows' ],
				'args'     => [
					'since' => [
						'type'     => 'string',
						'required' => false,
					],
					'search' => [
						'type'     => 'string',
						'required' => false,
					],
				],
			]
		);

		$this->register_route(
			'/dashboard/taxonomies/rescan-labels',
			[
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'rescanLabels' ],
				'args'     => [
					'taxonomy' => [
						'type'     => 'string',
						'required' => true,
					],
				],
			]
		);
	}

	public function get_allowed_capabilities( WP_REST_Request $request ) {
		return [ User::CAP_ADMINISTRATOR, User::CAP_MANAGE_TRANSLATIONS ];
	}

	public function getRows( WP_REST_Request $request ) {
		$since  = (string) $request->get_param( 'since' );
		$search = trim( (string) $request->get_param( 'search' ) );

		$token = TaxonomyDashboardData::stateToken();

		if ( '' === $search && '' !== $since && $since === $token ) {
			return [
				'unchanged' => true,
				'token'     => $token,
			];
		}

		$data          = ( new TaxonomyDashboardData() )->get( $search, $token );
		$data['token'] = $token;

		return $data;
	}

	public function rescanLabels( WP_REST_Request $request ) {
		$taxonomy = (string) $request->get_param( 'taxonomy' );

		return $this->makeLabelStatus()->rescan( $taxonomy );
	}

	protected function makeLabelStatus(): TaxonomyLabelStatus {
		return new TaxonomyLabelStatus();
	}
}
