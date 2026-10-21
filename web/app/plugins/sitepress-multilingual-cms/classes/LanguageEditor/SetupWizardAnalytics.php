<?php

namespace WPML\LanguageEditor;

use WPML\Setup\Option;
use WPML\WP\OptionManager;

class SetupWizardAnalytics {

	const OPTION_KEY = 'language-editor-analytics';

	const EVENT_EDIT_EXPANDED      = 'language_edit_expanded';
	const EVENT_VARIANT_CHANGED    = 'language_variant_changed';
	const EVENT_CUSTOM_FORM_OPENED = 'custom_language_form_opened';
	const EVENT_CUSTOM_CREATED     = 'custom_language_created';
	const EVENT_CODE_CHANGED       = 'language_code_changed';
	const EVENT_EDIT_ERROR         = 'language_edit_error';

	const EVENTS = [
		self::EVENT_EDIT_EXPANDED,
		self::EVENT_VARIANT_CHANGED,
		self::EVENT_CUSTOM_FORM_OPENED,
		self::EVENT_CUSTOM_CREATED,
		self::EVENT_CODE_CHANGED,
		self::EVENT_EDIT_ERROR,
	];

	const SESSION     = 'session';
	const ERROR_CODES = 'error_codes';

	const VARIANT_ENTRY_POINTS = 'variant_entry_points';

	const ENTRY_EXTENDED_MODE  = 'extended_mode';
	const ENTRY_ADD_PICKER     = 'add_picker';
	const ENTRY_WIZARD_DEFAULT = 'wizard_default_language';

	const ENTRY_POINTS = [
		self::ENTRY_EXTENDED_MODE,
		self::ENTRY_ADD_PICKER,
		self::ENTRY_WIZARD_DEFAULT,
	];

	const MAX_SESSIONS          = 10;
	const MAX_COUNT             = 10000;
	const MAX_ERROR_CODES       = 20;
	const MAX_ERROR_CODE_LENGTH = 64;
	const MAX_SESSION_ID_LENGTH = 64;

	const METHOD_EXTENDED_MODE = 'extended_mode';
	const METHOD_CUSTOM_FORM   = 'custom_form';
	const METHOD_BOTH          = 'both';
	const METHOD_NONE          = 'none';

	public static function record( $raw ) {
		$counters = self::normalizeCounters( $raw );
		if ( ! self::hasData( $counters ) ) {
			return;
		}

		$requestedSession = self::sessionId( $raw );

		( new OptionManager() )->mutate(
			Option::OPTION_GROUP,
			self::OPTION_KEY,
			function ( $stored ) use ( $counters, $requestedSession ) {
				$sessions = self::sessionsFrom( $stored );
				$session  = $requestedSession;
				if ( '' === $session ) {
					$i = count( $sessions ) + 1;
					while ( isset( $sessions[ 'request-' . $i ] ) ) {
						$i ++;
					}
					$session = 'request-' . $i;
				}

				unset( $sessions[ $session ] );
				$sessions[ $session ] = $counters;
				if ( count( $sessions ) > self::MAX_SESSIONS ) {
					$sessions = array_slice( $sessions, - self::MAX_SESSIONS, null, true );
				}

				return [ 'sessions' => $sessions ];
			}
		);
	}

	public static function clear() {
		if ( [] === self::sessions() ) {
			return;
		}
		( new OptionManager() )->set( Option::OPTION_GROUP, self::OPTION_KEY, [] );
	}

	public static function summary() {
		$totals = self::totals();

		$routes = $totals[ self::VARIANT_ENTRY_POINTS ];

		return [
			'language_variant_method'               => self::method( $totals ),
			'language_edit_expanded_count'          => $totals[ self::EVENT_EDIT_EXPANDED ],
			'language_variant_changed_count'        => $totals[ self::EVENT_VARIANT_CHANGED ],
			'language_variant_extended_mode_count'  => $routes[ self::ENTRY_EXTENDED_MODE ],
			'language_variant_add_picker_count'     => $routes[ self::ENTRY_ADD_PICKER ],
			'language_variant_wizard_default_count' => $routes[ self::ENTRY_WIZARD_DEFAULT ],
			'custom_language_form_opened_count'     => $totals[ self::EVENT_CUSTOM_FORM_OPENED ],
			'custom_language_created_count'         => $totals[ self::EVENT_CUSTOM_CREATED ],
			'language_code_changed_count'           => $totals[ self::EVENT_CODE_CHANGED ],
			'language_edit_error_count'             => $totals[ self::EVENT_EDIT_ERROR ],
			'language_edit_error_codes'             => $totals[ self::ERROR_CODES ],
		];
	}

	public static function sessions() {
		return self::sessionsFrom( ( new OptionManager() )->get( Option::OPTION_GROUP, self::OPTION_KEY, [] ) );
	}

	private static function sessionsFrom( $stored ) {
		$sessions = [];
		foreach ( (array) ( is_array( $stored ) ? ( $stored['sessions'] ?? [] ) : [] ) as $id => $counters ) {
			$sessions[ (string) $id ] = self::normalizeCounters( $counters );
		}

		return $sessions;
	}

	private static function totals() {
		$totals = self::normalizeCounters( [] );
		foreach ( self::sessions() as $counters ) {
			foreach ( self::EVENTS as $event ) {
				$totals[ $event ] = min( self::MAX_COUNT, $totals[ $event ] + $counters[ $event ] );
			}
			foreach ( self::ENTRY_POINTS as $route ) {
				$totals[ self::VARIANT_ENTRY_POINTS ][ $route ] = min(
					self::MAX_COUNT,
					$totals[ self::VARIANT_ENTRY_POINTS ][ $route ] + $counters[ self::VARIANT_ENTRY_POINTS ][ $route ]
				);
			}
			$totals[ self::ERROR_CODES ] = array_slice(
				array_values( array_unique( array_merge( $totals[ self::ERROR_CODES ], $counters[ self::ERROR_CODES ] ) ) ),
				0,
				self::MAX_ERROR_CODES
			);
		}

		return $totals;
	}

	private static function method( array $totals ) {
		$structured = $totals[ self::EVENT_VARIANT_CHANGED ] > 0;
		$custom     = $totals[ self::EVENT_CUSTOM_CREATED ] > 0;

		if ( $structured && $custom ) {
			return self::METHOD_BOTH;
		}
		if ( $structured ) {
			return self::METHOD_EXTENDED_MODE;
		}
		if ( $custom ) {
			return self::METHOD_CUSTOM_FORM;
		}

		return self::METHOD_NONE;
	}

	private static function sessionId( $raw ) {
		$session = is_array( $raw ) ? ( $raw[ self::SESSION ] ?? '' ) : '';
		if ( ! is_string( $session ) && ! is_numeric( $session ) ) {
			return '';
		}
		$session = (string) $session;

		return preg_match( '/^[A-Za-z0-9_-]{1,' . self::MAX_SESSION_ID_LENGTH . '}$/', $session ) ? $session : '';
	}

	private static function normalizeCounters( $raw ) {
		$raw      = is_array( $raw ) ? $raw : [];
		$counters = [];

		foreach ( self::EVENTS as $event ) {
			$value              = $raw[ $event ] ?? 0;
			$counters[ $event ] = is_numeric( $value ) ? min( self::MAX_COUNT, max( 0, (int) $value ) ) : 0;
		}

		$rawRoutes = $raw[ self::VARIANT_ENTRY_POINTS ] ?? [];
		$rawRoutes = is_array( $rawRoutes ) ? $rawRoutes : [];
		$routes    = [];
		foreach ( self::ENTRY_POINTS as $route ) {
			$value            = $rawRoutes[ $route ] ?? 0;
			$routes[ $route ] = is_numeric( $value ) ? min( self::MAX_COUNT, max( 0, (int) $value ) ) : 0;
		}
		$counters[ self::VARIANT_ENTRY_POINTS ] = $routes;

		$codes = [];
		foreach ( (array) ( $raw[ self::ERROR_CODES ] ?? [] ) as $code ) {
			if ( ! is_string( $code ) && ! is_numeric( $code ) ) {
				continue;
			}
			$code = substr( trim( (string) $code ), 0, self::MAX_ERROR_CODE_LENGTH );
			$code = (string) preg_replace( '/[^A-Za-z0-9_.:-]/', '', $code );
			if ( '' !== $code && ! in_array( $code, $codes, true ) ) {
				$codes[] = $code;
			}
			if ( count( $codes ) >= self::MAX_ERROR_CODES ) {
				break;
			}
		}
		$counters[ self::ERROR_CODES ] = $codes;

		return $counters;
	}

	private static function hasData( array $counters ) {
		foreach ( self::EVENTS as $event ) {
			if ( $counters[ $event ] > 0 ) {
				return true;
			}
		}
		foreach ( self::ENTRY_POINTS as $route ) {
			if ( $counters[ self::VARIANT_ENTRY_POINTS ][ $route ] > 0 ) {
				return true;
			}
		}

		return count( $counters[ self::ERROR_CODES ] ) > 0;
	}
}
