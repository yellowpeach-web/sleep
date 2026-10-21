<?php

namespace WPML\LanguageEditor\Save;

use WPML\LanguageEditor\Save\Phase\PhaseResult;

class FieldWriteExecutor {

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function writeStep( array $step ) {
		$code  = isset( $step['code'] ) ? (string) $step['code'] : '';
		$field = isset( $step['field'] ) ? (string) $step['field'] : '';
		$value = isset( $step['value'] ) ? (string) $step['value'] : '';
		$temp  = ! empty( $step['temp'] );

		if ( '' === $code || '' === $value ) {
			return PhaseResult::hardFail( 'invalid_write_step' );
		}

		if ( 'display_code' === $field ) {
			return $this->writeDisplayCode( $code, $value, $temp );
		}
		if ( 'locale' === $field ) {
			return $this->writeLocale( $code, $value, $temp );
		}

		return PhaseResult::hardFail( 'invalid_write_step' );
	}

	private function writeDisplayCode( $code, $value, $temp ) {
		if ( ! $temp ) {
			$codeError = \WPML\Core\Component\LanguageEditor\Domain\CustomLanguageFormat::validateCode( $value );
			if ( '' !== $codeError ) {
				return PhaseResult::hardFail( $codeError );
			}
		}

		$repository = new \WPML\LanguageEditor\Adapter\LanguageRepository();
		$written    = $repository->setDisplayCode( $code, $value );

		$stored = $written ? $repository->storedDisplayCode( $code ) : null;
		if ( null === $stored || 0 !== strcasecmp( $stored, $value ) ) {
			return PhaseResult::hardFail( 'display_code_not_saved' );
		}

		return PhaseResult::ok( 1 );
	}

	private function writeLocale( $code, $value, $temp ) {
		if ( ! $temp ) {
			if ( false !== strpos( $value, '-' ) ) {
				return PhaseResult::hardFail( 'locale_must_use_underscore' );
			}

			$localeError = \WPML\Core\Component\LanguageEditor\Domain\CustomLanguageFormat::validateLocale( $value );
			if ( '' !== $localeError ) {
				return PhaseResult::hardFail( $localeError );
			}
		}


		$this->wpdb->update(
			$this->wpdb->prefix . 'icl_languages',
			[ 'default_locale' => $value ],
			[ 'code' => $code ]
		);

		$mapExists = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT code FROM {$this->wpdb->prefix}icl_locale_map WHERE code = %s", $code ) );
		if ( $mapExists ) {
			$this->wpdb->update(
				$this->wpdb->prefix . 'icl_locale_map',
				[ 'locale' => $value ],
				[ 'code' => $code ]
			);
		} else {
			$this->wpdb->insert(
				$this->wpdb->prefix . 'icl_locale_map',
				[
					'code'   => $code,
					'locale' => $value,
				]
			);
		}

		return PhaseResult::ok( 1 );
	}
}
