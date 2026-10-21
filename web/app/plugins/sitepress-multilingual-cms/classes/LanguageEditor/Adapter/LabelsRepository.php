<?php

namespace WPML\LanguageEditor\Adapter;

use WPML\Core\Component\LanguageEditor\Domain\Repository\LabelsRepositoryInterface;
use WPML\LanguageEditor\Labels;

class LabelsRepository implements LabelsRepositoryInterface {

	private $labels;

	public function __construct( ?Labels $labels = null ) {
		$this->labels = $labels ?: new Labels();
	}

	public function get( string $code, array $targetCodes ): array {
		return $this->labels->get( $code, $targetCodes );
	}

	public function save( string $code, array $labels ): int {
		return $this->labels->save( $code, $labels );
	}
}
