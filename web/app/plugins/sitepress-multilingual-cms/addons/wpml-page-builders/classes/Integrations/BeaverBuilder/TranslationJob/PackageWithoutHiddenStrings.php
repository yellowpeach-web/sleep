<?php

namespace WPML\PB\BeaverBuilder\TranslationJob;

use WPML_Package;

class PackageWithoutHiddenStrings extends WPML_Package {

	private $package;

	private $hiddenStringNames;

	public function __construct( WPML_Package $package, array $hiddenStringNames ) {
		parent::__construct( null );

		foreach ( get_object_vars( $package ) as $property => $value ) {
			$this->$property = $value;
		}

		$this->package           = $package;
		$this->hiddenStringNames = $hiddenStringNames;
	}

	public function get_package_strings( $refresh = false ) {
		$strings = $this->package->get_package_strings( $refresh );
		if ( ! is_array( $strings ) ) {
			return $strings;
		}

		return array_values(
			array_filter(
				$strings,
				function ( $packageString ) {
					return ! isset( $this->hiddenStringNames[ $packageString->name ] );
				}
			)
		);
	}

	public function get_translated_strings( $strings ) {
		return $this->package->get_translated_strings( $strings );
	}
}
