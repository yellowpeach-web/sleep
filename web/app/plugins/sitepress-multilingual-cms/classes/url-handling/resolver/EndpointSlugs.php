<?php

namespace WPML\UrlHandling\Resolver;

class EndpointSlugs {

	private $sitepress;

	private $wpdb;

	private $strings;

	private $forms = [];

	public function __construct( \SitePress $sitepress, ?\wpdb $wpdb = null ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;
	}

	public function in_language( $slug, $source_language, $target_language ) {
		$registered = $this->registered_names();
		$string     = $this->find_string( $slug, $source_language, $target_language );

		if ( $string && $this->is_registered( $string, $registered ) ) {
			return $this->form( $string, $target_language );
		}

		return isset( $registered[ $slug ] ) ? $slug : null;
	}

	private function find_string( $slug, $source_language, $target_language ) {
		$strings = $this->strings();
		if ( ! $strings ) {
			return null;
		}

		$other_languages = array_diff(
			array_keys( $this->sitepress->get_active_languages() ),
			[ $source_language, $target_language ]
		);

		foreach ( array_merge( [ $source_language, null, $target_language ], $other_languages ) as $language ) {
			foreach ( $strings as $string ) {
				if ( $slug === $this->form( $string, $language ) ) {
					return $string;
				}
			}
		}

		return null;
	}

	private function is_registered( array $string, array $registered ) {
		foreach ( array_merge( [ null ], array_keys( $this->sitepress->get_active_languages() ) ) as $language ) {
			if ( isset( $registered[ $this->form( $string, $language ) ] ) ) {
				return true;
			}
		}

		return false;
	}

	private function form( array $string, $language ) {
		if ( null === $language ) {
			return rawurldecode( $string['value'] );
		}

		$key = $string['name'] . "\0" . $string['value'] . "\0" . $language;
		if ( ! array_key_exists( $key, $this->forms ) ) {
			$translation = apply_filters(
				'wpml_translate_single_string',
				$string['value'],
				\WPML_Endpoints_Support::STRING_CONTEXT,
				$string['name'],
				$language
			);

			$this->forms[ $key ] = rawurldecode(
				is_string( $translation ) && '' !== $translation ? $translation : $string['value']
			);
		}

		return $this->forms[ $key ];
	}

	private function registered_names() {
		global $wp_rewrite;

		$endpoints = isset( $wp_rewrite->endpoints ) && is_array( $wp_rewrite->endpoints ) ? $wp_rewrite->endpoints : [];
		$all_names = [];
		foreach ( $endpoints as $endpoint ) {
			if ( isset( $endpoint[1] ) ) {
				$all_names[ (string) $endpoint[1] ] = true;
			}
		}

		$names = [];
		foreach ( $endpoints as $endpoint ) {
			if (
				isset( $endpoint[0], $endpoint[1] )
				&& ( (int) $endpoint[0] & ( EP_PERMALINK | EP_PAGES ) )
				&& ! $this->is_request_language_alias( $endpoint, $all_names )
			) {
				$names[ rawurldecode( (string) $endpoint[1] ) ] = true;
			}
		}

		return $names;
	}

	private function is_request_language_alias( array $endpoint, array $all_names ) {
		return isset( $endpoint[2] )
			&& ( EP_ROOT | EP_PAGES ) === (int) $endpoint[0]
			&& (string) $endpoint[2] !== (string) $endpoint[1]
			&& isset( $all_names[ (string) $endpoint[2] ] );
	}

	private function strings() {
		if ( null !== $this->strings ) {
			return $this->strings;
		}

		$this->strings = [];
		if ( ! $this->wpdb || ! $this->is_string_translation_active() ) {
			return $this->strings;
		}

		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT name, value FROM {$wpdb->prefix}icl_strings WHERE context = %s",
				\WPML_Endpoints_Support::STRING_CONTEXT
			)
		);

		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			if ( isset( $row->name, $row->value ) && '' !== (string) $row->value ) {
				$this->strings[] = [
					'name'  => (string) $row->name,
					'value' => (string) $row->value,
				];
			}
		}

		return $this->strings;
	}

	protected function is_string_translation_active() {
		return function_exists( 'icl_get_string_id' );
	}
}
