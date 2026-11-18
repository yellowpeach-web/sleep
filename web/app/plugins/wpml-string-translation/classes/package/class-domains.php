<?php

namespace WPML\ST\Package;

use WPML\Collect\Support\Collection;

class Domains {

	private $wpdb;

	private $domains;

	private $domainLookup;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function isPackage( $domain ) {
		if ( ! $domain ) {
			return false;
		}

		if ( null === $this->domainLookup ) {
			$this->domainLookup = array_flip( $this->getDomains()->toArray() );
		}

		return isset( $this->domainLookup[ $domain ] );
	}

	public function getDomains() {
		$wpdb = $this->wpdb;

		if ( ! $this->domains ) {
			$this->domains = wpml_collect(
				$this->wpdb->get_col(
					"SELECT CONCAT(kind_slug, '-', name) FROM {$wpdb->prefix}icl_string_packages"
				)
			);
		}

		return $this->domains;
	}
}
