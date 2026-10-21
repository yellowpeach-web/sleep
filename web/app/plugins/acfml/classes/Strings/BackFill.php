<?php

namespace ACFML\Strings;

use ACFML\Options;
use ACFML\Options\ValueRegistration;
use WPML\FP\Obj;
use WPML_ACF;

class BackFill {

	const DONE_KEY = 'register-missing-strings';

	const STATUS_DONE = 'done';

	const COPY_DONE_KEY = 'copy-existing-option-values';

	private $translator;

	private $valueRegistration;

	public function __construct( Translator $translator, ValueRegistration $valueRegistration ) {
		$this->translator        = $translator;
		$this->valueRegistration = $valueRegistration;
	}

	public function runOnce() {
		if ( self::isDone() ) {
			return false;
		}

		return $this->run();
	}

	public function run() {
		if ( ! self::canRun() ) {
			return false;
		}

		$this->registerMissing();
		self::markDone();

		return true;
	}

	public static function canRun() {
		return function_exists( 'acf_get_field_groups' )
			&& HooksFactory::isStActivated()
			&& WPML_ACF::isWpmlSetupComplete();
	}

	public function copyOnce() {
		if ( self::isCopyDone() || ! self::canRun() ) {
			return false;
		}

		$this->valueRegistration->copyAll();
		Options::set( self::COPY_DONE_KEY, self::STATUS_DONE );

		return true;
	}

	public static function isCopyDone() {
		return null !== Options::get( self::COPY_DONE_KEY );
	}

	public static function isDone() {
		return null !== Options::get( self::DONE_KEY );
	}

	public static function markDone() {
		Options::set( self::DONE_KEY, self::STATUS_DONE );
	}

	public function registerMissing() {
		$this->registerFieldGroups();
		$this->registerCpts();
		$this->registerTaxonomies();
		$this->registerOptionsPages();
		$this->valueRegistration->registerAll();
	}

	private function registerFieldGroups() {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return;
		}

		wpml_collect( acf_get_field_groups() )
			->reject(
				function ( $fieldGroup ) {
					return self::hasPackage( Obj::prop( 'key', $fieldGroup ), Package::FIELD_GROUP_PACKAGE_KIND_SLUG )
						|| self::hasPackageNamedByIdPendingMigration( Obj::prop( 'ID', $fieldGroup ) );
				}
			)
			->map( [ $this->translator, 'registerGroupAndFieldsAndLayouts' ] );
	}

	private static function hasPackageNamedByIdPendingMigration( ?int $fieldGroupId ): bool {
		return $fieldGroupId && Package::create( $fieldGroupId )->exists();
	}

	private function registerCpts() {
		if ( ! function_exists( 'acf_get_acf_post_types' ) ) {
			return;
		}

		wpml_collect( acf_get_acf_post_types() )
			->reject(
				function ( $postData ) {
					return self::hasPackage( Obj::prop( 'post_type', $postData ), Package::CPT_PACKAGE_KIND_SLUG );
				}
			)
			->map( [ $this->translator, 'registerCpt' ] );
	}

	private function registerTaxonomies() {
		if ( ! function_exists( 'acf_get_acf_taxonomies' ) ) {
			return;
		}

		wpml_collect( acf_get_acf_taxonomies() )
			->reject(
				function ( $taxonomyData ) {
					return self::hasPackage( Obj::prop( 'taxonomy', $taxonomyData ), Package::TAXONOMY_PACKAGE_KIND_SLUG );
				}
			)
			->map( [ $this->translator, 'registerTaxonomy' ] );
	}

	private function registerOptionsPages() {
		if ( ! function_exists( 'acf_get_options_pages' ) ) {
			return;
		}

		$optionsPages = acf_get_options_pages();
		if ( ! is_array( $optionsPages ) ) {
			return;
		}

		foreach ( $optionsPages as $optionsPage ) {
			if ( ! self::hasPackage( Obj::prop( 'menu_slug', $optionsPage ), Package::OPTION_PAGE_PACKAGE_KIND_SLUG ) ) {
				$this->translator->registerOptionsPage( $optionsPage );
			}
		}
	}

	private static function hasPackage( $id, $kind ) {
		if ( ! $id ) {
			return true;
		}
		return Package::STATUS_NOT_REGISTERED !== Package::create( $id, $kind )->getStatus();
	}
}
