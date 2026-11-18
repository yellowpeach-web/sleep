<?php

if ( ! function_exists( 'wpml_view_esc' ) ) {
	function wpml_view_esc( $value, $strategy = 'html' ) {
		$string = wpml_view_string( $value );

		if ( 'html_attr' === $strategy ) {
			return wpml_view_esc_html_attr( $string );
		}

		return htmlspecialchars( $string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'wpml_view_esc_html_attr' ) ) {
	function wpml_view_esc_html_attr( $string ) {
		if ( '' === $string ) {
			return '';
		}

		if ( ! preg_match( '//u', $string ) ) {
			throw new \RuntimeException( 'The string to escape is not a valid UTF-8 string.' );
		}

		return (string) preg_replace_callback(
			'#[^a-zA-Z0-9,\.\-_]#Su',
			function ( $matches ) {
				$chr = $matches[0];
				$ord = ord( $chr[0] );

				if ( ( $ord <= 0x1f && "\t" !== $chr && "\n" !== $chr && "\r" !== $chr ) || ( $ord >= 0x7f && $ord <= 0x9f ) ) {
					return '&#xFFFD;';
				}

				if ( 1 === strlen( $chr ) ) {
					$names = array(
						34 => '&quot;',
						38 => '&amp;',
						60 => '&lt;',
						62 => '&gt;',
					);

					return isset( $names[ $ord ] ) ? $names[ $ord ] : sprintf( '&#x%02X;', $ord );
				}

				$bytes = array_map( 'ord', str_split( $chr ) );

				if ( $ord >= 0xf0 ) {
					$code = ( ( $ord - 0xf0 ) << 18 ) + ( ( $bytes[1] - 0x80 ) << 12 ) + ( ( $bytes[2] - 0x80 ) << 6 ) + $bytes[3] - 0x80;
				} elseif ( $ord >= 0xe0 ) {
					$code = ( ( $ord - 0xe0 ) << 12 ) + ( ( $bytes[1] - 0x80 ) << 6 ) + $bytes[2] - 0x80;
				} else {
					$code = ( ( $ord - 0xc0 ) << 6 ) + $bytes[1] - 0x80;
				}

				return sprintf( '&#x%04X;', $code );
			},
			$string
		);
	}
}

if ( ! function_exists( 'wpml_view_esc_attr' ) ) {
	function wpml_view_esc_attr( $value ) {
		$value = wpml_view_string( $value );

		if ( '' === $value ) {
			return '';
		}

		if ( ! preg_match( '//u', $value ) ) {
			throw new RuntimeException( 'The string to escape is not a valid UTF-8 string.' );
		}

		return preg_replace_callback(
			'#[^a-zA-Z0-9,\.\-_]#Su',
			function ( $matches ) {
				$char = $matches[0];
				$code = ord( $char[0] );

				if ( ( $code <= 0x1f && "\t" !== $char && "\n" !== $char && "\r" !== $char ) || ( $code >= 0x7f && $code <= 0x9f ) ) {
					return '&#xFFFD;';
				}

				if ( 1 === strlen( $char ) ) {
					$named = array(
						34 => '&quot;',
						38 => '&amp;',
						60 => '&lt;',
						62 => '&gt;',
					);

					return isset( $named[ $code ] ) ? $named[ $code ] : sprintf( '&#x%02X;', $code );
				}

				$bytes = unpack( 'C*', substr( $char, 0, 4 ) );
				$lead  = $bytes[1];

				if ( $lead >= 0xf0 ) {
					$point = ( ( $lead - 0xf0 ) << 18 ) + ( ( $bytes[2] - 0x80 ) << 12 ) + ( ( $bytes[3] - 0x80 ) << 6 ) + $bytes[4] - 0x80;
				} elseif ( $lead >= 0xe0 ) {
					$point = ( ( $lead - 0xe0 ) << 12 ) + ( ( $bytes[2] - 0x80 ) << 6 ) + $bytes[3] - 0x80;
				} else {
					$point = ( ( $lead - 0xc0 ) << 6 ) + $bytes[2] - 0x80;
				}

				return sprintf( '&#x%04X;', $point );
			},
			$value
		);
	}
}

if ( ! function_exists( 'wpml_view_in' ) ) {
	function wpml_view_in( $value, $haystack ) {
		if ( is_array( $haystack ) ) {
			return in_array( $value, $haystack, is_object( $value ) || is_resource( $value ) );
		}

		if ( is_string( $haystack ) && ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) ) {
			return '' === $value || false !== strpos( $haystack, (string) $value );
		}

		if ( $haystack instanceof Traversable ) {
			foreach ( $haystack as $item ) {
				if ( ( is_object( $value ) || is_resource( $value ) ) ? $item === $value : $item == $value ) {
					return true;
				}
			}
		}

		return false;
	}
}

if ( ! function_exists( 'wpml_view_raw' ) ) {
	function wpml_view_raw( $value ) {
		return wpml_view_string( $value );
	}
}

if ( ! function_exists( 'wpml_view_string' ) ) {
	function wpml_view_string( $value ) {
		if ( is_scalar( $value ) || ( is_object( $value ) && method_exists( $value, '__toString' ) ) ) {
			return (string) $value;
		}

		return '';
	}
}

if ( ! function_exists( 'wpml_view_default' ) ) {
	function wpml_view_default( $value, $default = '' ) {
		return ( '' === $value || false === $value || null === $value || array() === $value ) ? $default : $value;
	}
}

if ( ! function_exists( 'wpml_view_length' ) ) {
	function wpml_view_length( $value ) {
		if ( null === $value ) {
			return 0;
		}

		if ( is_scalar( $value ) ) {
			return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value, 'UTF-8' ) : strlen( (string) $value );
		}

		if ( is_array( $value ) || $value instanceof \Countable ) {
			return count( $value );
		}

		if ( $value instanceof \Traversable ) {
			return iterator_count( $value );
		}

		if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
			return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value, 'UTF-8' ) : strlen( (string) $value );
		}

		return 1;
	}
}

if ( ! function_exists( 'wpml_view_attr' ) ) {
	function wpml_view_attr( $subject, $name ) {
		if ( is_array( $subject ) ) {
			return array_key_exists( $name, $subject ) ? $subject[ $name ] : null;
		}

		if ( ! is_object( $subject ) ) {
			return null;
		}

		if ( $subject instanceof ArrayAccess && isset( $subject[ $name ] ) ) {
			return $subject[ $name ];
		}

		$properties = get_object_vars( $subject );

		if ( array_key_exists( $name, $properties ) ) {
			return $properties[ $name ];
		}

		foreach ( array( '', 'get', 'is' ) as $prefix ) {
			$method = $prefix . $name;

			if ( is_callable( array( $subject, $method ) ) ) {
				return $subject->$method();
			}
		}

		return null;
	}
}
