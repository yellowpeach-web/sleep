<?php

namespace WPML\PB\BeaverBuilder\TranslationJob;

use WPML\FP\Obj;
use WPML\PB\BeaverBuilder\Component\Overrides;
use WPML_Beaver_Builder_Data_Settings;
use WPML_Beaver_Builder_Integration_Factory;
use WPML_Beaver_Builder_Translatable_Nodes;
use WPML_Package;
use WPML_PB_String;
use WPML_TM_Page_Builders_Field_Wrapper;

class HiddenFields implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const PACKAGES_TO_TRANSLATE_PRIORITY = Hooks::PACKAGES_TO_TRANSLATE_PRIORITY + 1;

	private $dataSettings;

	private $translatableNodes;

	public function __construct( WPML_Beaver_Builder_Data_Settings $dataSettings, WPML_Beaver_Builder_Translatable_Nodes $translatableNodes ) {
		$this->dataSettings      = $dataSettings;
		$this->translatableNodes = $translatableNodes;
	}

	public function add_hooks() {
		add_filter( 'wpml_tm_translation_job_data', [ $this, 'removeHiddenFields' ], Hooks::JOB_DATA_PRIORITY, 2 );
		add_filter( Hooks::FILTER_PACKAGES_TO_TRANSLATE, [ $this, 'removeHiddenStringsFromPackages' ], self::PACKAGES_TO_TRANSLATE_PRIORITY, 2 );
	}

	public function removeHiddenStringsFromPackages( $packages, $postId ) {
		if ( ! is_array( $packages ) || ! $packages || ! $this->dataSettings->is_handling_post( (int) $postId ) ) {
			return $packages;
		}

		$hiddenStrings = $this->getHiddenStrings( (int) $postId );
		if ( ! $hiddenStrings ) {
			return $packages;
		}

		foreach ( $packages as $packageKey => $package ) {
			if ( $this->isBeaverPackage( $package ) ) {
				$packages[ $packageKey ] = new PackageWithoutHiddenStrings( $package, $this->getHiddenStringNames( $package, $hiddenStrings ) );
			}
		}

		return $packages;
	}

	public function removeHiddenFields( array $translationPackage, $post ) {
		if ( 'external' === $translationPackage['type'] || ! isset( $post->ID ) ) {
			return $translationPackage;
		}

		foreach ( $this->getPostIdsWithJobStrings( (int) $post->ID ) as $postId ) {
			$translationPackage = $this->removeHiddenFieldsOfPost( $translationPackage, $postId );
		}

		return $translationPackage;
	}

	private function getPostIdsWithJobStrings( int $jobSourcePostId ): array {
		$originalPostId = (int) apply_filters( 'wpml_original_element_id', null, $jobSourcePostId, 'post_' . get_post_type( $jobSourcePostId ) );

		return array_unique( array_filter( [ $jobSourcePostId, $originalPostId ] ) );
	}

	private function removeHiddenFieldsOfPost( array $translationPackage, int $postId ): array {
		if ( ! $this->dataSettings->is_handling_post( $postId ) ) {
			return $translationPackage;
		}

		$hiddenStrings = $this->getHiddenStrings( $postId );
		if ( ! $hiddenStrings ) {
			return $translationPackage;
		}

		foreach ( (array) apply_filters( 'wpml_st_get_post_string_packages', [], $postId ) as $package ) {
			if ( $this->isBeaverPackage( $package ) ) {
				foreach ( $this->getFieldSlugsOfHiddenStrings( $package, $hiddenStrings ) as $fieldSlug ) {
					unset( $translationPackage['contents'][ $fieldSlug ] );
				}
			}
		}

		return $translationPackage;
	}

	private function getHiddenStrings( int $postId ): array {
		$nodes         = Overrides::collectNodes( (array) get_post_meta( $postId, $this->dataSettings->get_meta_field(), true ) );
		$hiddenStrings = [];
		foreach ( $nodes as $node ) {
			if ( isset( $node->type ) && 'module' === $node->type && $node->settings instanceof \stdClass ) {
				$hiddenStrings = array_merge( $hiddenStrings, $this->getHiddenStringsOfNode( (string) $node->node, $node->settings ) );
			}

			foreach ( Overrides::getOverrideTargets( $node, $nodes ) as $target ) {
				$hiddenStrings = array_merge( $hiddenStrings, $this->getHiddenStringsOfNode( $target['node_id'], $target['settings'] ) );
			}
		}

		return $hiddenStrings;
	}

	private function getHiddenStringsOfNode( string $nodeId, \stdClass $settings ): array {
		return $this->translatableNodes->get_inactive( $nodeId, $settings, $this->translatableNodes->get( $nodeId, $settings ) );
	}

	private function getFieldSlugsOfHiddenStrings( WPML_Package $package, array $hiddenStrings ): array {
		$hiddenNames = $this->getHiddenStringNames( $package, $hiddenStrings );

		$fieldSlugs = [];
		foreach ( $package->get_package_strings() ?: [] as $packageString ) {
			if ( isset( $hiddenNames[ $packageString->name ] ) ) {
				$fieldSlugs[] = WPML_TM_Page_Builders_Field_Wrapper::generate_field_slug( $package->ID, $packageString->id );
			}
		}

		return $fieldSlugs;
	}

	private function getHiddenStringNames( WPML_Package $package, array $hiddenStrings ): array {
		$hiddenNames = [];
		foreach ( $hiddenStrings as $hiddenString ) {
			$hiddenNames[ $package->sanitize_string_name( $hiddenString->get_name() ) ] = true;
		}

		return $hiddenNames;
	}

	private function isBeaverPackage( $package ): bool {
		return $package instanceof WPML_Package && WPML_Beaver_Builder_Integration_Factory::SLUG === Obj::prop( 'kind_slug', $package );
	}
}
