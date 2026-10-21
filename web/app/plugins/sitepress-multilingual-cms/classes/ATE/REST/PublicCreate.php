<?php

namespace WPML\TM\ATE\REST;

use WPML\Core\SharedKernel\Component\ATE\Application\Service\TeaLoggerInterface;
use WPML\Posts\UntranslatedCount;
use WPML\Setup\Option;
use WPML\TM\ATE\API\ClientRestrictionState;
use WPML\TM\ATE\API\SpendCapState;
use WPML\TM\ATE\TranslateEverything;
use WPML\TM\AutomaticTranslation\Actions\Actions;

class PublicCreate extends \WPML_TM_ATE_Required_Rest_Base {

	const ENDPOINT_JOBS_PUBLIC_CREATE = '/ate/jobs/public/create';

	const CODE_OK           = 200;
	const CODE_INVALID_SITE = 422;
	const CODE_SERVER_ERROR = 500;

	const LOCK_KEY_ATE_BACKGROUND = 'ate-background';

	private $logger;

	private $translateEverything;

	private $actions;

	private $untranslatedCount;


	public function __construct(
		TeaLoggerInterface $logger,
		TranslateEverything $translateEverything,
		Actions $actions,
		UntranslatedCount $untranslatedCount
	) {
		parent::__construct();
		$this->logger              = $logger;
		$this->translateEverything = $translateEverything;
		$this->actions             = $actions;
		$this->untranslatedCount   = $untranslatedCount;
	}


	function add_hooks() {
		$this->register_routes();
	}

	function register_routes() {
		parent::register_route(
			self::ENDPOINT_JOBS_PUBLIC_CREATE,
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle' ],
				'args'                => [
					'site_identifier' => [
						'required' => true,
						'type'     => 'string',
					],
					'check'           => [
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					],
				],
				'permission_callback' => \WPML\Request\Adapter\Rest::permission(
					\WPML\Request\Policy\Policy::machine(
						[ $this, 'verify_site_identifier' ],
						'ATE public-create poll: site_identifier must equal this site\'s AMS-bound uuid (wpml_get_site_id)'
					),
					self::REST_NAMESPACE . self::ENDPOINT_JOBS_PUBLIC_CREATE
				),
			]
		);
	}

	public function get_allowed_capabilities( \WP_REST_Request $request ) {
		return [];
	}

	public function verify_site_identifier( $request ) {
		$siteIdentifier = is_object( $request ) && method_exists( $request, 'get_param' ) ? (string) $request->get_param( 'site_identifier' ) : '';
		$expectedUuid   = (string) wpml_get_site_id( \WPML_TM_ATE::SITE_ID_SCOPE );

		if ( '' === $expectedUuid || '' === $siteIdentifier || ! hash_equals( $expectedUuid, $siteIdentifier ) ) {
			return new \WP_Error(
				self::CODE_INVALID_SITE,
				'Invalid site identifier',
				[ 'status' => self::CODE_INVALID_SITE ]
			);
		}

		return true;
	}

	public function handle( \WP_REST_Request $request ) {
		$this->logger->beginPublicCreatePing();

		try {
			$siteIdentifier = (string) $request->get_param( 'site_identifier' );
			$check          = (bool) $request->get_param( 'check' );
			$expectedUuid   = (string) wpml_get_site_id( \WPML_TM_ATE::SITE_ID_SCOPE );

			$this->logger->publicCreateReceived(
				$siteIdentifier,
				$check,
				isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : null
			);

				if ( $expectedUuid === '' || $siteIdentifier !== $expectedUuid ) {
					$this->logger->siteIdentifierMismatch( $expectedUuid, $siteIdentifier );
					return new \WP_Error(
						self::CODE_INVALID_SITE,
					'Invalid site identifier',
					[ 'status' => self::CODE_INVALID_SITE ]
				);
			}

			if ( $check ) {
				$this->logger->reachabilityCheckOk();
				$this->logger->envelopeReturned( 'ok', null, self::CODE_OK );
				return new \WP_REST_Response( [ 'action' => 'ok' ], self::CODE_OK );
			}

			return $this->runBatch();
		} finally {
			$this->logger->end();
		}
	}

	private function runBatch() {
		if ( ClientRestrictionState::isRestricted() ) {
			return $this->waitResponse( 'client-restricted' );
		}

		if ( SpendCapState::holds() ) {
			return $this->waitResponse( 'spend-cap-reached' );
		}

		if ( ! Option::shouldTranslateEverything() ) {
			return $this->stopResponse( 'disabled' );
		}

		if ( $this->translateEverything->isEverythingProcessed( true ) ) {
			return $this->stopResponse( 'completed' );
		}

		$this->logger->lockAttempt( self::LOCK_KEY_ATE_BACKGROUND );
		$startMs = (int) ( microtime( true ) * 1000 );

		try {
			$result = $this->translateEverything->run(
				wpml_collect( [ 'holdLock' => false ] ),
				$this->actions
			);
		} catch ( \Throwable $e ) {
			$this->logger->publicCreateException( get_class( $e ), $e->getMessage(), (string) $e->getCode() );
			return $this->errorResponse( $e->getMessage() );
		}

		return $result->bichain(
			fn( $leftValue ) => $this->mapLeftToResponse( $leftValue ),
			fn( $rightValue ) => $this->mapRightToResponse( $rightValue, $startMs )
		);
	}

	private function mapLeftToResponse( $payload ): \WP_REST_Response {
		$key = is_array( $payload ) && isset( $payload['key'] ) ? $payload['key'] : null;

		if ( $key === 'in-use' ) {
			$this->logger->lockHeldByOther();
			$this->logger->envelopeReturned( 'wait', null, self::CODE_OK );
			return new \WP_REST_Response( [ 'action' => 'wait' ], self::CODE_OK );
		}

		if ( $key === 'media-setup-not-finished' ) {
			$this->logger->mediaSetupNotFinishedDeferred();
			$this->logger->envelopeReturned( 'wait', 'media-setup-not-finished', self::CODE_OK );
			return new \WP_REST_Response(
				[ 'action' => 'wait', 'reason' => 'media-setup-not-finished' ],
				self::CODE_OK
			);
		}

		$message = $key !== null
			? (string) $key
			: ( is_array( $payload ) && isset( $payload['error'] ) ? (string) $payload['error'] : 'unknown' );

		$this->logger->setupStateLeft( $message );
		return $this->errorResponse( $message );
	}

	private function mapRightToResponse( $payload, int $startMs ): \WP_REST_Response {
		$this->logger->lockAcquired();

		if ( $this->translateEverything->isEverythingProcessed( true ) ) {
			return $this->stopResponse( 'completed' );
		}

		$createdJobs = is_array( $payload ) && isset( $payload['createdJobs'] ) ? $payload['createdJobs'] : [];

		global $wpdb;
		$created   = count( $createdJobs );
		$remaining = $this->untranslatedCount->countTotal( wpml_collect(), $wpdb );
		$elapsedMs = (int) ( microtime( true ) * 1000 ) - $startMs;

		$this->logger->batchProcessed( $created, $remaining, $elapsedMs );
		$this->logger->envelopeReturned( 'continue', null, self::CODE_OK );

		return new \WP_REST_Response(
			[
				'action'    => 'continue',
				'created'   => $created,
				'remaining' => $remaining,
			],
			self::CODE_OK
		);
	}

	private function stopResponse( string $reason ): \WP_REST_Response {
		$this->logger->envelopeReturned( 'stop', $reason, self::CODE_OK );
		return new \WP_REST_Response(
			[ 'action' => 'stop', 'reason' => $reason ],
			self::CODE_OK
		);
	}

	private function waitResponse( string $reason ): \WP_REST_Response {
		$this->logger->envelopeReturned( 'wait', $reason, self::CODE_OK );
		return new \WP_REST_Response(
			[
				'action' => 'wait',
				'reason' => $reason,
			],
			self::CODE_OK
		);
	}

	private function errorResponse( string $message ): \WP_REST_Response {
		$this->logger->envelopeReturned( 'error', null, self::CODE_SERVER_ERROR );
		return new \WP_REST_Response(
			[ 'action' => 'error', 'message' => $message ],
			self::CODE_SERVER_ERROR
		);
	}
}
