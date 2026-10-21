<?php

namespace WPML\TM\ATE;

use WPML\LIB\WP\User;
use WPML\Remote\TrustedDestinations;

class Proxy extends \WPML_REST_Base {
	private $endpoints;

	public function __construct( \WPML_TM_ATE_AMS_Endpoints $endpoints ) {
		parent::__construct( 'wpml/ate/v1' );

		$this->endpoints = $endpoints;
	}

	public function add_hooks() {
		$this->register_routes();
	}

	public function register_routes() {
		parent::register_route(
			'/ate/proxy',
			array(
				'methods'  => \WP_REST_Server::ALLMETHODS,
				'callback' => [ $this, 'proxy' ],
			)
		);
	}

	private function get_args( \WP_REST_Request $request ) {
		$request_params = $this->get_request_params( $request );

		$url          = $request_params['url'];
		$headers      = $request_params['headers'];
		$query        = $request_params['query'];
		$content_type = $request_params['content_type'];

		if ( $content_type ) {
			if ( ! $headers ) {
				$headers = [];
			}
			$headers[] = 'Content-Type: ' . $content_type;
		}

		$args = [
			'method'  => $request_params['method'],
			'headers' => $headers,
		];

		$body = $request_params['body'];
		if ( $body ) {
			$args['body'] = $body;
		}

		return [ $url, $query, $args, $content_type ];
	}

	private function validate_request( \WP_REST_Request $request ) {
		if ( ! $this->get_request_params( $request ) ) {
			return new \WP_Error( 'endpoint_without_parameters', 'Endpoint called with no parameters.', [ 'status' => 400 ] );
		}

		list( $url, , $args ) = $this->get_args( $request );

		$has_all_required_parameters = $url && $args['method'] && $args['headers'];
		if ( ! $has_all_required_parameters ) {
			return new \WP_Error( 'missing_required_parameters', 'Required parameters missing.', [ 'status' => 400 ] );
		}

		if ( \strtolower( $request->get_method() ) !== \strtolower( $args['method'] ) ) {
			return new \WP_Error( 'invalid_method', 'Invalid method.', [ 'status' => 400 ] );
		}

		if ( ! $this->destinations()->isTrusted( $url ) ) {
			return new \WP_Error( 'invalid_url', 'Invalid URL.', [ 'status' => 400 ] );
		}

		$lock = \WPML\Container\make( ClonedSites\ApiCommunication::class )->checkCloneSiteLock( $url );
		if ( \is_wp_error( $lock ) ) {
			return $lock;
		}

		return true;
	}

	public function proxy( \WP_REST_Request $request ) {
		list( $url, $params, $args, $content_type ) = $this->get_args( $request );

		$status_code = 200;

		$validation = $this->validate_request( $request );
		if ( \is_wp_error( $validation ) ) {
			$status_code   = $validation->get_error_data()['status'];
			$response_body = '';
		} else {

			if ( \is_array( $params ) ) {
				$params = \wpml_http_build_query( $params );
			}

			$endpoint = \http_build_url( $url, [ 'query' => $params ] );

			if ( ! $this->destinations()->isTrusted( $endpoint ) ) {
				$status_code   = 400;
				$response_body = '';
			} else {
				$response = \wp_remote_request( $endpoint, TrustedDestinations::requestArgs( $args ) );

				$response_body = \wp_remote_retrieve_body( $response );
			}
		}

		status_header( $status_code );
		header( "Content-Type: {$content_type}" );

		echo $response_body;

		$this->break_the_default_response_flow();
	}

	public function destinations() {
		return TrustedDestinations::forAteAndAms( $this->endpoints );
	}

	public function get_allowed_capabilities( \WP_REST_Request $request ) {
		return [ User::CAP_MANAGE_TRANSLATIONS, User::CAP_ADMINISTRATOR ];
	}

	private function get_request_params( \WP_REST_Request $request ) {
		$params = [
			'url'          => null,
			'method'       => null,
			'query'        => null,
			'headers'      => null,
			'body'         => null,
			'content_type' => 'application/json',
		];

		if ( $request->get_params() ) {
			$params = \array_merge( $params, $request->get_params() );
		}

		return $params;
	}

	private function break_the_default_response_flow() {
		$shortcut     = function () {
			return function () {
				die();
			};

		};
		$die_handlers = [
			'wp_die_ajax_handler',
			'wp_die_json_handler',
			'wp_die_jsonp_handler',
			'wp_die_xmlrpc_handler',
			'wp_die_xml_handler',
			'wp_die_handler',
		];
		foreach ( $die_handlers as $die_handler ) {
			\add_filter( $die_handler, $shortcut, 10 );
		}

		\wp_die();
	}
}
