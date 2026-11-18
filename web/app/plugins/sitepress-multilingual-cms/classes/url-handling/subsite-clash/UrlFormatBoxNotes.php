<?php

namespace WPML\UrlHandling\SubsiteClash;

use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\Clash;
use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\MainSiteUrlState;

class UrlFormatBoxNotes {

	const DIRECTORIES_NOTE_ID       = 'wpml-subsite-clash-directories-note';
	const DEFAULT_DIRECTORY_NOTE_ID = 'wpml-subsite-clash-default-directory-note';

	const ALLOWED_HTML = [
		'a'    => [ 'href' => [] ],
		'code' => [],
	];

	const ERROR = 'icl_error_text';

	const INFO = 'description';

	public static function hasDirectoriesNote(): bool {
		return null !== self::directoriesNote();
	}

	public static function hasDefaultDirectoryNote(): bool {
		return null !== self::defaultDirectoryNote();
	}

	public static function printDirectoriesNote() {
		self::printNote( self::DIRECTORIES_NOTE_ID, self::directoriesNote() );
	}

	public static function printDefaultDirectoryNote() {
		self::printNote( self::DEFAULT_DIRECTORY_NOTE_ID, self::defaultDirectoryNote() );
	}

	private static function directoriesNote() {
		if ( ! self::applies() ) {
			return null;
		}

		if ( MainSiteUrlState::FORMAT_DIRECTORIES === Detector::state()->urlFormat() ) {
			$clashes = Detector::clashes();

			return $clashes ? [ self::ERROR, self::current( $clashes ) ] : null;
		}

		$clashes = Detector::potentialClashes();

		return $clashes ? [ self::INFO, self::potential( $clashes ) ] : null;
	}

	private static function defaultDirectoryNote() {
		if ( ! self::applies() ) {
			return null;
		}

		$clash = Detector::defaultLanguageClash();
		if ( ! $clash ) {
			return null;
		}

		$directory = self::directory( $clash );
		$language  = esc_html( $clash->languageName() );

		if ( Detector::state()->usesDirectoryForDefaultLanguage() ) {
			return [
				self::ERROR,
				sprintf(
					/* translators: Error next to the "Use directory for default language" checkbox, which is on. 1: the default language's URL directory in code tags, for example /en/. 2: the default language's name, for example English. */
					esc_html__( '%1$s belongs to a sub-site. With this option on, your %2$s pages open that sub-site.', 'sitepress' ),
					$directory,
					$language
				),
			];
		}

		return [
			self::INFO,
			sprintf(
				/* translators: Note next to the "Use directory for default language" checkbox, which is off. 1: the default language's URL directory in code tags, for example /en/. 2: the default language's name, for example English. */
				esc_html__( '%1$s belongs to a sub-site. If you turn this on, your %2$s pages will open that sub-site.', 'sitepress' ),
				$directory,
				$language
			),
		];
	}

	private static function current( array $clashes ) {
		$urlCodeLink = '<a href="' . esc_url( admin_url( 'admin.php?page=tm/menu/settings&section=languages' ) ) . '">';

		if ( is_super_admin() ) {
			return sprintf(
				esc_html(
					/* translators: Error under "Different languages in directories", for a network admin. 1: language URL directories with their language names, for example "/en/ (English), /es/ (Spanish)". 2 and 3: the start and the end of a link to Network Admin -> Sites, around "sub-site's address". 4 and 5: the start and the end of a link to WPML -> Settings -> Languages, around "language URL code". */
					_n(
						'These language URLs open a sub-site, not this site: %1$s. Change the %2$ssub-site\'s address%3$s or the %4$slanguage URL code%5$s.',
						'These language URLs open a sub-site, not this site: %1$s. Change the %2$ssub-site\'s address%3$s or the %4$slanguage URL code%5$s.',
						count( $clashes ),
						'sitepress'
					)
				),
				self::listOf( $clashes ),
				'<a href="' . esc_url( network_admin_url( 'sites.php' ) ) . '">',
				'</a>',
				$urlCodeLink,
				'</a>'
			);
		}

		return sprintf(
			esc_html(
				/* translators: Error under "Different languages in directories", for a site admin, who cannot change sub-sites. 1: language URL directories with their language names, for example "/en/ (English), /es/ (Spanish)". 2 and 3: the start and the end of a link to WPML -> Settings -> Languages, around "language URL code". */
				_n(
					'These language URLs open a sub-site, not this site: %1$s. Change the sub-site\'s address or the %2$slanguage URL code%3$s.',
					'These language URLs open a sub-site, not this site: %1$s. Change the sub-site\'s address or the %2$slanguage URL code%3$s.',
					count( $clashes ),
					'sitepress'
				)
			),
			self::listOf( $clashes ),
			$urlCodeLink,
			'</a>'
		);
	}

	private static function potential( array $clashes ) {
		return sprintf(
			esc_html(
				/* translators: Note under "Different languages in directories" when the site uses another URL format. %s: language URL directories with their language names, for example "/es/ (Spanish)". */
				_n(
					'If you choose this, these URLs will open a sub-site: %s.',
					'If you choose this, these URLs will open a sub-site: %s.',
					count( $clashes ),
					'sitepress'
				)
			),
			self::listOf( $clashes )
		);
	}

	private static function listOf( array $clashes ) {
		$items = [];
		foreach ( $clashes as $clash ) {
			$items[] = sprintf(
				/* translators: One item of a list of language URLs. 1: a language URL directory in code tags, for example /es/. 2: the language name, for example Spanish. */
				esc_html_x( '%1$s (%2$s)', 'language URL directory and language name', 'sitepress' ),
				self::directory( $clash ),
				esc_html( $clash->languageName() )
			);
		}

		return implode( esc_html( NoticeMarkup::listSeparator() ), $items );
	}

	private static function directory( Clash $clash ) {
		return '<code>' . esc_html( '/' . $clash->directoryCode() . '/' ) . '</code>';
	}

	private static function printNote( $id, $note ) {
		if ( ! $note ) {
			return;
		}

		printf(
			'<div id="%1$s" class="%2$s wpml-subsite-clash-note" style="margin:10px;"><p>%3$s</p></div>',
			esc_attr( $id ),
			esc_attr( $note[0] ),
			wp_kses( $note[1], self::ALLOWED_HTML )
		);
	}

	private static function applies(): bool {
		return is_multisite() && ! is_subdomain_install() && is_main_site() && ClashNotice::isUrlsAndSeo();
	}
}
