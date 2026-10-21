<?php

namespace ACFML\Strings;

use ACFML\Strings\Transformer\Register;
use ACFML\Strings\Transformer\Translate;
use ACFML\Strings\Traversable\Cpt;
use ACFML\Strings\Traversable\FieldGroup;
use ACFML\Strings\Traversable\Field;
use ACFML\Strings\Traversable\Layout;
use ACFML\Strings\Traversable\OptionsPage;
use ACFML\Strings\Traversable\Taxonomy;
use ACFML\Helper\FieldGroup as GroupHelper;

class Factory {

	private $fieldGroupPackageTitles;

	public function createFieldGroup( $fieldGroup ) {
		return new FieldGroup( $fieldGroup );
	}

	public function createField( $field ) {
		return new Field( $field );
	}

	public function createLayout( $layout ) {
		return new Layout( $layout );
	}

	public function createCpt( $data, $context = [] ) {
		return new Cpt( $data, $context );
	}

	public function createTaxonomy( $data, $context = [] ) {
		return new Taxonomy( $data, $context );
	}

	public function createOptionsPage( $data ) {
		return new OptionsPage( $data );
	}

	public function createPackage( $packageId, $kind = Package::FIELD_GROUP_PACKAGE_KIND_SLUG ) {
		return new Package( $packageId, $kind );
	}

	public function createRegister( $id, $kind ) {
		return new Register(
			$this->createPackage( $id, $kind )
		);
	}

	public function createFieldGroupRegister( array $fieldGroup ) {
		return new Register(
			$this->createPackage( $fieldGroup['key'] )->withTitle( $this->getFieldGroupPackageTitles()->titleOf( $fieldGroup ) )
		);
	}

	public function getFieldGroupPackageTitles() {
		if ( ! $this->fieldGroupPackageTitles ) {
			global $wpdb;
			$this->fieldGroupPackageTitles = new FieldGroupPackageTitles( $wpdb );
		}

		return $this->fieldGroupPackageTitles;
	}

	public function createTranslate( $id, $kind = Package::FIELD_GROUP_PACKAGE_KIND_SLUG ) {
		if ( Package::FIELD_GROUP_PACKAGE_KIND_SLUG === $kind ) {
			return new Translate(
				$this->createPackage( GroupHelper::getKey( $id ), $kind )
			);
		}
		return new Translate(
			$this->createPackage( $id, $kind )
		);
	}

	public function createTranslationJobFilter() {
		return new TranslationJobFilter( $this );
	}

	public static function createWpmlPackage( $data ) {
		return new \WPML_Package( $data );
	}
}
