<?php

namespace WPML\Media\TextScan;

use WPML\Remote\TrustedDestinations;

class HttpClassifierClient implements ClassifierClient {

	const URL_FILTER = 'wpml_media_text_scan_classifier_url';
	const PATH       = '/api/wpml/images/classify';
	const MAX_ITEMS  = 50;

	const VERDICTS         = [ 'translate', 'skip', 'unsure' ];
	const MAX_REASON_CHARS = 40;
	const MAX_MODEL_CHARS  = 80;

	private const BODY_VERSION = 1;

	private $defaultUrl;

	private $destinations;

	public function __construct( $defaultUrl = null, $destinations = null ) {
		$this->defaultUrl   = $defaultUrl ?: function () {
			return ( new \WPML_TM_ATE_AMS_Endpoints() )->get_endpoint_url( 'ate', self::PATH );
		};
		$this->destinations = $destinations ?: function () {
			return TrustedDestinations::forAteAndAms();
		};
	}

	public function classify( array $items, $purpose, $timeout ) {
		$url = $this->url();
		if ( '' === $url ) {
			throw ClassifierUnavailable::noEndpoint();
		}

		try {
			$url = call_user_func( $this->destinations )->assert( $url );
		} catch ( \InvalidArgumentException $e ) {
			throw ClassifierUnavailable::untrustedEndpoint();
		}

		$response = wp_remote_post(
			$url,
			TrustedDestinations::requestArgs(
				[
					'headers' => [ 'Content-Type' => 'application/json' ],
					'body'    => wp_json_encode( $this->body( $items, $purpose ) ),
					'timeout' => (int) $timeout,
				]
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( 'http_request_not_executed' === $response->get_error_code() ) {
				throw ClassifierUnavailable::blocked( esc_html( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
			}

			throw ClassifierUnavailable::transport( esc_html( $response->get_error_message() ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			throw ClassifierUnavailable::httpStatus( (int) $status );
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['results'] ) || ! is_array( $decoded['results'] ) ) {
			throw ClassifierUnavailable::badBody();
		}

		return $decoded['results'];
	}

	public function url() {
		$url = apply_filters( self::URL_FILTER, call_user_func( $this->defaultUrl ) );

		return is_scalar( $url ) ? (string) $url : '';
	}

	public function body( array $items, $purpose ) {
		$out = [];
		foreach ( array_slice( array_values( $items ), 0, self::MAX_ITEMS ) as $item ) {
			$item  = is_array( $item ) ? $item : [];
			$out[] = [
				'id'        => isset( $item['id'] ) ? (int) $item['id'] : 0,
				'image'     => self::text( $item, 'image' ),
				'mime'      => Thumbnail::MIME,
				'file_name' => self::text( $item, 'file_name' ),
				'alt'       => self::text( $item, 'alt' ),
				'title'     => self::text( $item, 'title' ),
			];
		}

		return [
			'v'       => self::BODY_VERSION,
			'purpose' => (string) $purpose,
			'site'    => [
				'url'          => home_url(),
				'wpml_version' => defined( 'ICL_SITEPRESS_VERSION' ) ? (string) constant( 'ICL_SITEPRESS_VERSION' ) : '',
			],
			'items'   => $out,
		];
	}

	public static function validResults( $results, array $requestIds ) {
		if ( ! is_array( $results ) ) {
			return [];
		}

		$asked = array_flip( array_map( 'intval', $requestIds ) );
		$valid = [];
		foreach ( $results as $result ) {
			if ( ! is_array( $result ) || ! isset( $result['id'] ) || ! is_int( $result['id'] ) ) {
				continue;
			}
			$id = $result['id'];
			if ( ! isset( $asked[ $id ] ) || isset( $valid[ $id ] ) ) {
				continue;
			}
			if ( ! isset( $result['verdict'] ) || ! in_array( $result['verdict'], self::VERDICTS, true ) ) {
				continue;
			}
			if ( ! self::isBoundedText( $result, 'reason', self::MAX_REASON_CHARS ) ) {
				continue;
			}
			if ( ! self::isBoundedText( $result, 'model', self::MAX_MODEL_CHARS ) ) {
				continue;
			}
			$confidence = isset( $result['confidence'] ) ? $result['confidence'] : null;
			if ( ! ( is_int( $confidence ) || is_float( $confidence ) ) || $confidence < 0 || $confidence > 1 ) {
				continue;
			}

			$valid[ $id ] = [
				'id'         => $id,
				'verdict'    => $result['verdict'],
				'reason'     => $result['reason'],
				'confidence' => (float) $confidence,
				'model'      => $result['model'],
			];
		}

		return $valid;
	}

	private static function isBoundedText( array $result, $field, $max ) {
		if ( ! isset( $result[ $field ] ) || ! is_string( $result[ $field ] ) || '' === $result[ $field ] ) {
			return false;
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $result[ $field ], 'UTF-8' ) : strlen( $result[ $field ] );

		return $length <= $max;
	}

	private static function text( array $item, $field ) {
		return isset( $item[ $field ] ) && is_scalar( $item[ $field ] ) ? (string) $item[ $field ] : '';
	}
}
