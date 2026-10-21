<?php

class WPML_TP_API_Client {
	private $proxy_url;

	private $http;

	private $tp_lock;

	private $request_filter;

	public function __construct(
		$proxy_url,
		WP_Http $http,
		WPML_TP_Lock $tp_lock,
		WPML_TP_HTTP_Request_Filter $request_filter
	) {
		$this->proxy_url      = $proxy_url;
		$this->http           = $http;
		$this->tp_lock        = $tp_lock;
		$this->request_filter = $request_filter;
	}

	public function send_request( WPML_TP_API_Request $request, $raw_json_response = false ) {
		if ( $this->tp_lock->is_locked( $request->get_url() ) ) {
			throw new WPML_TP_API_Exception( 'Communication with translation proxy is not allowed.', $request );
		}
		WPML_TranslationProxy_Com_Log::log_call( $request->get_url(), $request->get_params() );
		$started  = microtime( true );
		$response = $this->call_remote_api( $request );

		$failed = ! $response || is_wp_error( $response ) || ( isset( $response['response'] ) && isset( $response['response']['code'] ) && $response['response']['code'] >= 400 );

		if ( \WPML\TM\Jobs\JobLog::canLog() ) {
			$is_response_array = is_array( $response );

			$call_data = array(
				'method'         => $request->get_method(),
				'endpoint'       => (string) wpml_parse_url( $request->get_url(), PHP_URL_PATH ),
				'http_status'    => $is_response_array && isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0,
				'duration_ms'    => (int) round( ( microtime( true ) - $started ) * 1000 ),
				'response_bytes' => $is_response_array && isset( $response['body'] ) && is_string( $response['body'] ) ? strlen( $response['body'] ) : 0,
			);

			if ( $failed ) {
				$call_data['error'] = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP failure';
				\WPML\TM\Jobs\JobLog::addError( 'tp_api_call_failed', $call_data );
			} else {
				\WPML\TM\Jobs\JobLog::add( 'tp_api_call', $call_data );
			}
		}

		if ( $failed ) {
			throw new WPML_TP_API_Exception( 'Communication error', $request, $response );
		}

		if ( isset( $response['headers'] ) && isset( $response['headers']['content-type'] ) ) {
			$content_type = $response['headers']['content-type'];
			$response     = $response['body'];

			if ( strpos( $content_type, 'zip' ) !== false ) {
				$response = gzdecode( $response );
			} else {
				WPML_TranslationProxy_Com_Log::log_response( $response );
			}

			$json_response = json_decode( $response );

			if ( $json_response ) {
				if ( $raw_json_response ) {
					$response = $json_response;
				} else {
					$response = $this->handle_json_response( $request, $json_response );
				}
			}
		}

		return $response;
	}


	private function call_remote_api( WPML_TP_API_Request $request ) {
		$context = $this->filter_request_params(
			$request->get_params(),
			$request->get_method(),
			$request->get_timeout()
		);

		return $this->http->request( $this->proxy_url . $request->get_url(), $context );
	}

	private function filter_request_params( $params, $method, $timeout ) {
		return $this->request_filter->build_request_context(
			array(
				'method'    => $method,
				'body'      => $params,
				'sslverify' => true,
				'timeout'   => $timeout,
			)
		);
	}

	private function handle_json_response( WPML_TP_API_Request $request, $response ) {
		if ( $request->has_api_response() ) {
			if ( ! isset( $response->status->code ) || $response->status->code !== 0 ) {
				throw new WPML_TP_API_Exception(
					$this->generate_error_message_from_status_field( $response ),
					$request,
					$response
				);
			}
			$response = $response->response;
		}

		return $response;
	}

	private function generate_error_message_from_status_field( $response ) {
		$message = '';
		if ( isset( $response->status->message ) ) {
			if ( isset( $response->status->code ) ) {
				$message = '(' . $response->status->code . ') ';
			}
			$message .= $response->status->message;
		} else {
			$message = 'Unknown error';
		}

		return $message;
	}
}
