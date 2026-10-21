<?php

namespace WPML\Languages;

use WPML\Posts\TranslatedContentOfLanguages;
use WPML\Upgrade\SchemaFacts;

/**
 * Read-model for the languages DELETION-FLOWS §3.2 option 2 produces: an
 * `icl_languages` row with `active <> 1` that STILL HOLDS translated content.
 *
 * THE RULING THIS CLASS SERVES — §12 point 3: "keep" means kept IN THE DATABASE
 * with the language off, not served. A removed language's addresses 301 to their
 * default-language counterpart (part (a), {@see RemovedLanguageRedirect}) and its
 * kept items stay browsable from the admin list screens' language filter under a
 * "Removed" group (part (c)). A language that keeps SERVING is option 1's
 * territory and is a different mechanism entirely
 * ({@see \WPML\LanguageEditor\TranslationPause}).
 *
 * TWO PREDICATES, NEVER ONE. `active <> 1` alone is not "removed": a site carries
 * inactive SEED rows for every language it never turned on, and treating those as
 * removed would put every unused language in the admin filter and would make the
 * front end ask about them on every request. The second predicate — "still owns
 * translated content" — is what tells a genuinely removed language from an
 * untouched seed (the wpmldev-7763 distinction {@see \WPML\LanguageEditor\Adapter\LanguageRepository}
 * already draws), and it is answered by the landed counting engine
 * {@see TranslatedContentOfLanguages::totalsByLanguage()} — no new counting SQL.
 *
 * COST DISCIPLINE (the {@see \WPML\LanguageEditor\TranslationPause::pausedCodes()}
 * idiom). Everything is memoized for the request and dropped by
 * {@see self::resetCache()}. The two reads are STAGED: the inactive-row list is
 * one SELECT over a table that holds one row per language WPML ships (plus the
 * one-time `display_code` column probe it is shaped by,
 * {@see self::hasDisplayCodeColumn()}), and the content totals are asked ONLY once
 * a caller has named a code that list actually contains. A site with no inactive
 * rows therefore pays that one small SELECT and nothing else — no content query,
 * ever — and {@see self::has()} on a code that is not inactive costs nothing
 * beyond it.
 *
 * No `$wpdb` answers "the table cannot say" with the empty answer rather than a
 * fatal — the stance TranslationPause takes for the same reason: this is read
 * from front-end request handling, and a read model that fataled where the
 * database is not there would break serving rather than a feature.
 *
 * @group wpmldev-8026
 */
class RemovedLanguages {

	/**
	 * The admin screens whose list table is filtered by `lang=` and where a
	 * removed language is therefore BROWSABLE (§3.2 option 2, mechanism (c)).
	 *
	 * Deliberately a `$pagenow` whitelist and not `is_admin()` alone: admin-ajax,
	 * the WPML settings screens and every other admin page keep answering exactly
	 * as they do today, so the widening in {@see self::browsableInAdminList()}
	 * cannot reach anything but the list table the "Removed" group links to.
	 *
	 * `edit.php` alone, and on purpose: it is the one screen this slice puts a
	 * "Removed" group on, and a gate is widened for the surface that exists, never
	 * for the surfaces a later slice might add. The Media library (`upload.php`)
	 * and the term lists (`edit-tags.php`) keep answering exactly as they do today.
	 */
	const LIST_SCREENS = array( 'edit.php' );

	private static $inactive = null;

	private static $displayCodes = null;

	private static $activeTokens = null;

	private static $withContent = null;

	public static function resetCache() {
		self::$inactive     = null;
		self::$displayCodes = null;
		self::$activeTokens = null;
		self::$withContent  = null;
		SchemaFacts::forget();
	}

	public static function inactiveNames() {
		global $wpdb;

		if ( null !== self::$inactive ) {
			return self::$inactive;
		}

		self::$inactive     = array();
		self::$displayCodes = array();
		self::$activeTokens = array();

		if ( ! is_object( $wpdb ) ) {
			return self::$inactive;
		}

		$display = self::hasDisplayCodeColumn() ? ', display_code' : '';

		$rows = $wpdb->get_results(
			"SELECT code, english_name, active{$display} FROM {$wpdb->prefix}icl_languages ORDER BY id"
		);
		SchemaFacts::forgetIfQueryFailed( $wpdb );

		foreach ( (array) $rows as $row ) {
			if ( ! isset( $row->code ) ) {
				continue;
			}
			$code = (string) $row->code;
			if ( '' === $code ) {
				continue;
			}
			$display = isset( $row->display_code ) ? strtolower( (string) $row->display_code ) : '';

			if ( isset( $row->active ) && 1 === (int) $row->active ) {
				self::$activeTokens[ strtolower( $code ) ] = true;
				if ( '' !== $display ) {
					self::$activeTokens[ $display ] = true;
				}
				continue;
			}

			self::$inactive[ $code ]     = isset( $row->english_name ) ? (string) $row->english_name : $code;
			self::$displayCodes[ $code ] = $display;
		}

		return self::$inactive;
	}

	private static function hasDisplayCodeColumn() {
		return SchemaFacts::hasColumn( 'icl_languages', 'display_code' );
	}

	public static function codeForUrlToken( $token ) {
		$token = is_scalar( $token ) ? (string) $token : '';

		if ( '' === $token ) {
			return '';
		}

		$inactive   = self::inactiveNames();
		$published  = strtolower( $token );
		$candidates = array();

		if ( isset( self::$activeTokens[ $published ] ) ) {
			return '';
		}

		foreach ( (array) self::$displayCodes as $code => $display ) {
			if ( '' !== $display && $display === $published ) {
				$candidates[] = (string) $code;
			}
		}

		if ( isset( $inactive[ $token ] ) && ! in_array( $token, $candidates, true ) ) {
			$candidates[] = $token;
		}

		foreach ( $candidates as $candidate ) {
			if ( self::has( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	public static function withContent() {
		if ( null !== self::$withContent ) {
			return self::$withContent;
		}

		$codes = array_keys( self::inactiveNames() );

		if ( ! $codes ) {
			self::$withContent = array();

			return self::$withContent;
		}

		$totals = TranslatedContentOfLanguages::totalsByLanguage( $codes );

		self::$withContent = array();
		foreach ( $codes as $code ) {
			if ( ! empty( $totals[ $code ] ) ) {
				self::$withContent[ $code ] = (int) $totals[ $code ];
			}
		}

		return self::$withContent;
	}

	public static function has( $code ) {
		$code = is_scalar( $code ) ? (string) $code : '';

		if ( '' === $code ) {
			return false;
		}

		$inactive = self::inactiveNames();

		if ( ! isset( $inactive[ $code ] ) ) {
			return false;
		}

		$totals = self::withContent();

		return isset( $totals[ $code ] );
	}

	public static function totalFor( $code ) {
		$totals = self::withContent();
		$code   = is_scalar( $code ) ? (string) $code : '';

		return isset( $totals[ $code ] ) ? (int) $totals[ $code ] : 0;
	}

	public static function listing() {
		$names   = self::inactiveNames();
		$totals  = self::withContent();
		$listing = array();

		foreach ( $totals as $code => $total ) {
			$listing[] = array(
				'code'  => (string) $code,
				'name'  => isset( $names[ $code ] ) ? $names[ $code ] : (string) $code,
				'total' => (int) $total,
			);
		}

		usort(
			$listing,
			function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return $listing;
	}

	public static function browsableInAdminList( $code ) {
		if ( ! self::isListScreen() ) {
			return false;
		}

		return self::has( $code );
	}

	private static function isListScreen() {
		global $pagenow;

		if ( ! is_string( $pagenow ) || ! in_array( $pagenow, self::LIST_SCREENS, true ) ) {
			return false;
		}

		return function_exists( 'is_admin' ) && is_admin();
	}
}
