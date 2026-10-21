<?php

namespace WPML\Localization;

class CatalogFallbackResolver {

	private $domain_roots;

	private $locale_inventory;

	private $canonical_paths = [];

	public function __construct( array $domain_roots, CatalogLocaleInventory $locale_inventory ) {
		$this->domain_roots     = $domain_roots;
		$this->locale_inventory = $locale_inventory;
	}

	public function resolve_php_catalog( $file, $domain ) {
		$locale = $this->php_locale_from_filename( $file, $domain );

		return $this->resolve( $file, $domain, $locale, true );
	}

	public function resolve_script_catalog( $file, $handle, $domain ) {
		$locale = $this->script_locale_from_filename( $file, $handle, $domain );

		return $this->resolve( $file, $domain, $locale, false );
	}

	private function resolve( $file, $domain, $requested_locale, $allow_php_catalog ) {
		if ( ! $requested_locale ) {
			return CatalogResolution::unchanged( $file );
		}

		$package_root = $this->package_root_for( $file, $domain );

		if ( ! $package_root || $this->catalog_exists( $file, $allow_php_catalog ) ) {
			return CatalogResolution::unchanged( $file, $requested_locale );
		}

		$fallback_locale = $this->locale_inventory->find_fallback_locale(
			$package_root,
			$domain,
			$requested_locale
		);

		if ( ! $fallback_locale ) {
			return CatalogResolution::unchanged( $file, $requested_locale );
		}

		$fallback_file = $this->replace_locale( $file, $requested_locale, $fallback_locale );

		if ( ! $fallback_file || ! $this->catalog_exists( $fallback_file, $allow_php_catalog ) ) {
			return CatalogResolution::unchanged( $file, $requested_locale );
		}

		return CatalogResolution::fallback( $fallback_file, $requested_locale );
	}

	private function catalog_exists( $file, $allow_php_catalog ) {
		if ( is_readable( $file ) ) {
			return true;
		}

		if ( ! $allow_php_catalog || '.mo' !== substr( $file, -3 ) ) {
			return false;
		}

		$php_file = substr( $file, 0, -3 ) . '.l10n.php';

		return is_readable( $php_file );
	}

	private function replace_locale( $file, $requested_locale, $fallback_locale ) {
		$filename = basename( $file );
		$bounds   = $this->locale_bounds( $filename, $requested_locale );

		if ( ! $bounds ) {
			return null;
		}

		$filename = substr_replace( $filename, $fallback_locale, $bounds['offset'], $bounds['length'] );

		return dirname( $file ) . DIRECTORY_SEPARATOR . $filename;
	}

	private function php_locale_from_filename( $file, $domain ) {
		if ( ! is_string( $file ) ) {
			return null;
		}

		$filename = basename( $file );
		$prefix   = $domain . '-';

		if ( 0 !== strpos( $filename, $prefix ) || '.mo' !== substr( $filename, -3 ) ) {
			return null;
		}

		$locale = substr( $filename, strlen( $prefix ), -3 );

		return $this->is_locale( $locale ) ? $locale : null;
	}

	private function script_locale_from_filename( $file, $handle, $domain ) {
		$filename = basename( $file );
		$locale   = $this->between( $filename, $domain . '-', '-' . $handle . '.json' );

		if ( $this->is_locale( $locale ) ) {
			return $locale;
		}

		$locale = $this->between( $filename, $domain . '-' . $handle . '-', '.json' );

		if ( $this->is_locale( $locale ) ) {
			return $locale;
		}

		return $this->locale_from_wordpress_hash_filename( $filename, $domain );
	}

	private function locale_from_wordpress_hash_filename( $filename, $domain ) {
		$domain_prefix = $domain . '-';

		if ( 0 !== strpos( $filename, $domain_prefix ) ) {
			return null;
		}

		$filename_without_domain = substr( $filename, strlen( $domain_prefix ) );

		if ( ! preg_match( '/^(.+)-[a-f0-9]{32}\.json$/', $filename_without_domain, $matches ) ) {
			return null;
		}

		return $this->is_locale( $matches[1] ) ? $matches[1] : null;
	}

	private function between( $value, $prefix, $suffix ) {
		if ( 0 !== strpos( $value, $prefix ) || ! $this->ends_with( $value, $suffix ) ) {
			return null;
		}

		return substr( $value, strlen( $prefix ), strlen( $value ) - strlen( $prefix ) - strlen( $suffix ) );
	}

	private function locale_bounds( $filename, $locale ) {
		$pattern = '/(?<![A-Za-z0-9])' . preg_quote( $locale, '/' ) . '(?![A-Za-z0-9])/';

		if ( ! preg_match( $pattern, $filename, $matches, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		return [
			'offset' => $matches[0][1],
			'length' => strlen( $matches[0][0] ),
		];
	}

	private function is_locale( $locale ) {
		return is_string( $locale )
			&& (bool) preg_match( '/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $locale );
	}

	private function ends_with( $value, $suffix ) {
		return '' === $suffix || substr( $value, -strlen( $suffix ) ) === $suffix;
	}

	private function package_root_for( $file, $domain ) {
		if ( empty( $this->domain_roots[ $domain ] ) ) {
			return null;
		}

		$file = $this->canonical_path( $file );

		foreach ( $this->domain_roots[ $domain ] as $root ) {
			$root = trailingslashit( $this->canonical_path( $root ) );

			if ( 0 === strpos( $file, $root ) ) {
				return untrailingslashit( $root );
			}
		}

		return null;
	}

	private function canonical_path( $path ) {
		if ( isset( $this->canonical_paths[ $path ] ) ) {
			return $this->canonical_paths[ $path ];
		}

		$real_path = $this->real_path_for_existing_directory( $path );

		$this->canonical_paths[ $path ] = wp_normalize_path( $real_path ? $real_path : $path );

		return $this->canonical_paths[ $path ];
	}

	private function real_path_for_existing_directory( $path ) {
		if ( is_dir( $path ) ) {
			return realpath( $path );
		}

		$directory = realpath( dirname( $path ) );

		return $directory ? $directory . '/' . basename( $path ) : false;
	}
}
