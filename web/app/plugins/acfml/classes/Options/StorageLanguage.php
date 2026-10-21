<?php

namespace ACFML\Options;

use ACFML\Options;
use ACFML\Strings\Package;

class StorageLanguage implements \IWPML_Action {

	const KEY = 'options-storage-language';

	const ACF_DEFAULT_LANGUAGE_FILTER = 'acf/settings/default_language';

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function add_hooks() {
		add_filter( self::ACF_DEFAULT_LANGUAGE_FILTER, [ $this, 'filterDefaultLanguage' ] );
		add_action( 'icl_after_set_default_language', [ $this, 'recordOnDefaultLanguageChange' ], 10, 2 );
		add_action( 'wpml_st_package_created', [ $this, 'setLanguageOfNewOptionsPackage' ] );
	}

	public function get() {
		return (string) ( $this->getRecorded() ?: apply_filters( 'wpml_default_language', null ) );
	}

	public function filterDefaultLanguage( $language ) {
		return $this->getRecorded() ?: $language;
	}

	public function recordOnDefaultLanguageChange( $newDefault, $previousDefault ) {
		$recorded = $this->getRecorded();

		if ( $recorded ) {
			if ( $this->isSwitchBackToRecorded( $recorded, $newDefault ) ) {
				Options::set( self::KEY, null );
			}

			return;
		}

		if ( ! $this->isSetupComplete() ) {
			return;
		}

		if ( $previousDefault && $previousDefault !== $newDefault && $this->hasStoredValues() ) {
			Options::set( self::KEY, $previousDefault );
		}
	}

	public function setLanguageOfNewOptionsPackage( $package ) {
		$recorded = $this->getRecorded();

		if ( ! $recorded || Package::OPTION_PACKAGE_KIND_SLUG !== $package->kind_slug ) {
			return;
		}

		do_action(
			'wpml_set_element_language_details',
			[
				'element_id'       => $package->ID,
				'element_type'     => $package->get_translation_element_type(),
				'trid'             => false,
				'language_code'    => $recorded,
				'check_duplicates' => false,
			]
		);
	}

	private function isSwitchBackToRecorded( $recorded, $newDefault ): bool {
		return $recorded === $newDefault;
	}

	private function isSetupComplete(): bool {
		return (bool) apply_filters( 'wpml_setting', false, 'setup_complete' );
	}

	private function getRecorded() {
		return Options::get( self::KEY );
	}

	private function hasStoredValues() {
		$wpdb = $this->wpdb;

		foreach ( $this->getNamespacesToCheck() as $namespace ) {
			$hasValue = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT 1 FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1",
					$wpdb->esc_like( '_' . $namespace . '_' ) . '%'
				)
			);

			if ( $hasValue ) {
				return true;
			}
		}

		return false;
	}

	private function getNamespacesToCheck(): array {
		$acfDefaultNamespace             = Package::OPTION_PACKAGE_NAMESPACE;
		$registeredOptionsPageNamespaces = array_column( (array) acf_get_options_pages(), 'post_id' );

		return array_unique( array_merge( [ $acfDefaultNamespace ], $registeredOptionsPageNamespaces ) );
	}
}
