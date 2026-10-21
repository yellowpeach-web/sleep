<?php

namespace ACFML\Options;

use ACFML\Field\CompositeValue;
use ACFML\Helper\Fields;
use ACFML\Strings\Factory;
use ACFML\Strings\Package;
use WPML\FP\Obj;

class ValueRegistration {

	private $factory;

	private $valueCopy;

	private $storageLanguage;

	public function __construct( Factory $factory, ValueCopy $valueCopy, StorageLanguage $storageLanguage ) {
		$this->factory         = $factory;
		$this->valueCopy       = $valueCopy;
		$this->storageLanguage = $storageLanguage;
	}

	public function registerAll( $optionsNamespace = null ) {
		if ( ! function_exists( 'acf_get_options_pages' ) || ! function_exists( 'acf_get_field_groups' ) ) {
			return;
		}

		$walksNamespace = fn( $namespace ) => null === $optionsNamespace
			|| (string) $namespace === (string) $optionsNamespace;

		foreach ( array_filter( $this->getFieldGroupsByNamespace(), $walksNamespace, ARRAY_FILTER_USE_KEY ) as $namespace => $fieldGroups ) {
			$this->registerNamespaceAndDropStringsNoValuePointsAt( $namespace, $fieldGroups );
		}
	}

	private function registerNamespaceAndDropStringsNoValuePointsAt( $optionsNamespace, array $fieldGroups ) {
		$package = $this->factory->createPackage( $optionsNamespace, Package::OPTION_PACKAGE_KIND_SLUG );

		$package->recordRegisteredStrings();

		foreach ( $fieldGroups as $fieldGroup ) {
			$this->registerFields( $package, $optionsNamespace, acf_get_fields( $fieldGroup ), '' );
		}

		$package->cleanupUnusedStrings();
	}

	public function copyAll() {
		if ( ! function_exists( 'acf_get_options_pages' ) || ! function_exists( 'acf_get_field_groups' ) ) {
			return;
		}

		$sourceLanguage = $this->storageLanguage->get();

		foreach ( $this->getFieldGroupsByNamespace() as $optionsNamespace => $fieldGroups ) {
			foreach ( $fieldGroups as $fieldGroup ) {
				$this->copyFields( $optionsNamespace, acf_get_fields( $fieldGroup ), '', $sourceLanguage );
			}
		}
	}

	private function copyFields( $optionsNamespace, array $fields, $namePrefix, $sourceLanguage ) {
		foreach ( $fields as $field ) {
			$name = Obj::prop( 'name', $field );
			if ( ! $name ) {
				continue;
			}

			$flatName = $namePrefix . $name;

			$this->copyFieldValue( $optionsNamespace, $field, $flatName, $sourceLanguage );

			$subFields = Obj::prop( 'sub_fields', $field );

			switch ( Obj::prop( 'type', $field ) ) {
				case 'group':
					if ( is_array( $subFields ) ) {
						$this->copyFields( $optionsNamespace, $subFields, $flatName . '_', $sourceLanguage );
					}
					break;
				case 'repeater':
					$rowsCount = (int) $this->getValue( $optionsNamespace, $field, $flatName );
					for ( $row = 0; $row < $rowsCount; $row++ ) {
						if ( is_array( $subFields ) ) {
							$this->copyFields( $optionsNamespace, $subFields, $flatName . '_' . $row . '_', $sourceLanguage );
						}
					}
					break;
				case 'flexible_content':
					$this->copyLayoutFields( $optionsNamespace, $field, $flatName, $sourceLanguage );
					break;
			}
		}
	}

	private function copyLayoutFields( $optionsNamespace, array $field, $flatName, $sourceLanguage ) {
		$rowLayoutNames = $this->getValue( $optionsNamespace, $field, $flatName );
		if ( ! is_array( $rowLayoutNames ) ) {
			return;
		}

		$layoutsByName = [];
		foreach ( (array) Obj::prop( 'layouts', $field ) as $layout ) {
			$layoutsByName[ Obj::prop( 'name', $layout ) ] = $layout;
		}

		foreach ( array_values( $rowLayoutNames ) as $row => $layoutName ) {
			$layout    = Obj::prop( $layoutName, $layoutsByName );
			$subFields = $layout ? Obj::prop( 'sub_fields', $layout ) : null;

			if ( is_array( $subFields ) ) {
				$this->copyFields( $optionsNamespace, $subFields, $flatName . '_' . $row . '_', $sourceLanguage );
			}
		}
	}

	private function getFieldGroupsByNamespace() {
		$fieldGroupsByNamespace = [];

		$optionsPages = acf_get_options_pages();
		if ( ! is_array( $optionsPages ) ) {
			return $fieldGroupsByNamespace;
		}

		foreach ( $optionsPages as $optionsPage ) {
			$optionsNamespace = Obj::prop( 'post_id', $optionsPage );
			$menuSlug         = Obj::prop( 'menu_slug', $optionsPage );
			if ( ! $optionsNamespace || ! $menuSlug ) {
				continue;
			}

			foreach ( acf_get_field_groups( [ 'options_page' => $menuSlug ] ) as $fieldGroup ) {
				$fieldGroupsByNamespace[ $optionsNamespace ][ $fieldGroup['key'] ] = $fieldGroup;
			}
		}

		return $fieldGroupsByNamespace;
	}

	private function registerFields( Package $package, $optionsNamespace, array $fields, $namePrefix ) {
		foreach ( $fields as $field ) {
			$name = Obj::prop( 'name', $field );
			if ( ! $name ) {
				continue;
			}

			$flatName = $namePrefix . $name;

			switch ( Obj::prop( 'type', $field ) ) {
				case 'group':
					$this->registerSubFields( $package, $optionsNamespace, $field, $flatName . '_' );
					break;
				case 'repeater':
					$rowsCount = (int) $this->getValue( $optionsNamespace, $field, $flatName );
					for ( $row = 0; $row < $rowsCount; $row++ ) {
						$this->registerSubFields( $package, $optionsNamespace, $field, $flatName . '_' . $row . '_' );
					}
					break;
				case 'flexible_content':
					$this->registerLayoutFields( $package, $optionsNamespace, $field, $flatName );
					break;
				case 'clone':
					$this->registerFields( $package, $optionsNamespace, self::clonedFieldsKeyedAsAcfRestoresThemOnSave( $field ), $namePrefix );
					break;
				default:
					$this->registerFieldValue( $package, $optionsNamespace, $field, $flatName );
			}
		}
	}

	private static function clonedFieldsKeyedAsAcfRestoresThemOnSave( array $clone ): array {
		$restoreKey = fn( array $subField ) => array_merge( $subField, [ 'key' => $subField['__key'] ?? $subField['key'] ] );

		return array_map( $restoreKey, (array) Obj::propOr( [], 'sub_fields', $clone ) );
	}

	private function registerSubFields( Package $package, $optionsNamespace, array $field, $namePrefix ) {
		$subFields = Obj::prop( 'sub_fields', $field );
		if ( is_array( $subFields ) ) {
			$this->registerFields( $package, $optionsNamespace, $subFields, $namePrefix );
		}
	}

	private function registerLayoutFields( Package $package, $optionsNamespace, array $field, $flatName ) {
		$rowLayoutNames = $this->getValue( $optionsNamespace, $field, $flatName );
		if ( ! is_array( $rowLayoutNames ) ) {
			return;
		}

		$layoutsByName = [];
		foreach ( (array) Obj::prop( 'layouts', $field ) as $layout ) {
			$layoutsByName[ Obj::prop( 'name', $layout ) ] = $layout;
		}

		foreach ( array_values( $rowLayoutNames ) as $row => $layoutName ) {
			$layout = Obj::prop( $layoutName, $layoutsByName );
			if ( $layout ) {
				$this->registerSubFields( $package, $optionsNamespace, $layout, $flatName . '_' . $row . '_' );
			}
		}
	}

	private function registerFieldValue( Package $package, $optionsNamespace, array $field, $flatName ) {
		if ( WPML_TRANSLATE_CUSTOM_FIELD !== (int) Obj::prop( 'wpml_cf_preferences', $field ) ) {
			return;
		}

		$value = $this->getValue( $optionsNamespace, $field, $flatName );

		$compositeTexts = CompositeValue::getTexts( $field, $value );
		if ( $compositeTexts ) {
			foreach ( $compositeTexts as $subKey => $text ) {
				$compositeField = CompositeValue::asStringField( $field, $flatName, $subKey );
				$package->register( $text, FieldStringData::of( $compositeField, $text ) );
			}
			return;
		}

		if ( ! is_scalar( $value ) ) {
			return;
		}

		$field['name'] = $flatName;
		$package->register( (string) $value, FieldStringData::of( $field, $value ) );
	}

	private function copyFieldValue( $optionsNamespace, array $field, $flatName, $sourceLanguage ) {
		$preference = (int) Obj::prop( 'wpml_cf_preferences', $field );

		if ( ! in_array( $preference, [ WPML_COPY_CUSTOM_FIELD, WPML_COPY_ONCE_CUSTOM_FIELD ], true ) ) {
			return;
		}

		$value = $this->getValue( $optionsNamespace, $field, $flatName );

		if ( null === $value ) {
			return;
		}

		$this->valueCopy->fromStoredValue(
			$optionsNamespace,
			$sourceLanguage,
			$field,
			$flatName,
			$value,
			! Fields::isWrapperOrGroup( $field )
		);
	}

	private function getValue( $optionsNamespace, array $field, $flatName ) {
		$field['name'] = $flatName;

		if ( function_exists( 'acf_get_metadata_by_field' ) ) {
			return acf_get_metadata_by_field( $optionsNamespace, $field );
		}
		return acf_get_value( $optionsNamespace, $field );
	}
}
