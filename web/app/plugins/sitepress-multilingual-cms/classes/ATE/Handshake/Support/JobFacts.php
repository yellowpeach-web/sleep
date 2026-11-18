<?php

namespace WPML\TM\ATE\Handshake\Support;

class JobFacts {

	public static function of( array $jobIds ) {
		global $wpdb;

		$jobIds = array_values( array_unique( array_filter( array_map( 'intval', $jobIds ) ) ) );

		if ( ! $jobIds ) {
			return [];
		}

		$suppressed = $wpdb->suppress_errors( true );

		try {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT j.job_id, j.title, j.wpml_words_to_translate_count AS words, t.language_code, e.error_type, e.error_message FROM {$wpdb->prefix}icl_translate_job j LEFT JOIN {$wpdb->prefix}icl_translation_status s ON s.rid = j.rid LEFT JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = s.translation_id LEFT JOIN {$wpdb->prefix}icl_translate_unsolvable_jobs e ON e.job_id = j.job_id WHERE j.job_id IN (" . implode( ',', array_fill( 0, count( $jobIds ), '%d' ) ) . ')', $jobIds ) );
		} finally {
			$wpdb->suppress_errors( $suppressed );
		}

		$out = [];

		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			$out[ (int) $row->job_id ] = [
				'title'         => (string) $row->title,
				'language'      => self::languageName( (string) $row->language_code ),
				'words'         => null === $row->words ? null : (int) $row->words,
				'error_type'    => null === $row->error_type ? '' : (string) $row->error_type,
				'error_message' => null === $row->error_message ? '' : (string) $row->error_message,
			];
		}

		return $out;
	}

	private static function languageName( $code ) {
		global $sitepress;

		if ( '' !== $code && is_object( $sitepress ) && method_exists( $sitepress, 'get_display_language_name' ) ) {
			$name = (string) $sitepress->get_display_language_name( $code );

			if ( '' !== $name ) {
				return $name;
			}
		}

		return $code;
	}

	public static function registration() {
		$installer = function_exists( 'OTGS_Installer' ) ? OTGS_Installer() : null;

		$registered = is_object( $installer ) && method_exists( $installer, 'get_registered_site_url' )
			? (string) $installer->get_registered_site_url( 'wpml' )
			: '';
		$current    = is_object( $installer ) && method_exists( $installer, 'get_installer_site_url' )
			? (string) $installer->get_installer_site_url( 'wpml' )
			: (string) get_site_url();

		return [
			'registered' => $registered,
			'current'    => $current,
		];
	}
}
