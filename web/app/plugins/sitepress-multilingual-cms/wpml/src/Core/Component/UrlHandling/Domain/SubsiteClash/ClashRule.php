<?php

namespace WPML\Core\Component\UrlHandling\Domain\SubsiteClash;

final class ClashRule {

	public static function inScope( MainSiteUrlState $state ): bool {
		return $state->isMultisite()
			&& ! $state->isSubdomainInstall()
			&& $state->isWpmlActive()
			&& $state->isSetupComplete();
	}

	public static function directoryCodes( MainSiteUrlState $state, bool $potential = false ): array {
		if ( ! self::inScope( $state ) ) {
			return [];
		}

		if ( ! $potential && MainSiteUrlState::FORMAT_DIRECTORIES !== $state->urlFormat() ) {
			return [];
		}

		$codes = [];
		foreach ( $state->languages() as $language ) {
			if ( $language->code() === $state->defaultLanguage() && ! $state->usesDirectoryForDefaultLanguage() ) {
				continue;
			}

			$code = self::directoryCode( $language );
			if ( '' !== $code ) {
				$codes[ $code ] = $code;
			}
		}

		$codes = array_values( $codes );
		sort( $codes, SORT_STRING );

		return $codes;
	}

	public static function candidatePaths( MainSiteUrlState $state ): array {
		if ( ! self::inScope( $state ) ) {
			return [];
		}

		$paths = [];
		foreach ( $state->languages() as $language ) {
			$code = self::directoryCode( $language );
			if ( '' !== $code ) {
				$path           = self::pathOf( $state, $code );
				$paths[ $path ] = $path;
			}
		}

		$paths = array_values( $paths );
		sort( $paths, SORT_STRING );

		return $paths;
	}

	public static function clashes( MainSiteUrlState $state, array $sites, bool $potential = false ): array {
		$codes = self::directoryCodes( $state, $potential );
		if ( ! $codes ) {
			return [];
		}

		$languagesByPath = [];
		foreach ( $state->languages() as $language ) {
			$code = self::directoryCode( $language );
			if ( in_array( $code, $codes, true ) ) {
				$languagesByPath[ self::pathOf( $state, $code ) ] = [ $language, $code ];
			}
		}

		$clashes = [];
		foreach ( $sites as $site ) {
			$path = self::pathKeyOf( $state, $site );
			if ( null !== $path && isset( $languagesByPath[ $path ] ) ) {
				list( $language, $code ) = $languagesByPath[ $path ];
				$clashes[]               = new Clash( $language, $code, $site );
			}
		}

		usort( $clashes, [ self::class, 'compare' ] );

		return $clashes;
	}

	public static function defaultLanguageClash( MainSiteUrlState $state, array $sites ) {
		if ( ! self::inScope( $state ) ) {
			return null;
		}

		foreach ( $state->languages() as $language ) {
			if ( $language->code() !== $state->defaultLanguage() ) {
				continue;
			}

			$code = self::directoryCode( $language );
			if ( '' === $code ) {
				return null;
			}

			$found = [];
			foreach ( $sites as $site ) {
				if ( self::pathOf( $state, $code ) === self::pathKeyOf( $state, $site ) ) {
					$found[] = new Clash( $language, $code, $site );
				}
			}
			usort( $found, [ self::class, 'compare' ] );

			return $found ? $found[0] : null;
		}

		return null;
	}

	public static function defaultFixApplies( MainSiteUrlState $state, array $clashes ): bool {
		if ( ! $state->usesDirectoryForDefaultLanguage() ) {
			return false;
		}

		foreach ( $clashes as $clash ) {
			if ( $clash->languageCode() === $state->defaultLanguage() ) {
				return true;
			}
		}

		return false;
	}

	private static function directoryCode( ActiveLanguage $language ): string {
		return strtolower( trim( $language->urlCode(), " \t\n\r\0\x0B/" ) );
	}

	private static function pathOf( MainSiteUrlState $state, string $code ): string {
		return strtolower( $state->path() ) . $code . '/';
	}

	private static function pathKeyOf( MainSiteUrlState $state, SitePath $site ) {
		if ( $site->id() === $state->mainSiteId() ) {
			return null;
		}

		if ( strtolower( $site->domain() ) !== strtolower( $state->domain() ) ) {
			return null;
		}

		return strtolower( $site->path() );
	}

	private static function compare( Clash $a, Clash $b ): int {
		return strcasecmp( $a->languageName(), $b->languageName() )
			?: strcmp( $a->directoryCode(), $b->directoryCode() )
			?: $a->siteId() <=> $b->siteId();
	}
}
