<?php

namespace WPML\TM\XLIFF;

class PortableFileName {

	const FALLBACK = 'site';

	public static function component( $text, $fallback = self::FALLBACK ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		$text = (string) preg_replace( '/[<>:"\/\\\\|?*\x00-\x1F\x7F]+/u', '-', $text );
		$text = (string) preg_replace( '/^[\s\-]+|[\s.\-]+$/u', '', $text );

		if ( preg_match( '/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(\.|$)/i', $text ) ) {
			$text = (string) preg_replace( '/^([^.]+)/', '$1_', $text );
		}

		return '' === $text ? $fallback : $text;
	}

	public static function forJob( $site_name, $job_id ) {
		return self::component( $site_name ) . '-translation-job-' . (int) $job_id . '.xliff';
	}

	public static function forArchive( $site_name, array $job_ids ) {
		$job_ids = array_map( 'intval', $job_ids );
		$min_job = min( $job_ids );
		$max_job = max( $job_ids );
		$range   = $max_job === $min_job ? (string) $max_job : $min_job . '-' . $max_job;

		return self::component( $site_name ) . '-translation-job-' . $range . '.zip';
	}
}
