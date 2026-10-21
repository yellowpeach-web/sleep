<?php

class WPML_Outdated_Companion_Stand_In {

	private static $derived = [];

	public function __call( $name, $arguments ) {
		return null;
	}

	public function __get( $name ) {
		return $this;
	}

	public function __isset( $name ) {
		return false;
	}

	public static function forType( $type ) {
		if ( null === $type || ! $type instanceof ReflectionNamedType || $type->isBuiltin() ) {
			return null === $type || ( $type instanceof ReflectionNamedType && in_array( $type->getName(), [ 'object', 'mixed' ], true ) ) ? new self() : null;
		}

		$class = self::derive( $type->getName() );

		return $class ? new $class() : null;
	}

	private static function derive( $parent ) {
		if ( array_key_exists( $parent, self::$derived ) ) {
			return self::$derived[ $parent ];
		}

		self::$derived[ $parent ] = false;

		if ( ! class_exists( $parent ) ) {
			return false;
		}

		$reflection = new ReflectionClass( $parent );

		if ( $reflection->isFinal() || $reflection->isInterface() || $reflection->isAbstract() ) {
			return false;
		}

		$methods = [];

		foreach ( $reflection->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
			if ( $method->isConstructor() || $method->isDestructor() || $method->isFinal() ) {
				if ( $method->isFinal() && ! $method->isConstructor() ) {
					return false;
				}

				continue;
			}

			$return = self::returnFor( $method );

			if ( false === $return ) {
				return false;
			}

			$params = [];

			foreach ( $method->getParameters() as $param ) {
				$params[] = ( $param->isPassedByReference() ? '&' : '' ) . ( $param->isVariadic() ? '...' : '' ) . '$' . $param->getName() . ( $param->isVariadic() ? '' : ' = null' );
			}

			$methods[] = sprintf(
				'public %sfunction %s( %s )%s { %s }',
				$method->isStatic() ? 'static ' : '',
				$method->getName(),
				implode( ', ', $params ),
				$method->hasReturnType() ? ': ' . self::typeToCode( $method->getReturnType() ) : '',
				$return
			);
		}

		$class = 'WPML_Outdated_Stand_In_For_' . md5( $parent );

		if ( ! class_exists( $class, false ) ) {
			eval( sprintf( 'class %s extends %s { public function __construct() {} %s }', $class, $parent, implode( ' ', $methods ) ) );
		}

		self::$derived[ $parent ] = $class;

		return $class;
	}

	private static function returnFor( ReflectionMethod $method ) {
		if ( ! $method->hasReturnType() ) {
			return 'return null;';
		}

		$type = $method->getReturnType();

		if ( ! $type instanceof ReflectionNamedType ) {
			return false;
		}

		if ( $type->allowsNull() ) {
			return 'void' === $type->getName() ? '' : 'return null;';
		}

		switch ( $type->getName() ) {
			case 'void':
				return '';
			case 'string':
				return "return '';";
			case 'int':
				return 'return 0;';
			case 'float':
				return 'return 0.0;';
			case 'bool':
				return 'return false;';
			case 'array':
			case 'iterable':
				return 'return [];';
			case 'static':
			case 'self':
				return 'return $this;';
			default:
				return false;
		}
	}

	private static function typeToCode( ReflectionType $type ) {
		$name = $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;

		if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
			$name = '\\' . ltrim( $name, '\\' );
		}

		return ( $type->allowsNull() && 'mixed' !== $name && 'null' !== $name && 'void' !== $name ? '?' : '' ) . $name;
	}
}
