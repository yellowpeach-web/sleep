<?php

namespace WPML\EditHistory;

class SchemaValidator {

	const ANNOTATIONS = [ '$schema', '$id', '$defs', 'title', 'description' ];

	private $root;

	public function __construct( $root ) {
		$this->root = $root;
	}

	public static function fromFile( string $path ): self {
		$json = is_readable( $path ) ? file_get_contents( $path ) : false;
		$root = false === $json ? null : json_decode( $json );

		return new self( $root instanceof \stdClass ? $root : null );
	}

	public function validate( $value ) {
		if ( ! $this->root ) {
			return 'the schema could not be read';
		}

		return $this->check( $value, $this->root, '' );
	}

	private function check( $value, $schema, string $path ) {
		if ( is_bool( $schema ) ) {
			return $schema ? null : self::error( $path, 'no value is allowed here' );
		}

		foreach ( get_object_vars( $schema ) as $keyword => $argument ) {
			$error = in_array( $keyword, self::ANNOTATIONS, true ) ? null : $this->keyword( (string) $keyword, $argument, $value, $schema, $path );
			if ( null !== $error ) {
				return $error;
			}
		}

		return null;
	}

	private function keyword( string $keyword, $argument, $value, $schema, string $path ) {
		switch ( $keyword ) {
			case '$ref':
				$target = $this->resolve( (string) $argument );

				return null === $target ? self::error( $path, 'unresolved $ref ' . $argument ) : $this->check( $value, $target, $path );
			case 'type':
				return in_array( self::type( $value ), (array) $argument, true )
					|| ( 'integer' === self::type( $value ) && in_array( 'number', (array) $argument, true ) )
					? null : self::error( $path, 'is ' . self::type( $value ) . ', not ' . implode( ' or ', (array) $argument ) );
			case 'const':
				return self::same( $value, $argument ) ? null : self::error( $path, 'is not ' . wp_json_encode( $argument ) );
			case 'enum':
				foreach ( $argument as $allowed ) {
					if ( self::same( $value, $allowed ) ) {
						return null;
					}
				}

				return self::error( $path, 'is not one of ' . wp_json_encode( $argument ) );
			case 'oneOf':
				$errors  = array_map( fn( $branch ) => $this->check( $value, $branch, $path ), $argument );
				$matches = count( array_filter( $errors, 'is_null' ) );
				if ( 0 === $matches ) {
					return self::deepest( $errors );
				}

				return 1 === $matches ? null : self::error( $path, 'matches ' . $matches . ' of the ' . count( $argument ) . ' shapes of oneOf' );
			case 'properties':
			case 'patternProperties':
			case 'additionalProperties':
			case 'required':
				return $value instanceof \stdClass ? $this->objectKeyword( $keyword, $argument, $value, $schema, $path ) : null;
			case 'prefixItems':
			case 'items':
			case 'minItems':
			case 'maxItems':
			case 'uniqueItems':
				return is_array( $value ) ? $this->arrayKeyword( $keyword, $argument, $value, $schema, $path ) : null;
			case 'minimum':
				return ( ! is_int( $value ) && ! is_float( $value ) ) || $value >= $argument ? null : self::error( $path, 'is below ' . $argument );
			case 'minLength':
				return ! is_string( $value ) || mb_strlen( $value, 'UTF-8' ) >= $argument ? null : self::error( $path, 'is shorter than ' . $argument );
			case 'pattern':
				return ! is_string( $value ) || self::matches( (string) $argument, $value ) ? null : self::error( $path, 'does not match ' . $argument );
		}

		return self::error( $path, 'the schema uses the keyword ' . $keyword . ', which this validator does not know' );
	}

	private function objectKeyword( string $keyword, $argument, \stdClass $value, $schema, string $path ) {
		$members = get_object_vars( $value );

		if ( 'required' === $keyword ) {
			$missing = array_diff( $argument, array_map( 'strval', array_keys( $members ) ) );

			return $missing ? self::error( $path, 'lacks ' . implode( ', ', $missing ) ) : null;
		}

		foreach ( $members as $name => $member ) {
			foreach ( self::memberSchemas( $keyword, $argument, (string) $name, $schema ) as $memberSchema ) {
				$error = $this->check( $member, $memberSchema, $path . '/' . $name );
				if ( null !== $error ) {
					return $error;
				}
			}
		}

		return null;
	}

	private static function memberSchemas( string $keyword, $argument, string $name, $schema ): array {
		if ( 'properties' === $keyword ) {
			return isset( $argument->$name ) ? [ $argument->$name ] : [];
		}

		if ( 'patternProperties' === $keyword ) {
			return self::patternSchemas( $argument, $name );
		}

		$declared = isset( $schema->properties->$name ) || self::patternSchemas( $schema->patternProperties ?? null, $name );

		return $declared ? [] : [ $argument ];
	}

	private static function patternSchemas( $patterns, string $name ): array {
		$schemas = [];
		foreach ( get_object_vars( $patterns ?? new \stdClass() ) as $pattern => $patternSchema ) {
			if ( self::matches( (string) $pattern, $name ) ) {
				$schemas[] = $patternSchema;
			}
		}

		return $schemas;
	}

	private function arrayKeyword( string $keyword, $argument, array $value, $schema, string $path ) {
		switch ( $keyword ) {
			case 'minItems':
				return count( $value ) >= $argument ? null : self::error( $path, 'has fewer than ' . $argument . ' items' );
			case 'maxItems':
				return count( $value ) <= $argument ? null : self::error( $path, 'has more than ' . $argument . ' items' );
			case 'uniqueItems':
				$encoded = array_map( 'wp_json_encode', $value );

				return ! $argument || count( array_unique( $encoded ) ) === count( $encoded ) ? null : self::error( $path, 'repeats an item' );
		}

		$prefix = 'prefixItems' === $keyword ? $argument : ( $schema->prefixItems ?? [] );
		foreach ( $value as $index => $item ) {
			$itemSchema = 'prefixItems' === $keyword ? ( $prefix[ $index ] ?? null ) : ( isset( $prefix[ $index ] ) ? null : $argument );
			$error      = null === $itemSchema ? null : $this->check( $item, $itemSchema, $path . '/' . $index );
			if ( null !== $error ) {
				return $error;
			}
		}

		return null;
	}

	private function resolve( string $reference ) {
		if ( 0 !== strpos( $reference, '#' ) ) {
			return null;
		}

		$node = $this->root;
		foreach ( array_filter( explode( '/', substr( $reference, 1 ) ), 'strlen' ) as $part ) {
			$part = str_replace( [ '~1', '~0' ], [ '/', '~' ], $part );
			if ( ! $node instanceof \stdClass || ! property_exists( $node, $part ) ) {
				return null;
			}
			$node = $node->$part;
		}

		return $node instanceof \stdClass || is_bool( $node ) ? $node : null;
	}

	private static function type( $value ): string {
		if ( $value instanceof \stdClass ) {
			return 'object';
		}

		return [
			'array'   => 'array',
			'string'  => 'string',
			'integer' => 'integer',
			'double'  => 'number',
			'boolean' => 'boolean',
			'NULL'    => 'null',
		][ gettype( $value ) ] ?? gettype( $value );
	}

	private static function same( $value, $expected ): bool {
		return wp_json_encode( $value ) === wp_json_encode( $expected );
	}

	private static function matches( string $pattern, string $value ): bool {
		return 1 === preg_match( '/' . str_replace( '/', '\/', $pattern ) . '/u', $value );
	}

	private static function deepest( array $errors ): string {
		$deepest = $errors[0];
		foreach ( $errors as $error ) {
			if ( self::depth( $error ) > self::depth( $deepest ) ) {
				$deepest = $error;
			}
		}

		return $deepest;
	}

	private static function depth( string $error ): int {
		return substr_count( (string) strstr( $error, ': ', true ), '/' );
	}

	private static function error( string $path, string $what ): string {
		return ( '' === $path ? '/' : $path ) . ': ' . $what;
	}
}
