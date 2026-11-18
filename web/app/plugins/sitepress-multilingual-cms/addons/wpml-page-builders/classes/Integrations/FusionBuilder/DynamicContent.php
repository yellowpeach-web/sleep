<?php

namespace WPML\Compatibility\FusionBuilder;

use WPML\Compatibility\BaseDynamicContent;

class DynamicContent extends BaseDynamicContent {

	protected $positions = [ 'before', 'after', 'fallback', 'singular_text', 'plural_text' ];

	public function decode_dynamic_content( $string, $encoding ) {
		if ( ! $string || is_array( $string ) ) {
			return $string;
		}

		if ( $this->is_dynamic_content( $string ) ) {
			$decodedData = $this->decode_field( $string );

			$decodedContent = [
				'dynamic-content' => [
					'value'     => $string,
					'translate' => false,
				],
			];

			foreach ( $decodedData['content_keys'] as $contentKey ) {
				foreach ( $this->positions as $position ) {
					if ( ! empty( $decodedData['data'][ $contentKey ][ $position ] ) ) {
						$field_key                    = $contentKey . '-' . $position;
						$decodedContent[ $field_key ] = [
							'value'     => $decodedData['data'][ $contentKey ][ $position ],
							'translate' => true,
						];
					}
				}
			}

			return $decodedContent;
		}

		return $string;
	}

	public function encode_dynamic_content( $string, $encoding ) {
		if ( is_array( $string ) && isset( $string['dynamic-content'] ) ) {
			$decodedData = $this->decode_field( $string['dynamic-content'] );

			foreach ( $decodedData['content_keys'] as $contentKey ) {
				foreach ( $this->positions as $position ) {
					$field_key = $contentKey . '-' . $position;
					if ( isset( $string[ $field_key ] ) ) {
						$decodedData['data'][ $contentKey ][ $position ] = $string[ $field_key ];
					}
				}
			}

			return $this->encode_field( $decodedData );
		}

		return $string;
	}

	protected function is_dynamic_content( $string ) {
		$decoded = json_decode( base64_decode( $string ), true );

		return is_array( $decoded ) && (bool) $this->getDynamicParamKeys( $decoded );
	}

	protected function decode_field( $string ) {
		$decoded = json_decode( base64_decode( $string ), true );

		return [
			'data'         => $decoded,
			'content_keys' => is_array( $decoded ) ? $this->getDynamicParamKeys( $decoded ) : [],
		];
	}

	private function getDynamicParamKeys( array $decodedParams ) {
		return array_keys(
			array_filter(
				$decodedParams,
				function ( $param ) {
					return is_array( $param ) && isset( $param['data'] );
				}
			)
		);
	}

	protected function encode_field( $decodedData ) {
		return base64_encode( wp_json_encode( $decodedData['data'], JSON_UNESCAPED_SLASHES ) );
	}
}
