<?php

namespace WPML\Localization;

class CatalogLocaleInventory {

	private const PREFERRED_FALLBACKS = [
		'pt_ao' => 'pt_pt',
		'zh_hk' => 'zh_tw',
	];

	private $available_locales = [];

	public function find_fallback_locale( $package_root, $domain, $requested_locale ) {
		$language = $this->language_code( $requested_locale );
		$locales  = $this->locales_for_language( $package_root, $domain, $language );

		unset( $locales[ $this->normalize_locale( $requested_locale ) ] );

		$preferred_fallback = $this->preferred_fallback( $requested_locale, $locales );

		if ( $preferred_fallback ) {
			return $preferred_fallback;
		}

		return 1 === count( $locales ) ? reset( $locales ) : null;
	}

	private function preferred_fallback( $requested_locale, array $available_locales ) {
		$requested_locale = $this->normalize_locale( $requested_locale );

		if ( ! isset( self::PREFERRED_FALLBACKS[ $requested_locale ] ) ) {
			return null;
		}

		$preferred_locale = self::PREFERRED_FALLBACKS[ $requested_locale ];

		return isset( $available_locales[ $preferred_locale ] )
			? $available_locales[ $preferred_locale ]
			: null;
	}

	private function locales_for_language( $package_root, $domain, $language ) {
		$locales_by_language = $this->locales_by_language( $package_root, $domain );

		return isset( $locales_by_language[ $language ] )
			? $locales_by_language[ $language ]
			: [];
	}

	private function locales_by_language( $package_root, $domain ) {
		$cache_key = $package_root . '|' . $domain;

		if ( isset( $this->available_locales[ $cache_key ] ) ) {
			return $this->available_locales[ $cache_key ];
		}

		$this->available_locales[ $cache_key ] = $this->read_catalog_locales( $package_root, $domain );

		return $this->available_locales[ $cache_key ];
	}

	private function read_catalog_locales( $package_root, $domain ) {
		$locales = [];

		foreach ( $this->catalog_files( $package_root, $domain ) as $catalog_file ) {
			$locale = $this->locale_from_catalog_filename( basename( $catalog_file ), $domain );

			if ( ! $locale ) {
				continue;
			}

			$language = $this->language_code( $locale );
			$locales[ $language ][ $this->normalize_locale( $locale ) ] = $locale;
		}

		return $locales;
	}

	private function catalog_files( $package_root, $domain ) {
		$patterns = [
			$package_root . '/' . $domain . '-*.mo',
			$package_root . '/' . $domain . '-*.l10n.php',
		];
		$files    = [];

		foreach ( $patterns as $pattern ) {
			$found = glob( $pattern );

			if ( is_array( $found ) ) {
				$files = array_merge( $files, $found );
			}
		}

		return $files;
	}

	private function locale_from_catalog_filename( $filename, $domain ) {
		$filename = $this->use_mo_extension( $filename );
		$prefix   = $domain . '-';

		if ( 0 !== strpos( $filename, $prefix ) || '.mo' !== substr( $filename, -3 ) ) {
			return null;
		}

		$locale = substr( $filename, strlen( $prefix ), -3 );

		return $this->is_locale( $locale ) ? $locale : null;
	}

	private function use_mo_extension( $filename ) {
		return '.l10n.php' === substr( $filename, -9 )
			? substr( $filename, 0, -9 ) . '.mo'
			: $filename;
	}

	private function is_locale( $locale ) {
		return (bool) preg_match( '/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $locale );
	}

	private function language_code( $locale ) {
		$parts = preg_split( '/[_-]/', $locale );

		return strtolower( $parts[0] );
	}

	private function normalize_locale( $locale ) {
		return str_replace( '-', '_', strtolower( $locale ) );
	}
}
