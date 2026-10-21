<?php

namespace ACFML\Strings;

use ACFML\Strings\Helper\ContentTypeLabels;

class TaxonomyHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private $factory;

	private $translator;

	public function __construct( Factory $factory, Translator $translator ) {
		$this->factory    = $factory;
		$this->translator = $translator;
	}

	public function add_hooks() {
		add_action( 'acf/update_taxonomy', [ $this, 'register' ] );
		add_filter( 'acf/taxonomy/registration_args', [ $this, 'translate' ], 10, 2 );
		add_action( 'acf/delete_taxonomy', [ $this, 'delete' ] );
	}

	public function register( $taxonomyData ) {
		$this->translator->registerTaxonomy( $taxonomyData );
	}

	public function translate( $taxonomyArgs, $taxonomyData ) {
		return ContentTypeLabels::translateLabels(
			$taxonomyArgs,
			$this->translator->translateTaxonomy( $taxonomyData, $taxonomyArgs ),
			[ 'description' ]
		);
	}

	public function delete( $taxonomyData ) {
		$this->factory->createPackage( $taxonomyData['taxonomy'], Package::TAXONOMY_PACKAGE_KIND_SLUG )->delete();
	}
}
