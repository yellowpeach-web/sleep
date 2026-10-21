<?php

namespace ACFML\Strings;

use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Relation;

class FieldHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const AFTER_LOAD_LOCAL_FIELD_GROUPS_PRIORITY = 30;

	private $factory;

	private $translator;

	public function __construct( Factory $factory, Translator $translator ) {
		$this->factory    = $factory;
		$this->translator = $translator;
	}

	public function add_hooks() {
		add_action( 'acf/update_field_group', [ $this, 'registerGroupAndFieldsAndLayouts' ] );
		$fieldGroupPackageTitles = $this->factory->getFieldGroupPackageTitles();
		add_action( 'acf/update_field_group', [ $fieldGroupPackageTitles, 'syncAllOnShutdown' ] );
		add_action( 'acf/trash_field_group', [ $fieldGroupPackageTitles, 'syncAllOnShutdown' ] );
		add_action( 'acf/untrash_field_group', [ $fieldGroupPackageTitles, 'syncAllOnShutdown' ] );
		add_action( 'acf/delete_field_group', [ $fieldGroupPackageTitles, 'syncAllOnShutdown' ] );

		add_filter( 'acf/load_field_groups', Fns::withoutRecursion( Fns::identity(), [ $this, 'translateGroups' ] ), self::AFTER_LOAD_LOCAL_FIELD_GROUPS_PRIORITY );
		add_filter( 'acf/load_field_group', Fns::withoutRecursion( Fns::identity(), [ $this, 'translateGroup' ] ) );
		add_filter( 'acf/load_field', Fns::withoutRecursion( Fns::identity(), [ $this, 'translateField' ] ) );
		add_filter( 'acf/prepare_field', [ $this, 'translateFieldInFrontendForm' ] );

		add_action( 'acf/delete_field_group', [ $this, 'deleteFieldGroupPackage' ] );

		add_action( 'acf/duplicate_field_group', [ $this, 'duplicateFieldGroup' ] );
	}

	public function registerGroupAndFieldsAndLayouts( $fieldGroup ) {
		$this->translator->registerGroupAndFieldsAndLayouts( $fieldGroup );
	}

	public function translateGroups( $fieldGroups ) {
		if ( self::isAcfFieldGroupScreen() ) {
			return $fieldGroups;
		}

		return array_map( function( $fieldGroup ) {
			return $this->translateGroup( $fieldGroup );
		}, $fieldGroups );
	}

	public function translateGroup( $fieldGroup ) {
		if ( self::isAcfFieldGroupScreen() ) {
			return $fieldGroup;
		}

		$shouldTranslate = apply_filters( 'acfml_should_translate_acf_entity', true, $fieldGroup, 'group' );

		if ( ! $shouldTranslate ) {
			return $fieldGroup;
		}

		return $this->translator->translateGroup( $fieldGroup );
	}

	public function translateField( $field ) {
		if ( self::isAcfFieldGroupScreen() ) {
			return $field;
		}

		if ( ! self::shouldTranslateField( $field ) ) {
			return $field;
		}

		return $this->translator->translateField( $field );
	}

	public function translateFieldInFrontendForm( $field ) {
		if ( ! $field || ! self::isFrontendForm() || ! self::shouldTranslateField( $field ) ) {
			return $field;
		}

		return $this->translator->translateFieldInFrontendForm( $field );
	}

	public function deleteFieldGroupPackage( $fieldGroup ) {
		$this->factory->createPackage( $fieldGroup['key'], Package::FIELD_GROUP_PACKAGE_KIND_SLUG )->delete();
	}

	private static function isFrontendForm() {
		return ! is_admin() && 'acf_form' === acf_get_form_data( 'screen' );
	}

	private static function isAcfFieldGroupScreen() {
		return ! function_exists( 'acf_is_screen' ) || acf_is_screen( 'acf-field-group' );
	}

	private static function shouldTranslateField( array $field ) {
		return ! self::shouldSkipField( $field ) && apply_filters( 'acfml_should_translate_acf_entity', true, $field, 'field' );
	}

	private static function shouldSkipField( array $field ) {
		$isSeamlessClone = Logic::allPass( [
			Relation::propEq( 'type', 'clone' ),
			Relation::propEq( 'display', 'seamless' ),
		] );

		return $isSeamlessClone( $field );
	}

	public function duplicateFieldGroup( $fieldGroup ) {
		$this->flushAcfCache( $fieldGroup );
	}

	private function flushAcfCache( $fieldGroup ) {
		wp_cache_delete( 'acf_get_field_group_posts', 'acf' );
		wp_cache_delete( 'acf_get_field_group_post:key:' . $fieldGroup['key'], 'acf' );
		wp_cache_delete( 'acf_get_field_posts:' . $fieldGroup['ID'], 'acf' );
	}

}
