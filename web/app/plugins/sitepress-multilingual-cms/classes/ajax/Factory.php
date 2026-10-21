<?php

namespace WPML\Ajax;

use WPML\Ajax\Authorization\Authorization;
use WPML\Collect\Support\Collection;
use WPML\Core\Security\ExecutionContext\ExecutionContext;
use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Json;
use WPML\FP\Logic;
use WPML\FP\Lst;
use WPML\FP\Maybe;
use WPML\FP\System\System;
use WPML\LIB\WP\Hooks;
use WPML\LIB\WP\Nonce;
use function WPML\Container\execute;
use function WPML\Container\make;
use function WPML\FP\System\getFilterFor as filter;
use function WPML\FP\System\getValidatorFor as validate;
use function WPML\PHP\Logger\error as logError;

class Factory implements \IWPML_AJAX_Action {

	const GENERIC_ERROR_MESSAGE = 'Internal server error.';

	public function add_hooks() {
		$filterEndPoint = filter( 'endpoint' )->using( 'wp_unslash' );

		$decodeData = filter( 'data' )->using( Json::toCollection() )->defaultTo( 'wpml_collect' );

		$validateData = validate( 'data' )->using( Logic::isNotNull() )->error( 'Invalid json data' );

		$handleRequest = function ( Collection $postData ) {
			ob_start();
			try {
				$endpoint = $postData->get( 'endpoint' );
				$data     = $postData->get( 'data' );

				$handler     = null;
				$makeHandler = function () use ( &$handler, $endpoint ) {
					if ( null === $handler ) {
						$handler = make( $endpoint );
					}

					return $handler;
				};

				if ( \WPML\Setup\Initializer::shouldBlockMutatingEndpoint( $endpoint, $data ) ) {
					$result = Either::left( \WPML\Setup\Initializer::getSettingsRecoveryError() );
				} elseif ( ! Authorization::isAuthorized(
					$endpoint,
					$makeHandler,
					$data instanceof Collection ? $data : wpml_collect( is_array( $data ) ? $data : [] )
				) ) {
					$result = Either::left( Authorization::ERROR );
				} else {
					$result = ExecutionContextHolder::within(
						ExecutionContext::request( ExecutionContextHolder::currentPrincipalId(), (string) $endpoint ),
						function () use ( $endpoint, $makeHandler, $data ) {
							return Maybe::of( $endpoint )
								->map( function () use ( $makeHandler ) {
									return Lst::makePair( $makeHandler(), 'run' );
								} )
								->map( execute( Fns::__, [ ':data' => $data ] ) )
								->getOrElse( Either::left( 'End point not found' ) );
						}
					);
				}
			} catch ( \Throwable $e ) {
				logError(
					sprintf(
						'WPML AJAX endpoint "%s" failed: %s: %s',
						(string) $postData->get( 'endpoint' ),
						get_class( $e ),
						$e->getMessage()
					)
				);
				$result = Either::left( self::GENERIC_ERROR_MESSAGE );
			}

			$strayOutput = trim( (string) ob_get_clean() );
			if ( '' !== $strayOutput ) {
				logError(
					sprintf(
						'WPML AJAX endpoint "%s" emitted unexpected output: %s',
						(string) $postData->get( 'endpoint' ),
						$strayOutput
					)
				);

				return Either::left( [ 'error' => 'unexpected_output' ] );
			}

			return $result;
		};

		\WPML\Request\Adapter\Ajax::declare(
			'wpml_action',
			\WPML\Request\Policy\Policy::authenticated(
				\WPML\Request\Policy\Authenticity::none( 'the dispatcher verifies the per-endpoint nonce (Nonce::verifyEndPoint) before decoding the request' ),
				'per-endpoint policy resolved fail-closed by WPML\Ajax\Authorization\Authorization (EndpointPolicies, Authorized, PublicHandler)'
			)
		);

		Hooks::onAction( 'wp_ajax_wpml_action' )
			->then( System::getPostData() )
			->then( $filterEndPoint )
			->then( Nonce::verifyEndPoint() )
			->then( $decodeData )
			->then( $validateData )
			->then( $handleRequest )
			->then( 'wp_send_json_success' )
			->onError( 'wp_send_json_error' );
	}
}
