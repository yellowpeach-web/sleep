<?php

class WPML_Data_Encryptor {

	const SALT_CHARS  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?|';
	const SALT_LENGTH = 64;

	const DEFAULT_METHOD = 'AES-256-CTR';

	const MODE_OPENSSL = "\x01";
	const TAG_LENGTH   = 32;

	private $method = '';
	private $key;
	private $mac_key;

	private $library = false;

	public function __construct( $key_salt = '', $method = self::DEFAULT_METHOD ) {

		if ( ! $key_salt ) {
			$key_salt = $this->get_key_salt();
		}

		$this->key     = hash_hmac( 'sha256', 'wpml-data-encryptor:encryption', $key_salt, true );
		$this->mac_key = hash_hmac( 'sha256', 'wpml-data-encryptor:authentication', $key_salt, true );

		if ( function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' ) ) {
			$methods = array_map( 'strtolower', openssl_get_cipher_methods() );
			if ( in_array( strtolower( $method ), $methods, true ) ) {
				$this->method = $method;
			} elseif ( in_array( strtolower( self::DEFAULT_METHOD ), $methods, true ) ) {
				$this->method = self::DEFAULT_METHOD;
			}

			if ( '' !== $this->method ) {
				$this->library = 'openssl';
			}
		}
	}

	public function encrypt( $data ) {
		if ( ! $this->can_encrypt() ) {
			return false;
		}

		$iv   = $this->generate_iv();
		$body = openssl_encrypt( $data, $this->method, $this->key, OPENSSL_RAW_DATA, $iv );
		if ( false === $body ) {
			return false;
		}

		$framed = self::MODE_OPENSSL . $iv . $body;

		return $framed . $this->tag( $framed );
	}

	public function decrypt( $encrypted_data ) {
		if ( ! $this->can_encrypt() || ! is_string( $encrypted_data ) ) {
			return false;
		}

		$iv_length = $this->get_iv_length();

		if ( strlen( $encrypted_data ) < 1 + $iv_length + self::TAG_LENGTH ) {
			return false;
		}

		$framed = substr( $encrypted_data, 0, -self::TAG_LENGTH );
		$tag    = substr( $encrypted_data, -self::TAG_LENGTH );

		if ( ! hash_equals( $this->tag( $framed ), $tag ) ) {
			return false;
		}

		if ( self::MODE_OPENSSL !== $framed[0] ) {
			return false;
		}

		$iv   = (string) substr( $framed, 1, $iv_length );
		$body = (string) substr( $framed, 1 + $iv_length );

		return openssl_decrypt( $body, $this->method, $this->key, OPENSSL_RAW_DATA, $iv );
	}

	public function set_crypt_library( $library ) {
		$this->library = $library;
	}

	public function get_crypt_library() {
		return $this->library;
	}

	private function can_encrypt() {
		return 'openssl' === $this->library && '' !== $this->method;
	}

	private function tag( $framed ) {
		return hash_hmac( 'sha256', $framed, $this->mac_key, true );
	}

	private function get_iv_length() {
		$length = openssl_cipher_iv_length( $this->method );

		return max( 0, (int) $length );
	}

	private function generate_iv() {
		$length = $this->get_iv_length();

		return $length > 0 ? random_bytes( $length ) : '';
	}

	private function get_key_salt() {
		if ( defined( 'NONCE_SALT' ) ) {
			return NONCE_SALT;
		}

		return $this->generate_salt_key();
	}

	private function generate_salt_key() {
		$salt_key = '';
		for ( $i = 0; $i < self::SALT_LENGTH; $i++ ) {
			$salt_key .= substr( self::SALT_CHARS, random_int( 0, strlen( self::SALT_CHARS ) - 1 ), 1 );
		}

		return $salt_key;
	}
}
