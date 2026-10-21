<?php

namespace ACFML\Strings;

use ACFML\Helper\Fields;
use ACFML\Strings\Transformer\Transformer;

class Translator {

	const CONTEXT_BACKEND       = 'backend';
	const CONTEXT_FRONTEND      = 'frontend';
	const CONTEXT_FRONTEND_FORM = 'frontend-form';

	private $factory;

	private $context = self::CONTEXT_BACKEND;

	public function __construct( Factory $factory ) {
		$this->factory = $factory;
		$this->context = is_admin() ? self::CONTEXT_BACKEND : self::CONTEXT_FRONTEND;
	}

	public function registerGroupAndFieldsAndLayouts( $fieldGroup ) {
		$register = $this->factory->createFieldGroupRegister( $fieldGroup );

		$register->start();

		$this->factory->createFieldGroup( $fieldGroup )->traverse( $register );

		Fields::iterate(
			acf_get_fields( $fieldGroup ),
			$this->getFieldTraverser( $register ),
			$this->getLayoutTraverser( $register )
		);

		$register->end();
	}

	public function translateGroup( $fieldGroup ) {
		return $this->factory->createFieldGroup( $fieldGroup )->traverse( $this->factory->createTranslate( $fieldGroup['key'], Package::FIELD_GROUP_PACKAGE_KIND_SLUG ), $this->context );
	}

	public function translateField( $field ) {
		$translate = $this->createFieldTranslate( $field );

		$wrappedField = Fields::iterate(
			[ $field ],
			$this->getFieldTraverser( $translate, $this->context ),
			$this->getLayoutTraverser( $translate, $this->context )
		);

		return $wrappedField[0];
	}

	public function translateFieldInFrontendForm( $field ) {
		return $this->factory->createField( $field )->traverse( $this->createFieldTranslate( $field ), self::CONTEXT_FRONTEND_FORM );
	}

	private function createFieldTranslate( $field ) {
		return $this->factory->createTranslate( $field['parent'], Package::FIELD_GROUP_PACKAGE_KIND_SLUG );
	}

	private function getFieldTraverser( $transformer, $context = null ) {
		return function( $field ) use ( $transformer, $context ) {
			return $this->factory->createField( $field )->traverse( $transformer, $context );
		};
	}

	private function getLayoutTraverser( $transformer, $context = null ) {
		return function( $layout ) use ( $transformer, $context ) {
			return $this->factory->createLayout( $layout )->traverse( $transformer, $context );
		};
	}

	public function registerCpt( $postData ) {
		$register = $this->factory->createRegister( $postData['post_type'], Package::CPT_PACKAGE_KIND_SLUG );

		$register->start();

		$this->factory->createCpt( $postData )->traverse( $register );

		$register->end();
	}

	public function translateCpt( $postData, $postTypeArgs = [] ) {
		return $this->factory->createCpt( $postData, $postTypeArgs )->traverse( $this->factory->createTranslate( $postData['post_type'], Package::CPT_PACKAGE_KIND_SLUG ), $this->context );
	}

	public function registerTaxonomy( $taxonomyData ) {
		$register = $this->factory->createRegister( $taxonomyData['taxonomy'], Package::TAXONOMY_PACKAGE_KIND_SLUG );

		$register->start();

		$this->factory->createTaxonomy( $taxonomyData )->traverse( $register );

		$register->end();
	}

	public function translateTaxonomy( $taxonomyData, $taxonomyArgs = [] ) {
		return $this->factory->createTaxonomy( $taxonomyData, $taxonomyArgs )->traverse( $this->factory->createTranslate( $taxonomyData['taxonomy'], Package::TAXONOMY_PACKAGE_KIND_SLUG ), $this->context );
	}

	public function registerOptionsPage( $optionsPageData ) {
		$register = $this->factory->createRegister( $optionsPageData['menu_slug'], Package::OPTION_PAGE_PACKAGE_KIND_SLUG );

		$register->start();

		$this->factory->createOptionsPage( $optionsPageData )->traverse( $register );

		$register->end();
	}

	public function translateOptionsPage( $optionsPageData ) {
		return $this->factory->createOptionsPage( $optionsPageData )->traverse( $this->factory->createTranslate( $optionsPageData['menu_slug'], Package::OPTION_PAGE_PACKAGE_KIND_SLUG ), $this->context );
	}

}
