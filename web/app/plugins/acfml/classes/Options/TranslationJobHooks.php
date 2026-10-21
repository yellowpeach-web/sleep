<?php

namespace ACFML\Options;

use ACFML\Field\CompositeValue;
use ACFML\Helper\Fields;
use ACFML\Strings\Factory;
use ACFML\Strings\Package;
use WPML\FP\Obj;
use WPML\FP\Str;

class TranslationJobHooks implements \IWPML_Backend_Action, \IWPML_REST_Action {

	public function add_hooks() {
		add_action( 'wpml_pro_translation_completed', [ $this, 'saveFieldGroupStringsTranslations' ], 10, 3 );
	}

	public function saveFieldGroupStringsTranslations( $translatedPostId, $fields, $job ) {
		if ( Package::OPTION_PACKAGE_ELEMENT_TYPE !== Obj::prop( 'original_post_type', $job ) ) {
			return;
		}

		$packageObject = Factory::createWpmlPackage( $job->original_doc_id );
		if ( ! isset( $packageObject->name ) ) {
			return;
		}

		$language = $job->language_code;
		foreach ( $job->elements as $element ) {
			$optionFieldName = Str::match( '/^options-(.*?)-(field_(.*?))-(.*?)$/', Obj::propOr( '', 'field_type', $element ) );
			$optionField     = $optionFieldName ? $optionFieldName[1] : '';
			$optionKey       = $optionFieldName ? $optionFieldName[2] : '';

			if ( empty( $optionField ) || empty( $optionKey ) ) {
				continue;
			}

			list( $fieldName, $subKey ) = CompositeValue::splitName( $optionField );

			$optionName      = $packageObject->name . '_' . $language . '_' . $fieldName;
			$translatedValue = $this->getValueToStore(
				base64_decode( Obj::propOr( '', 'field_data_translated', $element ) ),
				$subKey,
				$packageObject->name . '_' . $fieldName,
				$optionName
			);

			if ( null === $translatedValue ) {
				continue;
			}

			update_option( $optionName, $translatedValue );
			update_option( '_' . $optionName, $optionKey );

			$this->copyMissingWrappers( $packageObject->name, $language, $fieldName );
		}
	}

	private function copyMissingWrappers( string $optionsNamespace, string $language, string $fieldName ): void {
		if ( ! preg_match_all( '/_(\d+)(?=_)/', $fieldName, $matches, PREG_OFFSET_CAPTURE ) ) {
			return;
		}

		foreach ( $matches[0] as $level => $match ) {
			$wrapperName = substr( $fieldName, 0, $match[1] );
			$sourceKey   = get_option( '_' . $optionsNamespace . '_' . $wrapperName );
			$sourceField = $sourceKey ? acf_get_field( $sourceKey ) : null;

			if ( ! is_array( $sourceField ) || ! Fields::isWrapper( $sourceField ) ) {
				continue;
			}

			$rowIndex       = (int) $matches[1][ $level ][0];
			$translatedName = $optionsNamespace . '_' . $language . '_' . $wrapperName;
			$sourceValue    = get_option( $optionsNamespace . '_' . $wrapperName );

			if ( ValueCopy::isAbsent( $translatedName ) ) {
				update_option( $translatedName, $sourceValue );
				update_option( '_' . $translatedName, $sourceKey );
				continue;
			}

			$translatedValue = get_option( $translatedName );
			if ( self::countRows( $translatedValue ) <= $rowIndex ) {
				update_option( $translatedName, self::addMissingRows( $translatedValue, $sourceValue ) );
				update_option( '_' . $translatedName, $sourceKey );
			}
		}
	}

	private static function countRows( $wrapperValue ): int {
		return is_array( $wrapperValue ) ? count( $wrapperValue ) : (int) $wrapperValue;
	}

	private static function addMissingRows( $translatedValue, $sourceValue ) {
		if ( is_array( $translatedValue ) && is_array( $sourceValue ) ) {
			return array_merge( $translatedValue, array_slice( $sourceValue, count( $translatedValue ) ) );
		}

		return $sourceValue;
	}

	private function getValueToStore( $translatedValue, $subKey, $sourceOptionName, $optionName ) {
		if ( null === $subKey ) {
			return $translatedValue;
		}

		$storedValue = get_option( $optionName );

		return CompositeValue::merge(
			is_array( $storedValue ) ? $storedValue : get_option( $sourceOptionName ),
			$subKey,
			$translatedValue
		);
	}

}
