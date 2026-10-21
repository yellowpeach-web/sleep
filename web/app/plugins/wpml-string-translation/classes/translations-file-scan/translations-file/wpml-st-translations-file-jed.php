<?php

class WPML_ST_Translations_File_JED implements IWPML_ST_Translations_File {

	const EMPTY_PROPERTY_NAME   = '_empty_';
	const DECODED_EOT_CHAR      = '"\u0004"';
	const PLURAL_SUFFIX_PATTERN = ' [plural %d]';

	private $filepath;

	private $decoded_eot_char;

	private $plural_forms;

	public function __construct( $filepath ) {
		$this->filepath         = $filepath;
		$this->decoded_eot_char = json_decode( self::DECODED_EOT_CHAR );
	}

	public function get_plural_forms() {
		return $this->plural_forms;
	}

	public function get_translations() {
		$translations = array();
		$data         = json_decode( (string) file_get_contents( $this->filepath ) );

		if ( isset( $data->locale_data->messages ) ) {

			$entries = (array) $data->locale_data->messages;
			$header  = array();
			if ( isset( $entries[''] ) ) {
				$header = (array) $entries[''];
			} elseif ( isset( $entries[ self::EMPTY_PROPERTY_NAME ] ) ) {
				$header = (array) $entries[ self::EMPTY_PROPERTY_NAME ];
			}
			unset( $entries[ self::EMPTY_PROPERTY_NAME ], $entries[''] );

			foreach ( array( 'plural-forms', 'plural_forms', 'Plural-Forms' ) as $key ) {
				if ( isset( $header[ $key ] ) && is_string( $header[ $key ] ) ) {
					$this->plural_forms = $header[ $key ];
					break;
				}
			}

			foreach ( $entries as $str => $str_data ) {
				$str_data = (array) $str_data;

				if ( ! isset( $str_data[0] ) ) {
					continue;
				}

				list( $str, $context ) = $this->get_string_and_context( $str );
				$count_translations    = count( $str_data );
				$translations[]        = new WPML_ST_Translations_File_Translation( $str, $str_data[0], $context );

				if ( $count_translations > 1 ) {
					for ( $i = 1; $i < $count_translations; $i++ ) {
						$plural_str     = $str . sprintf( self::PLURAL_SUFFIX_PATTERN, $i );
						$translations[] = new WPML_ST_Translations_File_Translation( $plural_str, $str_data[ $i ], $context );
					}
				}
			}
		}

		return $translations;
	}

	private function get_string_and_context( $string ) {
		$context = '';
		$parts   = explode( $this->decoded_eot_char, $string );

		if ( $parts && count( $parts ) > 1 ) {
			$context = $parts[0];
			$string  = $parts[1];
		}

		return array( $string, $context );
	}
}
