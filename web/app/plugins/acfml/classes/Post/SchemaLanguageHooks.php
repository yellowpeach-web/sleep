<?php

namespace ACFML\Post;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class SchemaLanguageHooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const LANGUAGE_PROPERTY = 'inLanguage';

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		Hooks::onFilter( 'acf/schema/data', 10, 2 )
			->then( spreadArgs( [ $this, 'addPostLanguage' ] ) );
	}

	public function addPostLanguage( $schema, $schemaSource ) {
		if ( ! $schemaSource instanceof \WP_Post || isset( $schema[ self::LANGUAGE_PROPERTY ] ) ) {
			return $schema;
		}

		$languageTag = $this->getLanguageTag( $schemaSource );
		if ( $languageTag ) {
			$schema[ self::LANGUAGE_PROPERTY ] = $languageTag;
		}

		return $schema;
	}

	private function getLanguageTag( \WP_Post $post ) {
		$languageCode = $this->sitepress->get_language_for_element( $post->ID, 'post_' . $post->post_type );
		$locale       = $languageCode ? $this->sitepress->get_locale( $languageCode ) : null;

		return $locale ? str_replace( '_', '-', $locale ) : null;
	}
}
