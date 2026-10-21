<?php

namespace ACFML\Strings\Traversable;

use ACFML\Strings\Config;
use ACFML\Strings\Helper\ContentTypeLabels;
use WPML\FP\Obj;

class Cpt extends Entity {

	protected $idKey = 'post_type';

	private $labelsInDataMap = [
		'post_type'        => 'post_type',
		'enter_title_here' => 'enter_title_here',
	];

	private $labelsinContextMap = [
		'description' => 'description',
	];

	protected function getConfig() {
		return Config::getForCpt();
	}

	protected function prepareData( $data, $context ) {
		return array_merge(
			Obj::propOr( Obj::propOr( [], 'labels', $data ), 'labels', $context ),
			ContentTypeLabels::getLabelsInData( $data, $this->labelsInDataMap ),
			ContentTypeLabels::getLabelsInContext( $data, $context, $this->labelsinContextMap )
		);
	}

}
