<?php

namespace WPML\UrlHandling\SubsiteClash;

use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\SitePath;

class LanguageHostNote {

	const ID_PREFIX = 'wpml-host-clash-note-';

	private $taken;

	public function __construct( array $taken ) {
		$this->taken = $taken;
	}

	public static function forShownDomains( array $domainsByLanguage ) {
		if ( ! is_multisite() ) {
			return new self( [] );
		}

		$hosts = [];
		foreach ( $domainsByLanguage as $code => $domain ) {
			$host = \WPML_Language_Domains::lookupHostOf( $domain );
			if ( null !== $host ) {
				$hosts[ $code ] = $host;
			}
		}

		return new self( Detector::hostRowsFor( $hosts ) );
	}

	public function printDescribedBy( $code ) {
		if ( isset( $this->taken[ $code ] ) ) {
			printf( ' aria-describedby="%s"', esc_attr( self::ID_PREFIX . $code ) );
		}
	}

	public function printNote( $code ) {
		if ( isset( $this->taken[ $code ] ) ) {
			printf(
				'<p id="%1$s" class="icl_error_text" style="white-space:normal">%2$s</p>',
				esc_attr( self::ID_PREFIX . $code ),
				wp_kses( self::text( $this->taken[ $code ] ), NoticeMarkup::ALLOWED_HTML )
			);
		}
	}

	private static function text( SitePath $site ) {
		$name     = esc_html( $site->name() );
		$statuses = NoticeMarkup::statusSuffix( $site->statuses() );

		if ( ! is_super_admin() ) {
			return sprintf(
				/* translators: Under a language domain on a multisite network, for a user who is not a network admin. 1: the name of the sub-site that has this address. 2: the sub-site's statuses in parentheses after a space, for example " (archived)", or nothing. */
				esc_html__( 'This address belongs to the sub-site “%1$s”%2$s. Change this domain, or ask your network admin to change the sub-site\'s address.', 'sitepress' ),
				$name,
				$statuses
			);
		}

		if ( $site->networkId() !== (int) get_current_network_id() ) {
			return sprintf(
				/* translators: Under a language domain on a multisite network, for a network admin, when the sub-site is in another network. 1: the name of the sub-site that has this address. 2: the sub-site's statuses in parentheses after a space, for example " (archived)", or nothing. */
				esc_html__( 'This address belongs to the sub-site “%1$s”%2$s. Change this domain or the sub-site\'s address.', 'sitepress' ),
				$name,
				$statuses
			);
		}

		return sprintf(
			/* translators: Under a language domain on a multisite network, for a network admin. 1: the name of the sub-site that has this address. 2: the sub-site's statuses in parentheses after a space, for example " (archived)", or nothing. 3: the URL of Network Admin -> Sites -> Edit Site for that sub-site. */
			__( 'This address belongs to the sub-site “%1$s”%2$s. Change this domain or <a href="%3$s">the sub-site\'s address</a>.', 'sitepress' ),
			$name,
			$statuses,
			esc_url( network_admin_url( 'site-info.php?id=' . $site->id() ) )
		);
	}
}
