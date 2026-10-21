<?php

namespace WPML\LanguageEditor\Save\Phase;

use WPML\OperationRecord\Repository;
use WPML\Posts\TranslatedContentOfLanguages;

class RemovalScope {

	private $codes;

	private $include;

	private $route;

	private $strings;

	private $jobs;

	private $legacy;

	private function __construct( array $codes, array $include, $route, $strings, $jobs, $legacy ) {
		$this->codes   = $codes;
		$this->include = $include;
		$this->route   = $route;
		$this->strings = $strings;
		$this->jobs    = $jobs;
		$this->legacy  = $legacy;
	}

	public static function fromChange( array $change ) {
		$delete = isset( $change['delete'] ) && is_array( $change['delete'] ) ? $change['delete'] : array();
		$codes  = self::codes( $change, $delete );

		$hasInclude = isset( $delete['include'] ) && is_array( $delete['include'] );
		$hasRoute   = isset( $delete['route'] ) && '' !== $delete['route'];
		$hasStrings = array_key_exists( 'strings', $delete );
		$hasJobs    = array_key_exists( 'jobs', $delete );

		if ( ! $hasInclude && ! $hasRoute && ! $hasStrings && ! $hasJobs ) {
			return new self( $codes, TranslatedContentOfLanguages::allTypes(), TranslatedContentOfLanguages::ROUTE_PERMANENT, false, false, true );
		}

		return new self(
			$codes,
			$hasInclude
				? TranslatedContentOfLanguages::normalizeTypes( $delete['include'] )
				: TranslatedContentOfLanguages::allTypes(),
			TranslatedContentOfLanguages::ROUTE_TRASH === ( $hasRoute ? (string) $delete['route'] : '' )
				? TranslatedContentOfLanguages::ROUTE_TRASH
				: TranslatedContentOfLanguages::ROUTE_PERMANENT,
			$hasStrings && (bool) filter_var( $delete['strings'], FILTER_VALIDATE_BOOLEAN ),
			$hasJobs && (bool) filter_var( $delete['jobs'], FILTER_VALIDATE_BOOLEAN ),
			false
		);
	}

	private static function codes( array $change, array $delete ) {
		if ( ! empty( $delete['codes'] ) && is_array( $delete['codes'] ) ) {
			return array_values( array_filter( array_map( 'strval', $delete['codes'] ) ) );
		}

		return ! empty( $change['code'] ) ? array( (string) $change['code'] ) : array();
	}

	public function codesList() {
		return $this->codes;
	}

	public function includeMap() {
		return $this->include;
	}

	public function route() {
		return $this->route;
	}

	public function deletesStrings() {
		return $this->strings;
	}

	public function cancelsJobs() {
		return $this->jobs;
	}

	public function isLegacy() {
		return $this->legacy;
	}

	public function recordRoute() {
		return TranslatedContentOfLanguages::ROUTE_TRASH === $this->route
			? Repository::ROUTE_TRASH
			: Repository::ROUTE_PERMANENT;
	}
}
