<?php

class WPML_Rewrite_Rules_Filter {

	private $active_url_codes;

	private $filtered_root;

	private $real_root;

	public function __construct( array $active_url_codes, $filtered_home_url, $real_home_url ) {
		$this->active_url_codes = $active_url_codes;
		$this->filtered_root    = self::root_of( $filtered_home_url );
		$this->real_root        = self::root_of( $real_home_url );
	}

	public function restore_home_root( $rules ) {
		if ( ! $this->is_language_shaped() ) {
			return $rules;
		}

		$filtered = preg_quote( $this->filtered_root, '/' );
		$real     = addcslashes( $this->real_root, '\\$' );

		$result = preg_replace(
			[
				'/^(RewriteBase\h+)' . $filtered . '(?=\h*\r?$)/m',
				'/^(RewriteRule\h+\S+\h+)' . $filtered . '/m',
			],
			[ '${1}' . $real, '${1}' . $real ],
			$rules
		);

		return null === $result ? $rules : $result;
	}

	private static function root_of( $url ) {
		$path = trim( (string) wp_parse_url( (string) $url, PHP_URL_PATH ), '/' );

		return '' === $path ? '/' : '/' . $path . '/';
	}

	private function is_language_shaped() {
		foreach ( $this->active_url_codes as $code ) {
			$code = (string) $code;
			if ( '' !== $code && $this->filtered_root === $this->real_root . $code . '/' ) {
				return true;
			}
		}

		return false;
	}
}
