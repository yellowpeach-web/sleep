<?php

class WPML_Single_Url_Cache_Key {

	const ALGORITHM_VERSION = '5';

	public static function build( $blog_id, $generation, $url, $source_language, $target_language ) {
		return hash(
			'sha256',
			implode(
				"\0",
				[
					(string) $blog_id,
					self::ALGORITHM_VERSION,
					(string) $generation,
					(string) $url,
					(string) $source_language,
					(string) $target_language,
				]
			)
		);
	}

	public static function url_hash( $url ) {
		return hash( 'sha256', (string) $url );
	}
}
