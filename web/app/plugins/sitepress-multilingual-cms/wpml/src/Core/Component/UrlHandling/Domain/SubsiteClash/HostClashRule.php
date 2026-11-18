<?php

namespace WPML\Core\Component\UrlHandling\Domain\SubsiteClash;

final class HostClashRule {

	const WWW = 'www.';

	public static function inScope( SiteLanguageHosts $state ): bool {
		return $state->isMultisite()
			&& $state->hasSunrise()
			&& $state->isWpmlActive()
			&& $state->isSetupComplete();
	}

	public static function languageHosts( SiteLanguageHosts $state ): array {
		if ( ! self::inScope( $state ) || SiteLanguageHosts::FORMAT_DOMAINS !== $state->urlFormat() ) {
			return [];
		}

		$hosts = [];
		foreach ( $state->languageHosts() as $languageHost ) {
			if ( $languageHost->language()->code() !== $state->defaultLanguage() && '' !== $languageHost->host() ) {
				$hosts[] = $languageHost;
			}
		}

		return $hosts;
	}

	public static function candidateDomains( array $hosts ): array {
		$domains = [];
		foreach ( $hosts as $host ) {
			foreach ( self::domainsOf( $host ) as $domain ) {
				$domains[ $domain ] = $domain;
			}
		}

		$domains = array_values( $domains );
		sort( $domains, SORT_STRING );

		return $domains;
	}

	public static function rowFor( string $host, int $siteId, string $sitePath, array $rows ) {
		$domains  = self::domainsOf( $host );
		$sitePath = strtolower( $sitePath );

		$matching = [];
		foreach ( $rows as $row ) {
			$path = strtolower( $row->path() );
			if ( in_array( strtolower( $row->domain() ), $domains, true ) && ( '/' === $path || $sitePath === $path ) ) {
				$matching[] = $row;
			}
		}

		if ( ! $matching ) {
			return null;
		}

		usort(
			$matching,
			function ( SitePath $a, SitePath $b ) {
				return strlen( $b->domain() ) <=> strlen( $a->domain() )
					?: strlen( $b->path() ) <=> strlen( $a->path() )
					?: $a->id() <=> $b->id();
			}
		);

		return $matching[0]->id() === $siteId ? null : $matching[0];
	}

	public static function clashes( SiteLanguageHosts $state, array $rows ): array {
		$clashes = [];
		foreach ( self::languageHosts( $state ) as $languageHost ) {
			$row = self::rowFor( $languageHost->host(), $state->siteId(), $state->sitePath(), $rows );
			if ( $row ) {
				$clashes[] = new HostClash( $languageHost->language(), $languageHost->host(), $row );
			}
		}

		usort( $clashes, [ self::class, 'compare' ] );

		return $clashes;
	}

	public static function rowsForShownHosts( SiteLanguageHosts $state, array $hostsByCode, array $rows ): array {
		if ( ! self::inScope( $state ) ) {
			return [];
		}

		$taken = [];
		foreach ( $hostsByCode as $code => $host ) {
			if ( $code === $state->defaultLanguage() || '' === $host ) {
				continue;
			}

			$row = self::rowFor( $host, $state->siteId(), $state->sitePath(), $rows );
			if ( $row ) {
				$taken[ $code ] = $row;
			}
		}

		return $taken;
	}

	public static function hostsAddSiteCanCreate( SiteLanguageHosts $state, string $networkDomain ): array {
		$base = strtolower( $networkDomain );
		if ( 0 === strpos( $base, self::WWW ) ) {
			$base = substr( $base, strlen( self::WWW ) );
		}

		$hosts = [];
		foreach ( self::languageHosts( $state ) as $languageHost ) {
			$host = strtolower( $languageHost->host() );
			if ( 1 === preg_match( '/^[a-z0-9-]+\.' . preg_quote( $base, '/' ) . '$/D', $host ) ) {
				$hosts[ $host ] = $host;
			}
		}

		$hosts = array_values( $hosts );
		sort( $hosts, SORT_STRING );

		return $hosts;
	}

	private static function domainsOf( string $host ): array {
		$host = strtolower( $host );

		return 0 === strpos( $host, self::WWW ) ? [ $host, substr( $host, strlen( self::WWW ) ) ] : [ $host ];
	}

	private static function compare( HostClash $a, HostClash $b ): int {
		return strcasecmp( $a->languageName(), $b->languageName() )
			?: strcmp( $a->address(), $b->address() )
			?: $a->siteId() <=> $b->siteId();
	}
}
