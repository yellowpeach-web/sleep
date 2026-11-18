<?php

namespace WPML\TM\XLIFF;

use WPML\TM\ATE\TranslateEverything\TermBundle;

class TermBundleXliffBuilder {

	private $termBuilder;

	public function __construct( ?TaxonomyTermXliffBuilder $termBuilder = null ) {
		$this->termBuilder = $termBuilder ?: new TaxonomyTermXliffBuilder();
	}

	public function build( $bundleJobId, array $terms, $sourceLang, $targetLang, array $options = [] ) {
		$groups = [];
		foreach ( $terms as $term ) {
			$ttid  = (int) $term->term_taxonomy_id;
			$units = $this->termBuilder->unitsFor( $term, $options );
			foreach ( $units as &$unit ) {
				$unit['id'] = TermBundle::unitId( $unit['id'], $ttid );
			}
			unset( $unit );

			$groups[] = [
				'id'    => 'term-' . $ttid,
				'units' => $units,
			];
		}

		$xliff = new \WPML_TM_XLIFF( '1.2' );
		$xliff->setFileAttributes(
			[
				'original'        => TermBundle::ELEMENT_TYPE . '-' . (int) $bundleJobId,
				'source-language' => $sourceLang,
				'target-language' => $targetLang,
				'datatype'        => 'plaintext',
			]
		);
		$xliff->setBodyGroups( $groups );

		return $xliff->toString();
	}
}
