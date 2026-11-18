<?php

namespace WPML\UrlHandling\SubsiteClash;

use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\Clash;
use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\LanguageUrlClash;
use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\SitePath;

class NoticeMarkup {

	const ALLOWED_HTML = [
		'a'      => [ 'href' => [] ],
		'code'   => [],
		'li'     => [],
		'p'      => [],
		'strong' => [],
	];

	public static function printSiteNotice( array $clashes, $isSuperAdmin, $defaultFixCode, $showSettingsLinks, $mainSitePath ) {
		self::printNotice(
			self::siteHeading(),
			self::adminLine(
				/* translators: A line in an error notice, shown when the default language's URLs (for example /en/) open a sub-site. %s: an address in code tags, for example /en/wp-json/. This site's admin screens load data from that address, and the sub-site answers instead. */
				esc_html__( 'Admin screens of this site can fail too. They load data from %s.', 'sitepress' ),
				(string) $defaultFixCode,
				(string) $mainSitePath
			),
			$clashes,
			(bool) $isSuperAdmin,
			self::fixes( (bool) $isSuperAdmin, (string) $defaultFixCode ),
			self::whyFormat(),
			$showSettingsLinks ? self::settingsLinks() : []
		);
	}

	public static function printNetworkNotice( array $clashes, $mainSiteName, $defaultFixCode, $mainSitePath, $focusSiteId = 0 ) {
		self::printNotice(
			self::networkHeading( $mainSiteName ),
			self::adminLine(
				/* translators: A line in an error notice in Network Admin, shown when the default language's URLs (for example /en/) open a sub-site. %s: an address in code tags, for example /en/wp-json/. The main site's admin screens load data from that address, and the sub-site answers instead. */
				esc_html__( 'Admin screens of the main site can fail too. They load data from %s.', 'sitepress' ),
				(string) $defaultFixCode,
				(string) $mainSitePath
			),
			$clashes,
			true,
			self::fixes( true, (string) $defaultFixCode ),
			self::whyFormat(),
			self::settingsLinks(),
			(int) $focusSiteId
		);
	}

	public static function printAddSiteHint( array $codes ) {
		if ( ! $codes ) {
			return;
		}

		$list = implode(
			esc_html( self::listSeparator() ),
			array_map(
				function ( $code ) {
					return '<code>' . esc_html( $code ) . '</code>';
				},
				$codes
			)
		);

		printf(
			'<p class="wpml-subsite-clash-hint">%s</p>',
			wp_kses(
				sprintf(
					esc_html(
						/* translators: Under the Network Admin "Add New Site" form. %s: one or more sub-site addresses in code tags, for example "en, es". The main site of the network uses them as language URL directories (/en/, /es/), so WordPress refuses them as site addresses. */
						_n(
							"The main site uses these addresses for its languages: %s. You can't use them for a new site.",
							"The main site uses these addresses for its languages: %s. You can't use them for a new site.",
							count( $codes ),
							'sitepress'
						)
					),
					$list
				),
				self::ALLOWED_HTML
			)
		);
	}

	public static function printHostSiteNotice( array $clashes, $isSuperAdmin, $showSettingsLink ) {
		self::printNotice(
			self::siteHeading(),
			'',
			$clashes,
			(bool) $isSuperAdmin,
			self::hostFixes( (bool) $isSuperAdmin ),
			self::hostWhyFormat(),
			$showSettingsLink ? self::hostLinks() : []
		);
	}

	public static function printHostNetworkNotice( array $clashes, $mainSiteName, $focusSiteId = 0 ) {
		self::printNotice(
			self::networkHeading( $mainSiteName ),
			'',
			$clashes,
			true,
			self::hostFixes( true ),
			self::hostWhyFormat(),
			self::hostLinks(),
			(int) $focusSiteId
		);
	}

	/**
	 * wpmldev-12231 (C): prints the hint under the Network Add Site form of a
	 * subdomain install.
	 *
	 * @param string[] $hosts The language hosts the form can create, sorted.
	 *
	 * @return void
	 */
	public static function printAddSiteHostHint( array $hosts ) {
		if ( ! $hosts ) {
			return;
		}

		$list = implode( esc_html( self::listSeparator() ), array_map( [ self::class, 'address' ], $hosts ) );

		printf(
			'<p class="wpml-subsite-clash-hint">%s</p>',
			wp_kses(
				sprintf(
					esc_html(
						/* translators: Under the Network Admin "Add New Site" form of a subdomain network. %s: one or more addresses in code tags, for example es.example.com. The main site uses them as language domains. WordPress still accepts them as site addresses, and such a site then answers that language's URLs. */
						_n(
							"This address is a language domain of the main site: %s. A new site with this address takes over that language's pages.",
							"These addresses are language domains of the main site: %s. A new site with one of these addresses takes over that language's pages.",
							count( $hosts ),
							'sitepress'
						)
					),
					$list
				),
				self::ALLOWED_HTML
			)
		);
	}

	private static function printNotice( $heading, $adminLine, array $clashes, $isSuperAdmin, array $fixes, $whyFormat, array $links, $focusSiteId = 0 ) {
		if ( ! $clashes ) {
			return;
		}

		$clashes = self::focusFirst( $clashes, $focusSiteId );

		$items = '';
		foreach ( $clashes as $clash ) {
			$item   = self::item( $clash, $isSuperAdmin );
			$items .= '<li>' . ( $focusSiteId && $clash->siteId() === $focusSiteId ? '<strong>' . $item . '</strong>' : $item ) . '</li>';
		}

		$siteAdminLine = $isSuperAdmin
			? ''
			: '<p>' . esc_html__( "Only a network admin can change a sub-site's address.", 'sitepress' ) . '</p>';

		$fixItems = '';
		foreach ( $fixes as $fix ) {
			$fixItems .= '<li>' . $fix . '</li>';
		}

		$why = sprintf( $whyFormat, self::address( $clashes[0]->address() ) );

		printf(
			'<div class="notice notice-error wpml-subsite-clash-notice"><h2>%1$s</h2><p>%2$s</p>%3$s'
			. '<ul class="wpml-subsite-clash-list" style="list-style:disc;margin-left:18px">%4$s</ul>%5$s',
			wp_kses( $heading, self::ALLOWED_HTML ),
			esc_html__( "WordPress sends these URLs to a sub-site before WPML runs. Visitors who open them don't see your pages.", 'sitepress' ),
			wp_kses( $adminLine, self::ALLOWED_HTML ),
			wp_kses( $items, self::ALLOWED_HTML ),
			wp_kses( $siteAdminLine, self::ALLOWED_HTML )
		);

		self::printLinks( $links );

		printf(
			'<details><summary>%1$s</summary><ul style="list-style:disc;margin-left:18px">%2$s</ul></details>'
			. '<details><summary>%3$s</summary><p>%4$s</p></details>'
			. '<p>%5$s</p></div>',
			esc_html__( 'How do I fix it?', 'sitepress' ),
			wp_kses( $fixItems, self::ALLOWED_HTML ),
			esc_html__( 'Why does this happen?', 'sitepress' ),
			wp_kses( $why, self::ALLOWED_HTML ),
			esc_html__( 'This message goes away when every address is fixed.', 'sitepress' )
		);
	}

	private static function printLinks( array $links ) {
		if ( ! $links ) {
			return;
		}

		echo '<p class="wpml-subsite-clash-actions">';
		$last = count( $links ) - 1;
		foreach ( array_values( $links ) as $index => $link ) {
			if ( $index < $last ) {
				printf( '<a href="%1$s" style="margin-right:12px">%2$s</a>', esc_url( $link['url'] ), wp_kses( $link['label'], self::ALLOWED_HTML ) );
			} else {
				printf( '<a href="%1$s">%2$s</a>', esc_url( $link['url'] ), wp_kses( $link['label'], self::ALLOWED_HTML ) );
			}
		}
		echo '</p>';
	}

	private static function settingsLinks() {
		return [
			[
				'url'   => admin_url( 'admin.php?page=tm/menu/settings&section=languages' ),
				/* translators: Link in the sub-site clash notice. It opens WPML -> Settings -> Languages, where each language's URL code can be changed. */
				'label' => esc_html__( 'Change language URL codes', 'sitepress' ),
			],
			[
				'url'   => admin_url( 'admin.php?page=tm/menu/settings&section=urls-and-seo' ),
				/* translators: Link in the sub-site clash notice. It opens WPML -> Settings -> URLs and SEO, where the language URL format is chosen. */
				'label' => esc_html__( 'Change URL format', 'sitepress' ),
			],
		];
	}

	private static function hostLinks() {
		return [
			[
				'url'   => admin_url( 'admin.php?page=tm/menu/settings&section=urls-and-seo' ),
				/* translators: Link in the notice about a sub-site that has the address of a language domain. It opens WPML -> Settings -> URLs and SEO, where the language domains and the language URL format are chosen. */
				'label' => esc_html__( 'Change language domains or URL format', 'sitepress' ),
			],
		];
	}

	private static function siteHeading() {
		/* translators: Heading of an error notice. Some URLs of this site's languages (for example /es/ or es.example.com) belong to a sub-site of the multisite network, so they show that sub-site instead of this site's pages. */
		return esc_html__( 'Some language URLs open a different site', 'sitepress' );
	}

	private static function networkHeading( $mainSiteName ) {
		return sprintf(
			/* translators: Heading of an error notice in Network Admin. %s: the name of the network's main site. Some URLs of its languages (for example /es/ or es.example.com) belong to a sub-site, so they show that sub-site instead of the main site's pages. */
			esc_html__( 'Some language URLs of “%s” open a sub-site', 'sitepress' ),
			esc_html( $mainSiteName )
		);
	}

	private static function hostWhyFormat() {
		/* translators: %s: a language domain in code tags, for example es.example.com. */
		return esc_html__( 'On a multisite network, WordPress looks for a site at the requested address first. It does this before any plugin runs. That is why %s opens the sub-site at that address.', 'sitepress' );
	}

	private static function whyFormat() {
		/* translators: %s: a language directory in code tags, for example /es/. */
		return esc_html__( 'On a multisite network, WordPress picks the site from the first part of the URL. It does this before any plugin runs. That is why %s opens the sub-site at that address.', 'sitepress' );
	}

	private static function focusFirst( array $clashes, $focusSiteId ) {
		if ( ! $focusSiteId ) {
			return array_values( $clashes );
		}

		$first = [];
		$rest  = [];
		foreach ( $clashes as $clash ) {
			if ( $clash->siteId() === $focusSiteId ) {
				$first[] = $clash;
			} else {
				$rest[] = $clash;
			}
		}

		return array_merge( $first, $rest );
	}

	private static function item( LanguageUrlClash $clash, $isSuperAdmin ) {
		$text = sprintf(
			/* translators: 1: a language name, for example Spanish. 2: that language's URL directory or domain in code tags, for example /es/ or es.example.com. 3: the name of a sub-site of the multisite network. */
			esc_html__( '%1$s: %2$s belongs to the sub-site “%3$s”.', 'sitepress' ),
			esc_html( $clash->languageName() ),
			self::address( $clash->address() ),
			esc_html( $clash->siteName() )
		);

		if ( $clash->isHiddenLanguage() ) {
			/* translators: Added after an item of the sub-site clash notice when the language is hidden. */
			$text .= ' ' . esc_html__( '(hidden language)', 'sitepress' );
		}

		$text .= self::statusSuffix( $clash->statuses() );

		if ( $isSuperAdmin && $clash->site()->networkId() === get_current_network_id() ) {
			$text .= ' <a href="' . esc_url( network_admin_url( 'site-info.php?id=' . $clash->siteId() ) ) . '">'
				/* translators: Link after an item of the sub-site clash notice. It opens Network Admin -> Sites -> Edit Site for that sub-site. */
				. esc_html__( 'Edit sub-site', 'sitepress' ) . '</a>';
		}

		return $text;
	}

	public static function statusSuffix( array $statuses ) {
		$labels = [
			/* translators: A sub-site status, shown in parentheses after an item of the sub-site clash notice. */
			SitePath::STATUS_ARCHIVED    => esc_html_x( 'archived', 'sub-site status', 'sitepress' ),
			/* translators: A sub-site status, shown in parentheses after an item of the sub-site clash notice. WordPress calls a deleted-but-kept site "deactivated". */
			SitePath::STATUS_DEACTIVATED => esc_html_x( 'deactivated', 'sub-site status', 'sitepress' ),
			/* translators: A sub-site status, shown in parentheses after an item of the sub-site clash notice. */
			SitePath::STATUS_SPAM        => esc_html_x( 'spam', 'sub-site status', 'sitepress' ),
		];

		$shown = [];
		foreach ( $labels as $status => $label ) {
			if ( in_array( $status, $statuses, true ) ) {
				$shown[] = $label;
			}
		}

		if ( ! $shown ) {
			return '';
		}

		return ' ' . sprintf(
			/* translators: Added after an item of the sub-site clash notice. %s: the sub-site's statuses, for example "archived" or "archived, spam". */
			esc_html_x( '(%s)', 'sub-site statuses', 'sitepress' ),
			implode( esc_html( self::listSeparator() ), $shown )
		);
	}

	private static function fixes( $isSuperAdmin, $defaultFixCode ) {
		$fixes = [
			self::addressFix( $isSuperAdmin ),
			esc_html__( 'Change the URL code of the language. All URLs in that language change. Old links to them then open the sub-site.', 'sitepress' ),
			self::formatFix(),
		];

		if ( '' !== $defaultFixCode ) {
			$fixes[] = sprintf(
				/* translators: %s: the default language's URL directory in code tags, for example /en/. “Use directory for default language” is the name of a checkbox in WPML -> Settings -> URLs and SEO; use the same words as that checkbox. */
				esc_html__( 'Turn off “Use directory for default language”. Your default language URLs then have no %s.', 'sitepress' ),
				self::directory( $defaultFixCode )
			);
		}

		return $fixes;
	}

	private static function hostFixes( $isSuperAdmin ) {
		return [
			self::addressFix( $isSuperAdmin ),
			/* translators: A fix in the notice about a sub-site that has the address of a language domain. */
			esc_html__( "Change the language's domain. The new domain must point to this site. All URLs in that language change. Old links to them then open the sub-site.", 'sitepress' ),
			self::formatFix(),
		];
	}

	private static function addressFix( $isSuperAdmin ) {
		return $isSuperAdmin
			? esc_html__( "Change the sub-site's address, or delete the sub-site. Links to that sub-site change too.", 'sitepress' )
			: esc_html__( "Ask your network admin to change the sub-site's address.", 'sitepress' );
	}

	private static function formatFix() {
		return esc_html__( 'Use another URL format, for example the language as a parameter. All language URLs change.', 'sitepress' );
	}

	public static function listSeparator() {
		return function_exists( 'wp_get_list_item_separator' )
			? wp_get_list_item_separator()
			/* translators: Separates the items of a list, for example the sub-site statuses "archived, spam". Used only on WordPress before 6.0. */
			: _x( ', ', 'list item separator', 'sitepress' );
	}

	private static function adminLine( $sentence, $defaultFixCode, $mainSitePath ) {
		if ( '' === $defaultFixCode ) {
			return '';
		}

		$base    = trim( $mainSitePath, '/' );
		$address = ( '' === $base ? '/' : '/' . $base . '/' ) . $defaultFixCode . '/' . trim( (string) rest_get_url_prefix(), '/' ) . '/';

		return '<p>' . sprintf( $sentence, '<code>' . esc_html( $address ) . '</code>' ) . '</p>';
	}

	private static function directory( $code ) {
		return self::address( '/' . $code . '/' );
	}

	private static function address( $address ) {
		return '<code>' . esc_html( $address ) . '</code>';
	}
}
