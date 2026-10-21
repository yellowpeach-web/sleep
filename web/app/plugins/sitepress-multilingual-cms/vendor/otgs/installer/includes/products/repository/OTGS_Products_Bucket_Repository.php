<?php

class OTGS_Products_Bucket_Repository {

	private $buckets = [];

	public function get_products_bucket_url( $api_url, $site_key, $site_url ) {
		$bucket = $this->get_bucket( $api_url, $site_key, $site_url );

		return isset( $bucket->url ) ? $bucket->url : null;
	}

	public function get_releases_bucket_url( $api_url, $site_key, $site_url ) {
		$bucket = $this->get_bucket( $api_url, $site_key, $site_url );

		return isset( $bucket->releases_url ) ? $bucket->releases_url : null;
	}

	private function get_bucket( $api_url, $site_key, $site_url ) {
		$cache_key = md5( $api_url . '|' . $site_key . '|' . $site_url );
		if ( array_key_exists( $cache_key, $this->buckets ) ) {
			return $this->buckets[ $cache_key ];
		}

		$args['body'] = [
			'action'   => 'product_bucket_url',
			'site_key' => $site_key,
			'site_url' => $site_url
		];

		$response = wp_remote_post( $api_url, $args );

		$response_data = $this->get_response_data( $response );

		$bucket = isset( $response_data->success ) && $response_data->success === true && isset( $response_data->bucket )
			? $response_data->bucket
			: null;

		$this->buckets[ $cache_key ] = $bucket;

		return $bucket;
	}

	private function get_response_data( $response ) {
		if (
			$response &&
			! is_wp_error( $response ) &&
			isset( $response['response']['code'] ) &&
			$response['response']['code'] == 200
		) {
			$body = wp_remote_retrieve_body( $response );
			if ( $body ) {
				return json_decode( $body );
			}
		}

		return null;
	}
}
