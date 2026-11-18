<?php

namespace WPML\PB\Gutenberg;

final class NeverTextKeys {

	const EXACT = [ 'classname', 'class', 'cssclass', 'anchor', 'htmlanchor', 'id', 'uniqueid', 'clientid' ];

	const SUFFIXES = [ 'ClassName', 'Classes', 'Id', 'Ids' ];

	const WORDPRESS_MANAGED = [
		'metadata',
		'lock',
		'templatelock',
		'style',
		'layout',
		'textcolor',
		'backgroundcolor',
		'gradient',
		'fontfamily',
		'fontsize',
		'bordercolor',
	];

	public static function matches( string $key, bool $isRootKey = true ): bool {
		$lowercase = strtolower( $key );

		return in_array( $lowercase, self::exact(), true )
			|| ( $isRootKey && in_array( $lowercase, self::WORDPRESS_MANAGED, true ) )
			|| (bool) preg_match( self::suffixPattern(), $key );
	}

	private static function exact(): array {
		$filtered = apply_filters( 'wpml_pb_gutenberg_never_text_keys', self::EXACT );

		return array_values( array_unique( array_merge( self::EXACT, self::stringsOf( $filtered, 'strtolower' ) ) ) );
	}

	private static function suffixPattern(): string {
		$filtered = apply_filters( 'wpml_pb_gutenberg_never_text_key_suffixes', self::SUFFIXES );
		$suffixes = array_values( array_unique( array_merge( self::SUFFIXES, self::stringsOf( $filtered ) ) ) );
		$quoted   = array_map(
			function ( $suffix ) {
				return preg_quote( $suffix, '/' );
			},
			$suffixes
		);

		return '/[a-z0-9](?:' . implode( '|', $quoted ) . ')$/';
	}

	private static function stringsOf( $values, ?callable $map = null ): array {
		$strings = [];
		foreach ( is_array( $values ) ? $values : [] as $value ) {
			if ( is_string( $value ) && '' !== $value ) {
				$strings[] = $map ? $map( $value ) : $value;
			}
		}

		return $strings;
	}
}
