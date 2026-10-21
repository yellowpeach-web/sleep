<?php

class OTGS_Installer_Download_HTTP_Logger {

	const LOG_PREFIX = '[otgs-installer-download-http] ';

	private $download_url = '';
	private $context = array();

	public function start( $download_url, $context = array() ) {
		$this->download_url = $download_url;
		$this->context      = $context;

		add_action( 'http_api_debug', array( $this, 'log_response' ), 10, 5 );
	}

	public function stop() {
		remove_action( 'http_api_debug', array( $this, 'log_response' ), 10 );

		$this->download_url = '';
		$this->context      = array();
	}

	public function log_response( $response, $hook_context, $transport, $request_args, $url ) {
		if ( ! $this->is_download_response( $hook_context, $url ) ) {
			return;
		}

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			return;
		}

		error_log( self::LOG_PREFIX . $this->build_log_message( $response, $transport, $request_args, $url ) );
	}

	private function is_download_response( $hook_context, $url ) {
		return $hook_context === 'response' && $url === $this->download_url;
	}

	private function build_log_message( $response, $transport, $request_args, $url ) {
		$log = array_merge( $this->context, array(
			'event'     => 'package-http-response',
			'transport' => $transport,
		) );

		if ( is_wp_error( $response ) ) {
			$log['error_code']    = $response->get_error_code();
			$log['error_message'] = $response->get_error_message();
			$log['error_data']    = $response->get_error_data();
		} else {
			$log['response_code']    = wp_remote_retrieve_response_code( $response );
			$log['response_message'] = wp_remote_retrieve_response_message( $response );
			$log['headers']          = $this->get_selected_headers( $response );
			$log['body']             = $this->get_body( $response, $request_args );
		}

		list( $request_url, $query_string ) = $this->split_url( $url );
		$log['request_url']  = $request_url;
		$log['request_args'] = $query_string;

		return function_exists( 'wp_json_encode' ) ? wp_json_encode( $log ) : json_encode( $log );
	}

	private function split_url( $url ) {
		$url_parts    = wp_parse_url( $url );
		$query_string = isset( $url_parts['query'] ) ? $url_parts['query'] : '';
		unset( $url_parts['query'] );

		$url_without_query = function_exists( 'http_build_url' ) ? http_build_url( $url_parts ) : strtok( $url, '?' );

		return array( $url_without_query, $query_string );
	}

	private function get_selected_headers( $response ) {
		$headers = wp_remote_retrieve_headers( $response );
		$headers = $this->normalize_headers( $headers );

		$selected_headers = array();
		foreach ( array( 'content-type', 'content-length', 'location', 'server', 'x-cache', 'x-amz-cf-id' ) as $header ) {
			if ( isset( $headers[ $header ] ) ) {
				$selected_headers[ $header ] = $headers[ $header ];
			}
		}

		return $selected_headers;
	}

	private function normalize_headers( $headers ) {
		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			return $headers->getAll();
		}

		if ( $headers instanceof Traversable ) {
			return iterator_to_array( $headers );
		}

		return is_array( $headers ) ? $headers : array();
	}

	private function get_body( $response, $request_args ) {
		$body = '';

		if ( ! empty( $request_args['filename'] ) && file_exists( $request_args['filename'] ) && is_readable( $request_args['filename'] ) ) {
			$body = file_get_contents( $request_args['filename'] );
		}

		if ( ! $body ) {
			$body = wp_remote_retrieve_body( $response );
		}

		return is_string( $body ) ? $body : '';
	}
}
