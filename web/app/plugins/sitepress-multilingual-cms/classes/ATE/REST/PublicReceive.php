<?php

namespace WPML\TM\ATE\REST;

use WPML\Core\Security\ExecutionContext\ExecutionContext;
use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;
use WPML\Core\Security\ExecutionContext\MachineCallbackProof;
use WPML\FP\Obj;
use WPML\TM\API\ATE;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\ClonedSites\ReconnectState;
use WPML\TM\ATE\PullDelivery\State;
use WPML\TM\ATE\Receive\ClientEdits;
use WPML\TM\ATE\Receive\JobKind;
use WPML\TM\ATE\Receive\SingleJobDelivery;
use WPML\TM\ATE\Receive\TranslationApplier;
use WPML\TM\ATE\SyncLock;
use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;

class PublicReceive extends \WPML_TM_ATE_Required_Rest_Base {

	const CODE_LOCKED               = 423;
	const CODE_UNPROCESSABLE_ENTITY = 422;
	const CODE_TOO_EARLY            = 425;
	const CODE_INTERNAL_ERROR       = 500;
	const CODE_SERVICE_UNAVAILABLE  = 503;
	const CODE_OK                   = 200;
	const CODE_FORBIDDEN            = 403;

	const REASON_JOB_MISSING     = 'wpml_job_missing';
	const REASON_XLIFF_NOT_READY = 'xliff_not_ready';
	const REASON_APPLY_FAILED    = 'apply_failed';

	const REASON_JOB_CANCELLED = 'wpml_job_cancelled';

	const REASON_TRANSLATION_EDITED = ClientEdits::REASON;

	const RETRY_AFTER_XLIFF_NOT_READY = 60;
	const RETRY_AFTER_APPLY_FAILED    = 300;

	const ENDPOINT_JOBS_RECEIVE = '/ate/jobs/receive/';

	const TOKEN_PARAM          = 't';
	const TOKEN_MESSAGE_PREFIX = 'ate-receive|';

	const LOCK_WAIT_TRIES   = 12;
	const LOCK_WAIT_STEP_US = 250000;

	private $lockWaitSleeper = 'usleep';

	public function setLockWaitSleeper( callable $sleeper ) {
		$this->lockWaitSleeper = $sleeper;

		return $this;
	}

	function add_hooks() {
		$this->register_routes();
	}

	function register_routes() {
		parent::register_route(
			self::ENDPOINT_JOBS_RECEIVE . '(?P<wpmlJobId>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'receive_ate_job' ),
				'args'                => array(
					'wpmlJobId' => array(
						'required'          => true,
						'type'              => 'int',
						'validate_callback' => array( 'WPML_REST_Arguments_Validation', 'integer' ),
						'sanitize_callback' => array( 'WPML_REST_Arguments_Sanitation', 'integer' ),
					),
				),
				'permission_callback' => \WPML\Request\Adapter\Rest::permission(
					\WPML\Request\Policy\Policy::machine(
						array( $this, 'verify_receive_callback_token' ),
						'ATE delivery callback: job-bound HMAC-SHA256 token in ?t= (a missing token only reaches the inert legacy path)'
					),
					self::REST_NAMESPACE . self::ENDPOINT_JOBS_RECEIVE . '(?P<wpmlJobId>\d+)'
				),
			)
		);
	}

	public function verify_receive_callback_token( \WP_REST_Request $request ) {
		if ( ! $request->has_param( self::TOKEN_PARAM ) ) {
			return true;
		}

		if ( self::is_valid_receive_token( $request->get_param( 'wpmlJobId' ), $request->get_param( self::TOKEN_PARAM ) ) ) {
			return true;
		}

		return new \WP_Error(
			'wpml_ate_receive_invalid_token',
			'Invalid ATE callback token.',
			[ 'status' => self::CODE_FORBIDDEN ]
		);
	}

	public static function get_receive_token( $wpml_job_id ) {
		$salt = wp_salt( 'auth' );
		if ( ! is_string( $salt ) || strlen( $salt ) < 32 ) {
			return '';
		}

		return hash_hmac( 'sha256', self::TOKEN_MESSAGE_PREFIX . (int) $wpml_job_id, $salt );
	}

	public static function is_valid_receive_token( $wpml_job_id, $token ) {
		$expected = self::get_receive_token( $wpml_job_id );

		return '' !== $expected
			&& is_string( $token )
			&& hash_equals( $expected, $token );
	}

	public function get_allowed_capabilities( \WP_REST_Request $request ) {
		return [];
	}

	public function receive_ate_job( \WP_REST_Request $request ) {
		$wpmlJobId = $request->get_param( 'wpmlJobId' );

		if ( ! $request->has_param( self::TOKEN_PARAM ) ) {
			return $this->respondLegacyTokenless();
		}

		if ( ! self::is_valid_receive_token( $wpmlJobId, $request->get_param( self::TOKEN_PARAM ) ) ) {
			return new \WP_Error(
				self::CODE_FORBIDDEN,
				'',
				[ 'status' => self::CODE_FORBIDDEN ]
			);
		}

		$context = ExecutionContext::trusted( MachineCallbackProof::forVerifiedCallback( (int) $wpmlJobId, 'ate-receive-token' ) );

		ExecutionContextHolder::coverDeferredWork( $context );

		return ExecutionContextHolder::within(
			$context,
			function () use ( $wpmlJobId ) {
				return $this->receiveVerifiedAteJob( (int) $wpmlJobId );
			}
		);
	}

	private function receiveVerifiedAteJob( $wpmlJobId ) {
		JobLog::maybeInitRequest();
		JobLog::createNewGroup(
			JobLog::GROUP_ID_DOWNLOAD_JOBS,
			'ATE webhook (PublicReceive)',
			[ 'rid' => $wpmlJobId ]
		);

		try {
			State::onWebhookReceived();

			$delivery = new SingleJobDelivery(
				make( SyncLock::class ),
				function () {
					return new TranslationApplier( make( ATE::class ) );
				}
			);

			$tries    = 0;
			$waitedMs = 0;

			while ( true ) {
				$result = $delivery->deliver(
					$wpmlJobId,
					'publicReceive',
					function () use ( $wpmlJobId ) {
						JobLog::add( 'webhook_lock_acquired', [ 'rid' => $wpmlJobId ] );
					}
				);

				++$tries;
				if ( SingleJobDelivery::LOCK_BUSY !== $result['outcome'] || $tries >= self::LOCK_WAIT_TRIES ) {
					break;
				}

				call_user_func( $this->lockWaitSleeper, self::LOCK_WAIT_STEP_US );
				$waitedMs += (int) ( self::LOCK_WAIT_STEP_US / 1000 );
			}

				switch ( $result['outcome'] ) {
					case SingleJobDelivery::LOCK_BUSY:
						JobLog::add(
							'webhook_lock_busy_423',
							[
								'rid'       => $wpmlJobId,
								'waited_ms' => $waitedMs,
							]
						);
						return new \WP_Error( self::CODE_LOCKED, '', [ 'status' => self::CODE_LOCKED ] );
				case SingleJobDelivery::CANCELLED:
					return $this->respondCancelled( $wpmlJobId );
				case SingleJobDelivery::JOB_MISSING:
					return $this->respondJobMissing( $wpmlJobId );
				case SingleJobDelivery::CLIENT_EDITS:
					return $this->respondTranslationEdited( $wpmlJobId );
				case SingleJobDelivery::SUPERSEDED:
					return $this->respondSuperseded( $wpmlJobId, $result['superseded_by'] );
				case SingleJobDelivery::CLOSED:
					JobLog::add( 'webhook_closed_request_clone', [ 'wpml_job_id' => (int) $wpmlJobId, 'http_status' => self::CODE_OK ] );

					return new \WP_REST_Response( null, self::CODE_OK );
				default:
					return $this->respondToOutcome( $result['outcome'], $wpmlJobId );
			}
		} catch ( \Throwable $e ) {
			JobLog::addError(
				'webhook_uncaught_exception',
				[
					'rid'     => (int) $wpmlJobId,
					'message' => $e->getMessage(),
					'origin'  => $e->getFile() . ':' . $e->getLine(),
				]
			);

			return new \WP_Error( self::CODE_INTERNAL_ERROR, '', [ 'status' => self::CODE_INTERNAL_ERROR ] );
		} finally {
			JobLog::finishCurrentGroup();
		}
	}

	private function isTaxonomyTermJob( $wpmlJobId ) {
		return JobKind::isTaxonomyTerm( $wpmlJobId );
	}

	private function respondToOutcome( $outcome, $wpmlJobId ) {
		if ( TranslationApplier::APPLIED === $outcome ) {
			return $this->respondApplied( $wpmlJobId );
		}

		if ( TranslationApplier::XLIFF_NOT_READY === $outcome ) {
			return $this->respondXliffNotReady( $wpmlJobId );
		}

		return $this->respondApplyFailed( $wpmlJobId );
	}

	private function respondApplied( $wpmlJobId ) {
		JobLog::add( 'webhook_applied', [ 'rid' => $wpmlJobId, 'http_status' => self::CODE_OK ] );

		State::onTranslationReceived();

		return new \WP_REST_Response( null, self::CODE_OK );
	}

	private function respondJobMissing( $wpmlJobId ) {
		JobLog::addError( 'webhook_job_missing', [
			'rid'         => $wpmlJobId,
			'http_status' => self::CODE_UNPROCESSABLE_ENTITY,
			'wpml_job_id' => (int) $wpmlJobId,
			'reason'      => 'wpml_job_no_longer_in_icl_translate_job',
		] );

		return new \WP_REST_Response(
			[ 'code' => self::REASON_JOB_MISSING, 'jobId' => (int) $wpmlJobId ],
			self::CODE_UNPROCESSABLE_ENTITY
		);
	}

	private function respondCancelled( $wpmlJobId ) {
		JobLog::addError( 'webhook_cancelled_422', [
			'wpml_job_id' => (int) $wpmlJobId,
			'http_status' => self::CODE_UNPROCESSABLE_ENTITY,
			'reason'      => 'wpml_job_cancelled_on_site',
		] );

		return new \WP_REST_Response(
			[ 'code' => self::REASON_JOB_CANCELLED, 'jobId' => (int) $wpmlJobId ],
			self::CODE_UNPROCESSABLE_ENTITY
		);
	}

	private function respondTranslationEdited( $wpmlJobId ) {
		JobLog::add( 'webhook_translation_edited_422', [
			'rid'         => (int) $wpmlJobId,
			'wpml_job_id' => (int) $wpmlJobId,
			'http_status' => self::CODE_UNPROCESSABLE_ENTITY,
			'reason'      => self::REASON_TRANSLATION_EDITED,
		] );

		return new \WP_REST_Response(
			[ 'code' => self::REASON_TRANSLATION_EDITED, 'jobId' => (int) $wpmlJobId ],
			self::CODE_UNPROCESSABLE_ENTITY
		);
	}


	private function respondSuperseded( $wpmlJobId, array $supersededBy ) {
		JobLog::addError( 'webhook_superseded_422', [
			'rid'          => (int) $supersededBy['rid'],
			'newer_job_id' => (int) $supersededBy['newer_job_id'],
			'wpml_job_id'  => (int) $wpmlJobId,
			'http_status'  => self::CODE_UNPROCESSABLE_ENTITY,
		] );

		return $this->respondJobMissing( $wpmlJobId );
	}


	private function respondXliffNotReady( $wpmlJobId ) {
		JobLog::add(
			'webhook_xliff_not_ready',
			[
				'rid'                => $wpmlJobId,
				'http_status'        => self::CODE_TOO_EARLY,
				'unreachable_window' => \WPML_TM_ATE_API::isUnreachableWindowLive(),
				'reconnect_episode'  => self::reconnectEpisode(),
			]
		);

		return new \WP_REST_Response(
			[ 'code' => self::REASON_XLIFF_NOT_READY, 'retry_after' => self::RETRY_AFTER_XLIFF_NOT_READY ],
			self::CODE_TOO_EARLY
		);
	}

	private static function reconnectEpisode() {
		if ( ! ReconnectState::get() ) {
			return 'none';
		}

		return ReconnectState::isNoAnswer() ? ReconnectState::KIND_NO_ANSWER : 'diagnosed';
	}

	private function respondApplyFailed( $wpmlJobId ) {
		JobLog::addError(
			'webhook_apply_failed',
			[
				'rid'         => $wpmlJobId,
				'http_status' => self::CODE_SERVICE_UNAVAILABLE,
				'reason'      => \WPML\TM\ATE\TranslateEverything\TaxonomyTermJobApplier::lastFailureReason(),
			]
		);

		return new \WP_REST_Response(
			[ 'code' => self::REASON_APPLY_FAILED, 'retry_after' => self::RETRY_AFTER_APPLY_FAILED ],
			self::CODE_SERVICE_UNAVAILABLE
		);
	}


	private function respondLegacyTokenless() {
		return new \WP_REST_Response( null, self::CODE_OK );
	}

	public static function get_receive_ate_job_url( $wpml_job_id ) {
		$url   = self::get_url( self::ENDPOINT_JOBS_RECEIVE . $wpml_job_id );
		$token = self::get_receive_token( $wpml_job_id );

		return $token ? add_query_arg( self::TOKEN_PARAM, $token, $url ) : $url;
	}
}
