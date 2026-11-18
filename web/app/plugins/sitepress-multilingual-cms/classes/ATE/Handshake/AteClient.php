<?php

namespace WPML\TM\ATE\Handshake;

use WPML\Remote\TrustedDestinations;
use WPML\TM\ATE\API\FingerprintGenerator;
use function WPML\Container\make;

class AteClient {

	const ENDPOINT_DECLINE       = '/api/wpml/jobs/decline';
	const ENDPOINT_RETRY_CLOSURE = '/api/wpml/jobs/retry_closure';
	const ENDPOINT_STATE         = '/api/wpml/handshake/state';
	const ENDPOINT_PROBE         = '/api/wpml/handshake/probe';
	const ENDPOINT_REPORT        = '/api/wpml/handshake/report';

	const REPORT_TIMEOUT = 5;

	const STATE_TIMEOUT = 5;

	const PROBE_TIMEOUT = 25;

	public function inFlight( $leaveOutBackedOffManualJobs = false ) {
		return make( \WPML_TM_ATE_Job_Repository::class )->get_jobs_to_sync_with_element_ids( true, (bool) $leaveOutBackedOffManualJobs );
	}

	public function syncAll( array $ateJobIds, array $elementIds, $inFlightTotal = null ) {
		$repository = make( \WPML_TM_ATE_Job_Repository::class );
		$repository->increment_ate_sync_count( $ateJobIds );

		return make( \WPML_TM_ATE_API::class )->sync_all(
			$ateJobIds,
			isset( $elementIds['postIds'] ) ? $elementIds['postIds'] : [],
			isset( $elementIds['stringIds'] ) ? $elementIds['stringIds'] : [],
			isset( $elementIds['packageIds'] ) ? $elementIds['packageIds'] : [],
			isset( $elementIds['termIds'] ) ? $elementIds['termIds'] : [],
			$inFlightTotal
		);
	}

	public function decline( array $body ) {
		return $this->post( self::ENDPOINT_DECLINE, $body );
	}

	public function retryClosure( $ateJobId ) {
		return $this->post( self::ENDPOINT_RETRY_CLOSURE, [ 'job_id' => (int) $ateJobId ] );
	}

	public function state() {
		return $this->request( 'GET', self::ENDPOINT_STATE, null, self::STATE_TIMEOUT );
	}

	public function probe() {
		return $this->request( 'POST', self::ENDPOINT_PROBE, null, self::PROBE_TIMEOUT );
	}

	public function report( array $reports ) {
		return $this->request( 'POST', self::ENDPOINT_REPORT, [ 'reports' => array_values( $reports ) ], self::REPORT_TIMEOUT );
	}

	protected function post( $path, array $body ) {
		return $this->request( 'POST', $path, $body, 30 );
	}

	protected function request( $method, $path, $body, $timeout ) {
		try {
			$endpoints = make( \WPML_TM_ATE_AMS_Endpoints::class );
			$url       = $endpoints->get_endpoint_url( \WPML_TM_ATE_AMS_Endpoints::SERVICE_ATE, $path );
			$signed    = make( \WPML_TM_ATE_Authentication::class )->get_signed_url_with_parameters( $method, $url, $body );

			if ( is_wp_error( $signed ) ) {
				return [ 'code' => 0, 'body' => null, 'error' => $signed->get_error_code() ];
			}

			$signed = TrustedDestinations::forAteAndAms( $endpoints )->assert( $signed );

			$response = wp_remote_request( $signed, TrustedDestinations::requestArgs( self::requestArgs( $method, $body, $timeout ) + [ 'headers' => $this->headers() ] ) );
		} catch ( \Throwable $e ) {
			return [ 'code' => 0, 'body' => null, 'error' => get_class( $e ) ];
		}

		if ( is_wp_error( $response ) ) {
			return [ 'code' => 0, 'body' => null, 'error' => (string) $response->get_error_code() ];
		}

		return [
			'code'  => (int) wp_remote_retrieve_response_code( $response ),
			'body'  => json_decode( (string) wp_remote_retrieve_body( $response ), true ),
			'error' => '',
		];
	}

	public static function requestArgs( $method, $body, $timeout ) {
		$args = [
			'method'  => (string) $method,
			'timeout' => (int) $timeout,
		];

		if ( $body && 'GET' !== strtoupper( (string) $method ) ) {
			$args['body'] = (string) wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		return $args;
	}

	private function headers() {
		$headers = [
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		];

		$fingerprint = '';

		try {
			$fingerprint = (string) make( FingerprintGenerator::class )->getSiteFingerprint();
		} catch ( \Throwable $e ) {
			$fingerprint = '';
		}

		if ( '' !== $fingerprint ) {
			$headers[ FingerprintGenerator::SITE_FINGERPRINT_HEADER ] = $fingerprint;
		}

		if ( Declaration::active() ) {
			$headers[ Declaration::HEADER ] = (string) Declaration::requestId();
		}

		return $headers;
	}
}
