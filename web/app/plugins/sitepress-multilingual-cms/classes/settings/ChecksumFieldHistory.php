<?php

namespace WPML\TM\Settings;

use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\ContainerFreeServices;
use WPML\LIB\WP\Hooks;
use WPML\WP\OptionManager;

class ChecksumFieldHistory implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const OPTION = 'wpml_tm_checksum_fields';

	const CHECK_OPTION = 'wpml_tm_checksum_fields_check';

	const MAX_CHANGES = 10;

	const RECHECK_SECONDS = 86400;

	private static $records = [];

	public function add_hooks() {
		Hooks::onAction( 'wpml_after_tm_loaded', 1 )
		     ->then( [ $this, 'observeCurrent' ] );
	}

	public function observeCurrent() {
		$this->observe( [ self::class, 'currentNames' ] );
	}

	public static function currentNames() {
		$names = [];
		foreach ( Repository::getCustomFields() as $name => $mode ) {
			if ( in_array( (int) $mode, [ WPML_TRANSLATE_CUSTOM_FIELD, WPML_COPY_ONCE_CUSTOM_FIELD ], true ) ) {
				$names[] = (string) $name;
			}
		}

		return $names;
	}

	public function observe( callable $currentNames ) {
		$stamp = ContainerFreeServices::changeSignal()->stamp();
		$check = get_option( self::CHECK_OPTION );
		if (
			is_array( $check )
			&& isset( $check['stamp'], $check['at'] )
			&& (int) $check['stamp'] === $stamp
			&& time() - (int) $check['at'] < self::RECHECK_SECONDS
		) {
			return;
		}

		$names = self::normalize( $currentNames() );
		$now   = time();

		self::$records[ get_current_blog_id() ] = ( new OptionManager() )->mutateRaw(
			self::OPTION,
			function ( $stored ) use ( $names, $now ) {
				return self::withNames( self::read( $stored ), $names, $now );
			},
			false
		);

		update_option(
			self::CHECK_OPTION,
			[
				'stamp' => $stamp,
				'at'    => $now,
			],
			true
		);
	}

	public function earlierSets( array $names, callable $hasValue ) {
		$changes = $this->record()['changes'];
		if ( ! $changes ) {
			return [];
		}

		$current = array_fill_keys( self::unique( $names ), true );
		$set     = $current;
		$seen    = [ self::key( array_keys( $current ) ) => true ];
		$sets    = [];
		foreach ( array_reverse( $changes ) as $change ) {
			foreach ( $change['joined'] as $name ) {
				if ( isset( $current[ $name ] ) && $hasValue( $name ) ) {
					return $sets;
				}
				unset( $set[ $name ] );
			}
			foreach ( $change['left'] as $name ) {
				$set[ $name ] = true;
			}

			$earlier = array_map( 'strval', array_keys( $set ) );
			$key     = self::key( $earlier );
			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$sets[]       = $earlier;
			}
		}

		return $sets;
	}

	public static function reset() {
		self::$records = [];
	}

	private function record() {
		$blog = get_current_blog_id();
		if ( ! isset( self::$records[ $blog ] ) ) {
			self::$records[ $blog ] = self::read( get_option( self::OPTION ) );
		}

		return self::$records[ $blog ];
	}

	private static function withNames( array $record, array $names, $at ) {
		if ( null === $record['names'] ) {
			$record['names'] = $names;

			return $record;
		}

		$joined = array_values( array_diff( $names, $record['names'] ) );
		$left   = array_values( array_diff( $record['names'], $names ) );
		if ( $joined || $left ) {
			$record['changes'][] = [
				'at'     => $at,
				'joined' => $joined,
				'left'   => $left,
			];
			$record['changes']   = array_slice( $record['changes'], -self::MAX_CHANGES );
			$record['names']     = $names;
		}

		return $record;
	}

	private static function read( $stored ) {
		$record = [
			'names'   => null,
			'changes' => [],
		];
		if ( ! is_array( $stored ) || ! isset( $stored['names'] ) || ! is_array( $stored['names'] ) ) {
			return $record;
		}

		$record['names'] = self::normalize( $stored['names'] );
		foreach ( isset( $stored['changes'] ) && is_array( $stored['changes'] ) ? $stored['changes'] : [] as $change ) {
			if ( ! is_array( $change ) ) {
				continue;
			}
			$record['changes'][] = [
				'at'     => isset( $change['at'] ) ? (int) $change['at'] : 0,
				'joined' => isset( $change['joined'] ) && is_array( $change['joined'] ) ? self::unique( $change['joined'] ) : [],
				'left'   => isset( $change['left'] ) && is_array( $change['left'] ) ? self::unique( $change['left'] ) : [],
			];
		}

		return $record;
	}

	private static function normalize( array $names ) {
		$names = self::unique( $names );
		sort( $names, SORT_STRING );

		return $names;
	}

	private static function unique( array $names ) {
		return array_values( array_unique( array_map( 'strval', $names ) ) );
	}

	private static function key( array $names ) {
		return implode( "\0", self::normalize( $names ) );
	}
}
