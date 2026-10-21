<?php

namespace ACFML\Options;

use WPML\Element\API\Languages;
use WPML\FP\Obj;

class ValueCopy {

	const ALWAYS = 'always';

	const HOLDS_NOTHING = 'holds-nothing';

	const ABSENT = 'absent';

	private $acfWorker;

	public function __construct( \WPML_ACF_Worker $acfWorker ) {
		$this->acfWorker = $acfWorker;
	}

	public function fromSave(
		$optionsNamespace,
		$sourceLanguage,
		array $field,
		$flatName,
		$value,
		$overrideExisting = true,
		$convert = true
	) {
		$this->write(
			$optionsNamespace,
			$sourceLanguage,
			$field,
			$flatName,
			$value,
			$convert,
			true,
			$overrideExisting ? self::ALWAYS : self::HOLDS_NOTHING
		);
	}

	public function fromStoredValue(
		$optionsNamespace,
		$sourceLanguage,
		array $field,
		$flatName,
		$value,
		$convert = true
	) {
		$this->write(
			$optionsNamespace,
			$sourceLanguage,
			$field,
			$flatName,
			$value,
			$convert,
			false,
			self::ABSENT
		);
	}

	public static function holdsNothing( $optionName ) {
		return in_array( get_option( $optionName, null ), [ null, false, '', [] ], true );
	}

	public static function isAbsent( $optionName ) {
		return null === get_option( $optionName, null );
	}

	private function write(
		$optionsNamespace,
		$sourceLanguage,
		array $field,
		$flatName,
		$value,
		$convert,
		$unslash,
		$writeWhen
	) {
		$optionKey = Obj::prop( 'key', $field );

		foreach ( array_keys( Languages::getActive() ) as $languageCode ) {
			if ( $languageCode === $sourceLanguage ) {
				continue;
			}

			$localOptionName = $optionsNamespace . '_' . $languageCode . '_' . $flatName;

			if ( ! self::mayWrite( $localOptionName, $writeWhen ) ) {
				continue;
			}

			$localValue = $convert
				? $this->convert( $optionsNamespace, $field, $flatName, $value, $languageCode )
				: $value;

			update_option( $localOptionName, $unslash ? wp_unslash( $localValue ) : $localValue );
			update_option( '_' . $localOptionName, $optionKey );
		}
	}

	private static function mayWrite( $optionName, $writeWhen ) {
		if ( self::ABSENT === $writeWhen ) {
			return self::isAbsent( $optionName );
		}

		if ( self::HOLDS_NOTHING === $writeWhen ) {
			return self::holdsNothing( $optionName );
		}

		return true;
	}

	private function convert( $optionsNamespace, array $field, $flatName, $value, $languageCode ) {
		return $this->acfWorker->convertMetaValue(
			$value,
			$flatName,
			Obj::prop( 'type', $field ),
			'post',
			$optionsNamespace,
			$optionsNamespace . '_' . $languageCode,
			$languageCode
		);
	}
}
