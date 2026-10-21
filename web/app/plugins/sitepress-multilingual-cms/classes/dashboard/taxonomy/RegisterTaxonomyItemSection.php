<?php

namespace WPML\TM\Dashboard\Taxonomy;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class RegisterTaxonomyItemSection implements \IWPML_Backend_Action, \IWPML_REST_Action, \IWPML_AJAX_Action {

	const SECTION_ID = 'taxonomy/all';
	const KIND_ID    = 'taxonomy';

	public function add_hooks() {
		Hooks::onFilter( 'wpml_tm_dashboard_item_sections', 20 )
			->then( spreadArgs( [ $this, 'addSection' ] ) );

		Hooks::onFilter( 'wpml_dashboard_initial_script_data', 20 )
			->then( spreadArgs( [ $this, 'injectTaxonomyData' ] ) );
	}

	public function addSection( array $itemSections ): array {
		$itemSections[] = $this->makeSectionViewModel();

		return $itemSections;
	}

	public function injectTaxonomyData( array $data ): array {
		$data['taxonomyData'] = ( new TaxonomyDashboardData() )->get();
		$data['taxonomyApi']  = [
			'rowsUrl'         => rest_url( 'wpml/v1/dashboard/taxonomies' ),
			'rescanLabelsUrl' => rest_url( 'wpml/v1/dashboard/taxonomies/rescan-labels' ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
		];

		return $data;
	}

	private function makeSectionViewModel() {
		$className = '\\WPML\\UserInterface\\Web\\Core\\Component\\Dashboard\\Application\\ViewModel\\ItemSection';

		return new $className(
			self::SECTION_ID,
			/* translators: Name of the section that lists the terms of the taxonomies, on the Translation Dashboard. */
			__( 'Taxonomy Terms', 'sitepress' ),
			/* translators: Column heading and label for the kind of grouping a term belongs to, for example Category or Tag. Singular. */
			__( 'Taxonomy', 'sitepress' ),
			/* translators: Heading above the kinds of grouping a term can belong to, for example Categories and Tags. Plural. */
			__( 'Taxonomies', 'sitepress' ),
			self::KIND_ID,
			false,
			'',
			[]
		);
	}
}
