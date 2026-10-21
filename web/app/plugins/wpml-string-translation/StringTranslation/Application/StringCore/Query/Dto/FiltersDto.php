<?php

namespace WPML\StringTranslation\Application\StringCore\Query\Dto;

class FiltersDto {

	private $domains;

	public function __construct( array $domains ) {
		$this->domains = $domains;
	}

	public function getDomains(): array {
		return $this->domains;
	}
}