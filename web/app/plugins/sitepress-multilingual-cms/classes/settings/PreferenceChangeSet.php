<?php

namespace WPML\TM\Settings;

class PreferenceChangeSet {

	const TRANSLATE = 'translate';
	const COPY      = 'copy';
	const COPY_ONCE = 'copyOnce';
	const IGNORE    = 'ignore';

	private $changes;

	private function __construct( array $changes ) {
		$this->changes = $changes;
	}

	public static function fromArray( array $changes ): self {
		$normalized = [];

		foreach ( $changes as $key => $change ) {
			if ( ! is_array( $change ) ) {
				$change = [ 'newPref' => $change ];
			}

			$name = isset( $change['field'] ) ? (string) $change['field'] : (string) $key;
			if ( '' === $name || ! isset( $change['newPref'] ) ) {
				continue;
			}

			$newPref = (int) $change['newPref'];
			$oldPref = isset( $change['oldPref'] ) ? (int) $change['oldPref'] : null;

			if ( null !== $oldPref && $oldPref === $newPref ) {
				continue;
			}

			$normalized[ $name ] = [
				'oldPref' => $oldPref,
				'newPref' => $newPref,
			];
		}

		return new self( $normalized );
	}

	public static function transitionFor( int $mode ): string {
		switch ( $mode ) {
			case WPML_TRANSLATE_CUSTOM_FIELD:
				return self::TRANSLATE;
			case WPML_COPY_CUSTOM_FIELD:
				return self::COPY;
			case WPML_COPY_ONCE_CUSTOM_FIELD:
				return self::COPY_ONCE;
			default:
				return self::IGNORE;
		}
	}

	public function isEmpty(): bool {
		return ! $this->changes;
	}

	public function fieldNames(): array {
		return array_keys( $this->changes );
	}

	public function actingFieldNames(): array {
		$names = [];
		foreach ( $this->changes as $name => $change ) {
			if ( self::transitionFor( $change['newPref'] ) !== self::IGNORE ) {
				$names[] = (string) $name;
			}
		}

		return $names;
	}

	public function namesFor( string $transition ): array {
		$names = [];
		foreach ( $this->changes as $name => $change ) {
			if ( self::transitionFor( $change['newPref'] ) === $transition ) {
				$names[] = (string) $name;
			}
		}

		return $names;
	}

	public function namesByTransition(): array {
		$byTransition = [];
		foreach ( $this->changes as $name => $change ) {
			$byTransition[ self::transitionFor( $change['newPref'] ) ][] = (string) $name;
		}

		return $byTransition;
	}

	public function onlyTransitions( array $transitions ): self {
		return new self(
			array_filter(
				$this->changes,
				function ( $change ) use ( $transitions ) {
					return in_array( self::transitionFor( $change['newPref'] ), $transitions, true );
				}
			)
		);
	}

	public function toArray(): array {
		return $this->changes;
	}
}
