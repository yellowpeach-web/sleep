<?php

namespace ACFML\Strings;

class TranslationJobHooks implements \IWPML_Action {

	private $factory;

	public function __construct( Factory $factory ) {
		$this->factory = $factory;
	}

	public function add_hooks() {
		if ( self::isEnabled() ) {
			add_filter( 'wpml_translation_package_by_language', [ $this, 'addStringsToTranslationPackage' ], 10, 3 );
			add_action( 'wpml_translation_job_saved', [ $this, 'saveFieldGroupStringsTranslations' ], 10, 3 );
		}
	}

	public static function isEnabled() {
		return defined( 'ACFML_EXCLUDE_FIELD_GROUP_STRINGS_IN_POST_JOBS' ) &&
			false === constant( 'ACFML_EXCLUDE_FIELD_GROUP_STRINGS_IN_POST_JOBS' );
	}

	public function saveFieldGroupStringsTranslations( $translatedPostId, $fields, $job ) {
		$this->factory->createTranslationJobFilter()->saveTranslations( $fields, $job );
	}

	public function addStringsToTranslationPackage( $package, $post, $targetLang ) {
		if ( $post instanceof \WP_Post ) {
			return $this->factory->createTranslationJobFilter()->appendStrings( $package, $post, $targetLang );
		}

		return $package;
	}

}
