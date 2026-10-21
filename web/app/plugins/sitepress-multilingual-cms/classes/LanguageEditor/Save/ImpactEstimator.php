<?php

namespace WPML\LanguageEditor\Save;

class ImpactEstimator {

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function estimate( array $change ) {
		$code = isset( $change['code'] ) ? (string) $change['code'] : '';

		return [
			'posts'   => $this->countPosts( $code ),
			'links'   => $this->countLinks( $change ),
			'strings' => $this->countStrings( $code ),
		];
	}

	private function countPosts( $code ) {
		if ( '' === $code ) {
			return 0;
		}
		$wpdb = $this->wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations WHERE language_code = %s", $code )
		);
	}

	private function countLinks( array $change ) {
		$old = isset( $change['display_code']['old'] ) ? (string) $change['display_code']['old'] : '';
		$new = isset( $change['display_code']['new'] ) ? (string) $change['display_code']['new'] : '';
		if ( '' === $old || $old === $new ) {
			return 0;
		}
		$wpdb    = $this->wpdb;
		$segment = '/' . trim( $old, '/' ) . '/';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_content LIKE %s",
				'%' . $wpdb->esc_like( $segment ) . '%'
			)
		);
	}

	private function countStrings( $code ) {
		if ( '' === $code ) {
			return 0;
		}
		$wpdb         = $this->wpdb;
		$stTable      = $wpdb->prefix . 'icl_string_translations';
		$stringsTable = $wpdb->prefix . 'icl_strings';

		if ( ! $this->tableExists( $stTable ) || ! $this->tableExists( $stringsTable ) ) {
			return 0;
		}

		$translations = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_string_translations WHERE language = %s", $code )
		);
		$sources = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_strings WHERE language = %s", $code )
		);
		return $translations + $sources;
	}

	private function tableExists( $table ) {
		$wpdb = $this->wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);
	}
}
