<?php

use WPML\FP\Maybe;
use WPML\TM\ATE\API\FingerprintGenerator;
use WPML\TM\ATE\Log\Entry;
use WPML\TM\ATE\Log\Storage;
use WPML\TM\ATE\Log\EventsTypes;
use WPML\TM\ATE\ClonedSites\ApiCommunication as ClonedSitesHandler;
use WPML\FP\Json;
use WPML\FP\Relation;
use WPML\FP\Either;
use WPML\TM\ATE\API\ErrorMessages;
use WPML\FP\Fns;
use WPML\TM\Editor\ATEDetailedErrorMessage;
use WPML\TM\Jobs\JobLog;
use WPML\TM\ATE\ClonedSites\MigrationLogger;
use WPML\TM\ATE\ClonedSites\Rebind\State as RebindState;
use WPML\FP\Logic;
use WPML\TM\ATE\API\CachedAMSAPI;
use WPML\TM\ATE\API\IdentityRequestTimeout;
use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\WpmlOrgOrigin;
use function WPML\FP\pipe;
use function WPML\FP\invoke;
class WPML_TM_AMS_API {

	const HTTP_ERROR_CODE_400 = 400;

	const HTTP_BAD_GATEWAY = 502;

	private $auth;

	private $endpoints;
	private $wp_http;

	private $clonedSitesHandler;

	private $fingerprintGenerator;

	private $amsRequestSigner;

	private $lastRequestError = null;

	public function __construct(
		WP_Http $wp_http,
		WPML_TM_ATE_Authentication $auth,
		WPML_TM_ATE_AMS_Endpoints $endpoints,
		ClonedSitesHandler $clonedSitesHandler,
		FingerprintGenerator $fingerprintGenerator,
		?\WPML\TM\ATE\API\AmsRequestSigner $amsRequestSigner = null
	) {
		$this->wp_http                = $wp_http;
		$this->auth                   = $auth;
		$this->endpoints              = $endpoints;
		$this->clonedSitesHandler     = $clonedSitesHandler;
		$this->fingerprintGenerator   = $fingerprintGenerator;
		$this->amsRequestSigner       = $amsRequestSigner ?: new \WPML\TM\ATE\API\AmsRequestSigner( $wp_http, $auth, $fingerprintGenerator );
	}

	public function enable_subscription( $translator_email ) {
		$result = null;

		$verb = 'PUT';
		$url  = $this->endpoints->get_enable_subscription();
		$url  = str_replace( '{translator_email}', base64_encode( $translator_email ), $url );

		$response = $this->signed_request( $verb, $url );

		if ( $this->response_has_body( $response ) ) {

			$result = $this->get_errors( $response );

			if ( ! is_wp_error( $result ) ) {
				$result = json_decode( $response['body'], true );
			}
		}

		return $result;
	}

	public function is_subscription_activated( $translator_email ) {
		$result = null;

		$url = $this->endpoints->get_subscription_status();

		$url = str_replace( '{translator_email}', base64_encode( $translator_email ), $url );
		$url = str_replace( '{WEBSITE_UUID}', $this->auth->get_site_id(), $url );

		$response = $this->signed_request( 'GET', $url );

		if ( $this->response_has_body( $response ) ) {

			$result = $this->get_errors( $response );

			if ( ! is_wp_error( $result ) ) {
				$result = json_decode( $response['body'], true );
				$result = $result['subscription'];
			}
		}

		return $result;
	}

	public function get_status() {
		$result = null;

		$registration_data = $this->get_registration_data();
		$shared            = array_key_exists( 'shared', $registration_data ) ? $registration_data['shared'] : null;

		if ( $shared ) {
			$url = $this->endpoints->get_ams_status();
			$url = str_replace( '{SHARED_KEY}', $shared, $url );

			$response = $this->request( 'GET', $url );

			if ( $this->response_has_body( $response ) ) {
				$response_body = json_decode( $response['body'], true );

				$result = $this->get_errors( $response );

				if ( ! is_wp_error( $result ) ) {
					$registration_data = $this->get_registration_data();
					if ( isset( $response_body['activated'] ) && (bool) $response_body['activated'] ) {
						$registration_data['status'] = WPML_TM_ATE_Authentication::AMS_STATUS_ACTIVE;
						$this->set_registration_data( $registration_data );
					}
					$result = $response_body;
				}
			}
		}

		return $result;
	}

	public function get_translation_engines() {
		$result = null;

		$url = $this->endpoints->get_translation_engines();
		$response = $this->signed_request( 'GET', $url );
		if ( $this->response_has_body( $response ) ) {
			$result = $this->get_errors( $response );
			if ( ! is_wp_error( $result ) ) {
				$result = json_decode( $response['body'], true );
			}
		}
		return $result;
	}


	public function get_available_formalities() {
		$result = null;

		$url = $this->endpoints->get_available_formalities();
		$response = $this->signed_request( 'GET', $url );
		if ( $this->response_has_body( $response ) ) {
			$result = $this->get_errors( $response );
			if ( ! is_wp_error( $result ) ) {
				$result = json_decode( $response['body'], true );
			}
		}
		return $result;
	}

	public function getGlossaryCount() {
		$result = $this->getSignedResult(
			'GET',
			$this->endpoints->get_glossary_counts()
		);

		return Maybe::of( $result )->reject( 'is_wp_error' );
	}

	public function getNormalizedSuggestionsCount() {
		$result = $this->getSignedResult(
			'GET',
			$this->endpoints->get_normalized_suggestions_count()
		);

		return Maybe::of( $result )->reject( 'is_wp_error' );
	}

	public function update_translation_engines( $engine_settings ) {
		$result = false;

		$url      = $this->endpoints->get_translation_engines();
		$response = $this->signed_request( 'POST', $url, [ 'list' => $engine_settings ] );
		if ( $this->response_has_body( $response ) ) {
			$result = $this->get_errors( $response );
			if ( ! is_wp_error( $result ) ) {
				$result = json_decode( $response['body'], true );

				CachedAMSAPI::clearCache();
				do_action( 'wpml_tm_ate_translation_engines_updated' );

				return \WPML\FP\Obj::propOr( false, 'success', $result );
			}
		}

		return $result;
	}

	public function register_manager( WP_User $manager, array $translators, array $managers, $forceNewWebsite = false ) {
		$makeRequest = $this->makeRegistrationRequest( $manager, $translators, $managers, $forceNewWebsite );

		$logErrorResponse = $this->logErrorResponse();

		$getErrors = Fns::memorize( function ( $response ) {
			return $this->get_errors( $response, false );
		} );

		$handleErrorResponse = $this->handleErrorResponse( $logErrorResponse, $getErrors );
		$handleGeneralError  = $handleErrorResponse(
			Fns::identity(),
			function( $response ) {
				return Either::left( ErrorMessages::invalidResponse( WPML_Default_Site_Url::get(), $response ) );
			}
		);

		return Either::of( true )
		             ->chain( $makeRequest )
		             ->chain( $handleGeneralError )
		             ->chain( $this->handleInvalidBodyError() )
		             ->map( $this->saveRegistrationData( $manager ) );
	}

	private function makeRegistrationRequest( $manager, $translators, $managers, $forceNewWebsite = false ) {
		$buildParams = function () use ( $manager, $translators, $managers, $forceNewWebsite ) {
			$manager_data     = $this->get_user_data( $manager, true );
			$translators_data = $this->get_users_data( $translators );
			$managers_data    = $this->get_users_data( $managers, true );
			$sitekey          = function_exists( 'OTGS_Installer' ) ? OTGS_Installer()->get_site_key( 'wpml' ) : null;

			$params                 = $manager_data;
			$params['website_url']  = WPML_Default_Site_Url::get();
			$params['translators']          = $translators_data;
			$params['translation_managers'] = $managers_data;
			if ( $sitekey ) {
				$params['site_key'] = $sitekey;
			}

			$websiteUuid = (string) get_option(
				WPML_Site_ID::SITE_ID_KEY . ':' . WPML_TM_ATE::SITE_ID_SCOPE,
				''
			);
			if ( '' !== $websiteUuid ) {
				$params['website_uuid'] = $websiteUuid;
			}

			if ( $forceNewWebsite ) {
				$params['force_new_website'] = true;
			}

			return $params;
		};

		$handleUnavailableATEError = function ( $response ) {
			if ( is_wp_error( $response ) ) {
				$website_url = WPML_Default_Site_Url::get();
				$this->log_api_error(
					ErrorMessages::serverUnavailableHeader(),
					[ 'responseError' => $response->get_error_message(), 'website_url' => $website_url ]
				);
				$msg = $this->ping_healthy_wpml_endpoint()
					? ErrorMessages::serverUnavailable( $website_url, $response )
					: ErrorMessages::offline( $website_url, $response );

				return Either::left( $msg );
			}

			return Either::of( $response );
		};

		return function () use ( $buildParams, $handleUnavailableATEError ) {
			$response = $this->request( 'POST', $this->endpoints->get_ams_register_client(), $buildParams() );

			return $handleUnavailableATEError( $response );
		};
	}

	private function logErrorResponse() {
		return function ( $error ) {
			$this->log_api_error(
				ErrorMessages::respondedWithError(),
				[
					'responseError' => $error->get_error_message(),
					'website_url'   => WPML_Default_Site_Url::get(),
				]
			);
		};
	}

	private function handleErrorResponse($logErrorResponse, $getErrors) {
		return \WPML\FP\curryN( 3, function ( $shouldHandleError, $errorHandler, $response ) use ( $logErrorResponse, $getErrors ) {
			$error = $getErrors( $response );

			if ( $shouldHandleError( $error ) ) {
				$logErrorResponse( $error );

				return $errorHandler( $response );
			}

			return Either::of( $response );
		} );
	}

	private function handleInvalidBodyError() {
		return function ( $response ) {
			if ( ! $this->response_has_keys( $response ) ) {
				$website_url = WPML_Default_Site_Url::get();
				$this->log_api_error(
					ErrorMessages::respondedWithError(),
					[ 'responseError' => ErrorMessages::bodyWithoutRequiredFields(), 'response' => json_encode( $response ), 'website_url' => $website_url ]
				);

				return Either::left( ErrorMessages::invalidResponse( $website_url, $response ) );
			}

			return Either::of( $response );
		};
	}

	private function saveRegistrationData($manager) {
		return function ( $response ) use ( $manager ) {
			$registration_data = $this->get_registration_data();

			$response_body = json_decode( $response['body'], true );

			$registration_data['user_id'] = $manager->ID;
			$registration_data['secret']  = $response_body['secret_key'];
			$registration_data['shared']  = $response_body['shared_key'];
			$registration_data['status']  = WPML_TM_ATE_Authentication::AMS_STATUS_ENABLED;

			update_option(
				WPML_Site_ID::SITE_ID_KEY . ':' . WPML_TM_ATE::SITE_ID_SCOPE,
				$response_body['website_uuid'],
				false
			);

			$this->auth->override_site_id( $response_body['website_uuid'] );

			RebindState::noteRegistrationAnswer( $response_body );

			$stored = $this->set_registration_data( $registration_data );

			\WPML\TM\ATE\SiteName\Sender::send();

			return $stored;
		};
	}

	private function get_user_data( WP_User $wp_user, $with_name_details = false ) {
		$data = array();

		$data['email'] = $this->ensure_user_email_is_not_empty( $wp_user );
		$display_name = $this->ensure_display_name_is_not_empty( $wp_user, $data['email'] );

		if ( $with_name_details ) {
			$data['display_name'] = $display_name;
			$data['first_name']   = $wp_user->first_name;
			$data['last_name']    = $wp_user->last_name;
		} else {
			$data['name'] = $display_name;
		}

		return $data;
	}

	private function ensure_user_email_is_not_empty( WP_User $wp_user ) {
		if ( ! empty( $wp_user->user_email ) ) {
			return $wp_user->user_email;
		}

		$fake_email = 'noreply-user-' . $wp_user->ID . '@placeholder.test';

		wp_update_user(
			array(
				'ID'         => $wp_user->ID,
				'user_email' => $fake_email,
			)
		);

		return $fake_email;
	}

	private function ensure_display_name_is_not_empty( WP_User $wp_user, string $default_value ) {
		if ( ! empty( $wp_user->display_name ) ) {
			return $wp_user->display_name;
		}

		$display_name = ! empty( $wp_user->user_login ) ? $wp_user->user_login : $default_value;

		wp_update_user(
			array(
				'ID'           => $wp_user->ID,
				'display_name' => $display_name,
			)
		);

		return $display_name;
	}

	private function getTimeout( int $minimum = 5 ): int {
		$max_execution_time = ini_get( 'max_execution_time' );
		$timeout = $max_execution_time ? (int) $max_execution_time / 2 : 1;

		return max( $timeout, $minimum );
	}

	private function get_users_data( array $users, $with_name_details = false ) {
		$user_data = array();

		foreach ( $users as $user ) {
			$wp_user = get_user_by( 'id', $user->ID );

			if ( $wp_user ) {
				$user_data[] = $this->get_user_data( $wp_user, $with_name_details );
			}
		}

		return $user_data;
	}

	private function response_has_body( $response ) {
		return ! is_wp_error( $response ) && array_key_exists( 'body', $response );
	}

	private function get_errors( $response, $logError = true ) {
		$response_errors = null;

		if ( is_wp_error( $response ) ) {
			$response_errors = $response;
		} elseif ( array_key_exists( 'body', $response ) && $response['response']['code'] >= self::HTTP_ERROR_CODE_400 ) {
			$main_error    = array();
			$errors        = array();
			$error_message = $response['response']['message'];

			$response_body = json_decode( $response['body'], true );
			if ( ! $response_body ) {
				$error_message = $response['body'];
				$main_error    = array( $response['body'] );
			} elseif ( array_key_exists( 'errors', $response_body ) ) {
				$errors        = $response_body['errors'];
				$main_error    = array_shift( $errors );
				$error_message = $this->get_error_message( $main_error, $response['body'] );
			}

			$upstream_status = $response['response']['code'];

			$response_errors = new WP_Error(
				$upstream_status,
				$error_message,
				$this->upstream_error_data( $upstream_status, $error_message, $main_error )
			);

			foreach ( $errors as $error ) {
				$error_message = $this->get_error_message( $error, $response['body'] );
				$error_status  = isset( $error['status'] ) ? 'ams_error: ' . $error['status'] : '';
				$response_errors->add(
					$error_status,
					$error_message,
					$this->upstream_error_data( $upstream_status, $error_message, $error )
				);
			}
		}

		if ( $logError && $response_errors ) {
			$this->log_api_error( $response_errors->get_error_message(), $response_errors->get_error_data() );
		}

		return $response_errors;
	}

	private function upstream_error_data( $upstream_status, $upstream_message, $upstream_errors ) {
		return [
			'status'           => self::HTTP_BAD_GATEWAY,
			'upstream_status'  => (int) $upstream_status,
			'upstream_message' => $upstream_message,
			'errors'           => $upstream_errors,
		];
	}

	private function log_api_error( $message, $data ) {
		$entry              = new Entry();
		$entry->eventType   = EventsTypes::SERVER_AMS;
		$entry->description = $message;
		$entry->extraData   = [ 'errorData' => $data ];

		wpml_tm_ate_ams_log( $entry );
	}

	private function ping_healthy_wpml_endpoint() {
		$url      = defined( 'WPML_TM_INTERNET_CHECK_URL' ) ? WPML_TM_INTERNET_CHECK_URL : WpmlOrgOrigin::health() . '/';
		$response = $this->request( 'GET', $url, [] );

		return ! is_wp_error( $response ) && (int) \WPML\FP\Obj::path( [ 'response', 'code' ], $response ) === 200;
	}

	private function get_error_message( $ams_error, $default ) {
		$title   = isset( $ams_error['title'] ) ? $ams_error['title'] . ': ' : '';
		$details = isset( $ams_error['detail'] ) ? $ams_error['detail'] : $default;

		return $title . $details;
	}

	private function response_has_keys( $response ) {
		$response_body = json_decode( $response['body'], true );

		return array_key_exists( 'secret_key', $response_body )
			&& array_key_exists( 'shared_key', $response_body )
			&& array_key_exists( 'website_uuid', $response_body );
	}

	public function get_registration_data() {
		$data = get_option( WPML_TM_ATE_Authentication::AMS_DATA_KEY, [] );

		return is_array( $data ) ? $data : [];
	}

	private function set_registration_data( $registration_data ) {
		return update_option( WPML_TM_ATE_Authentication::AMS_DATA_KEY, $registration_data );
	}

	public function synchronize_managers( array $managers ) {
		$result = null;

		$managers_data = $this->get_users_data( $managers, true );

		if ( $managers_data ) {
			$url = $this->endpoints->get_ams_synchronize_managers();
			$url = str_replace( '{WEBSITE_UUID}', wpml_get_site_id( WPML_TM_ATE::SITE_ID_SCOPE ), $url );

			$params = array( 'translation_managers' => $managers_data );

			$response = $this->signed_request( 'PUT', $url, $params );

			if ( $this->response_has_body( $response ) ) {
				$response_body = json_decode( $response['body'], true );

				$result = $this->get_errors( $response );

				if ( ! is_wp_error( $result ) ) {
					$result = $response_body;
				}
			} elseif ( is_wp_error( $response ) ) {
				$result = $response;
			}

			if ( is_wp_error( $result ) ) {
				ATEDetailedErrorMessage::saveDetailedError( $result );
			}
		}

		return $result;
	}

	public function synchronize_translators( array $translators ) {
		$result = null;

		$translators_data = $this->get_users_data( $translators );

		if ( $translators_data ) {
			$url = $this->endpoints->get_ams_synchronize_translators();

			$params = array( 'translators' => $translators_data );

			$response = $this->signed_request( 'PUT', $url, $params );

			if ( $this->response_has_body( $response ) ) {
				$response_body = json_decode( $response['body'], true );

				$result = $this->get_errors( $response );

				if ( ! is_wp_error( $result ) ) {
					$result = $response_body;
				}
			} elseif ( is_wp_error( $response ) ) {
				$result = $response;
			}

			if ( is_wp_error( $result ) ) {
				ATEDetailedErrorMessage::saveDetailedError( $result );
			}
		}

		return $result;
	}

	private function request( $method, $url, ?array $params = null ) {
		$lock = $this->clonedSitesHandler->checkCloneSiteLock( $url );
		if ( $lock ) {
			JobLog::add(
				'WPML_TM_AMS_API request lock check failed',
				[
					'method' => $method,
					'url'    => $url,
					'params' => $params,
				]
			);
			return $lock;
		}

		$method  = strtoupper( $method );
		$headers = [
			'Accept'                                      => 'application/json',
			'Content-Type'                                => 'application/json',
			FingerprintGenerator::SITE_FINGERPRINT_HEADER => $this->fingerprintGenerator->getSiteFingerprint(),
		];

		$args = [
			'method'  => $method,
			'headers' => $headers,
			'timeout' => $this->timeoutFor( $url ),
		];

		if ( $params ) {
			$body = wp_json_encode( $params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$args['body'] = $body ?: '';
		}

		$versioned_url = $this->add_versions_to_url( $url );

		JobLog::addExtraLogData( 'apiCall', $url );
		JobLog::add(
			'WPML_TM_AMS_API request',
			[
				'method'        => $method,
				'url'           => $url,
				'params'        => $params,
				'versioned_url' => $versioned_url,
				'args'          => $args,
			]
		);
		$response = $this->wp_http->request( $versioned_url, $args );
		JobLog::add(
			'WPML_TM_AMS_API request response',
			[
				'response' => $response,
			]
		);
		JobLog::removeExtraLogData( 'apiCall' );

		if ( is_wp_error( $response ) ) {
			$this->clonedSitesHandler->handleTransportFailure( $response, \WPML\TM\ATE\ClonedSites\ReconnectState::SERVICE_AMS );

			return $response;
		}

		$this->clonedSitesHandler->noteServerAnswered( $response, \WPML\TM\ATE\ClonedSites\ApiCommunication::CALL_OTHER, \WPML\TM\ATE\ClonedSites\ReconnectState::SERVICE_AMS );

		return $this->clonedSitesHandler->handleClonedSiteError( $response, \WPML\TM\ATE\ClonedSites\ReconnectState::SERVICE_AMS );
	}

	private function minimumTimeoutFor( $url ) {
		$identityEndpoints = [
			\WPML_TM_ATE_AMS_Endpoints::ENDPOINTS_SITE,
			\WPML_TM_ATE_AMS_Endpoints::ENDPOINTS_COPY_ATTACHED,
			\WPML_TM_ATE_AMS_Endpoints::ENDPOINTS_SITE_CONFIRM,
		];

		foreach ( $identityEndpoints as $endpoint ) {
			if ( is_string( $url ) && strpos( $url, $endpoint ) !== false ) {
				return IdentityRequestTimeout::seconds();
			}
		}

		return 5;
	}

	private function timeoutFor( $url ) {
		if ( is_string( $url ) && strpos( $url, \WPML_TM_ATE_AMS_Endpoints::ENDPOINTS_WEBSITE_SITE_NAME ) !== false ) {
			return 10;
		}

		return $this->getTimeout( $this->minimumTimeoutFor( $url ) );
	}

	private function signed_request( $verb, $url, ?array $params = null ) {
		$verb = strtoupper( $verb );

		$response = $this->signedRequestOnce( $verb, $url, $params );

		if ( ClonedSitesHandler::shouldReplay( $response ) ) {
			$response = ClonedSitesHandler::replay(
				function () use ( $verb, $url, $params ) {
					return $this->signedRequestOnce( $verb, $url, $params );
				}
			);
		}

		return $response;
	}

	private function signedRequestOnce( $verb, $url, ?array $params = null ) {
		$signed_url = $this->auth->get_signed_url_with_parameters( $verb, $url, $params );

		if ( is_wp_error( $signed_url ) ) {
			return $signed_url;
		}

		return $this->request( $verb, $signed_url, $params );
	}

	private function add_versions_to_url( $url ) {
		$url_parts = wp_parse_url( $url );
		$url_parts = $url_parts ?: [];
		$query     = array();

		if ( array_key_exists( 'query', $url_parts ) ) {
			parse_str( $url_parts['query'], $query );
		}
		$query['wpml_core_version'] = ICL_SITEPRESS_VERSION;
		$query['wpml_tm_version']   = defined( 'WPML_TM_VERSION' ) ? WPML_TM_VERSION : '1.0';

		$url_parts['query'] = wpml_http_build_query( $query );
		$url                = http_build_url( $url_parts );

		return $url;
	}

	public function override_site_id( $site_id ) {
		$this->auth->override_site_id( $site_id );
	}

	public function getCredits() {
		return $this->getSignedResult(
			'GET',
			$this->endpoints->get_credits()
		);
	}

	public function getAccountBalances() {
		return $this->getSignedResult(
			'GET',
			$this->endpoints->get_account_balances()
		);
	}

	public function getWebsiteMigrationCode() {
		return $this->getSignedResult(
			'GET',
			$this->endpoints->get_website_migration_code()
		);
	}

	public function send_sitekey( $sitekey ) {
		$siteId   = wpml_get_site_id( WPML_TM_ATE::SITE_ID_SCOPE );
		$response = $this->getSignedResult(
			'POST',
			$this->endpoints->get_send_sitekey(),
			[
				'site_key'     => $sitekey,
				'website_uuid' => $siteId,
			]
		);

		return Relation::propEq( 'updated_website', $siteId, $response );
	}

	public function updateSiteName( $siteName ) {
		$response = $this->signed_request(
			'PUT',
			$this->endpoints->get_ams_site_name(),
			[ 'site_name' => (string) $siteName ]
		);

		$error = $this->get_errors( $response, false );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$code = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'ams_site_name_not_stored', sprintf( 'AMS answered %d to the site_name update', $code ) );
		}

		return true;
	}

	public function unassign_sitekey( $sitekey ) {
		$siteId = wpml_get_site_id( WPML_TM_ATE::SITE_ID_SCOPE );

		return $this->getSignedResult(
			'POST',
			$this->endpoints->get_unassign_sitekey(),
			[
				'site_key'     => $sitekey,
				'website_uuid' => $siteId,
			]
		);
	}

	public function disconnect() {
		return $this->getSignedResult( 'POST', $this->endpoints->get_ams_disconnect() );
	}

	public function connect() {
		return $this->getSignedResult( 'POST', $this->endpoints->get_ams_connect() );
	}

	public function getLastRequestError() {
		return $this->lastRequestError;
	}

	private function getSignedResult( $verb, $url, ?array $params = null ) {
		$result = null;

		$response = $this->signed_request( $verb, $url, $params );

		$this->lastRequestError = is_wp_error( $response ) ? $response : null;

		if ( $this->response_has_body( $response ) ) {

			$result = $this->get_errors( $response );

			if ( ! is_wp_error( $result ) ) {
				$result = Json::toArray( $response['body'] );
			}
		}

		return $result;

	}
}
