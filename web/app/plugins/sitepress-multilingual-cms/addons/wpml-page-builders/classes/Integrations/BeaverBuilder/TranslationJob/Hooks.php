<?php

namespace WPML\PB\BeaverBuilder\TranslationJob;

use WPML\FP\Obj;
use WPML_Beaver_Builder_Data_Settings;

class Hooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private $dataSettings;

	public function __construct( WPML_Beaver_Builder_Data_Settings $dataSettings ) {
		$this->dataSettings = $dataSettings;
	}

	const FILTER_PACKAGES_TO_TRANSLATE = 'wpml_pb_string_packages_to_translate';

	public function add_hooks() {
		add_filter( 'wpml_tm_translation_job_data', [ $this, 'filterFieldsByPageBuilderKind' ], PHP_INT_MAX, 2 );
		add_filter( self::FILTER_PACKAGES_TO_TRANSLATE, [ $this, 'filterPackagesByPageBuilderKind' ], 10, 2 );
	}

	public function filterFieldsByPageBuilderKind( array $translationPackage, $post ) {
		if ( ! $this->isPostPackage( $translationPackage, $post ) ) {
			return $translationPackage;
		}

		$packages = (array) apply_filters( 'wpml_st_get_post_string_packages', [], $post->ID );
		$offered  = (array) apply_filters( self::FILTER_PACKAGES_TO_TRANSLATE, $packages, $post->ID );

		$packageIdsToRemove = wpml_collect( $packages )
			->pluck( 'ID' )
			->diff( wpml_collect( $offered )->pluck( 'ID' ) )
			->values()
			->all();

		return $this->removeFieldsOfPackages( $translationPackage, $packageIdsToRemove );
	}

	public function filterPackagesByPageBuilderKind( $packages, $postId ) {
		if ( ! is_array( $packages ) || ! $packages ) {
			return $packages;
		}

		$kindSlugToRemove = $this->dataSettings->is_handling_post( $postId ) ? 'gutenberg' : 'beaver-builder';

		return array_filter(
			$packages,
			function ( $package ) use ( $kindSlugToRemove ) {
				return $kindSlugToRemove !== Obj::prop( 'kind_slug', $package );
			}
		);
	}

	private function isPostPackage( array $translationPackage, $post ) {
		return 'external' !== $translationPackage['type'] && isset( $post->ID );
	}

	private function removeFieldsOfPackages( array $translationPackage, array $packageIdsToRemove ) {
		if ( $packageIdsToRemove ) {
			$pattern = '/^package-string-(' . implode( '|', array_map( 'intval', $packageIdsToRemove ) ) . ')-/';

			$isFieldFromPackageToRemove = function( $value, $key ) use ( $pattern ) {
				return preg_match( $pattern, $key );
			};

			$translationPackage['contents'] = wpml_collect( $translationPackage['contents'] )
				->reject( $isFieldFromPackageToRemove )
				->toArray();
		}

		return $translationPackage;
	}
}
