<?php

namespace ACFML\Strings\Traversable;

use ACFML\Strings\Config;
use ACFML\Strings\Helper\ContentTypeLabels;
use WPML\FP\Obj;

class Taxonomy extends Entity {

	protected $idKey = 'taxonomy';

	private $labelsInDataMap = [
		'taxonomy' => 'taxonomy',
	];

	private $labelsinContextMap = [
		'description' => 'description',
	];

	protected function getConfig() {
		return Config::getForTaxonomy();
	}

	protected function prepareData( $data, $context ) {
		return array_merge(
			Obj::propOr( Obj::propOr( [], 'labels', $data ), 'labels', $context ),
			ContentTypeLabels::getLabelsInData( $data, $this->labelsInDataMap ),
			ContentTypeLabels::getLabelsInContext( $data, $context, $this->labelsinContextMap )
		);
	}

}
