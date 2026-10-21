<?php

namespace ACFML\Field;

class FlexibleLayoutJobFields implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_REST_Action, \IWPML_DIC_Action {

	const FIELD_TYPE = 'flexible_content';

	const TERM_META_LEAF = '/^tfield-(.+)-\d+_\d+$/';

	private $metaKeyPatterns;

	public function __construct( MetaKeyPatterns $metaKeyPatterns ) {
		$this->metaKeyPatterns = $metaKeyPatterns;
	}

	public function add_hooks() {
		add_filter( 'wpml_translation_job_post_meta_value_translated', [ $this, 'keepLayoutNamesOut' ], 10, 2 );
	}

	public function keepLayoutNamesOut( $isTranslatable, $fieldType ) {
		if ( ! $isTranslatable || ! is_string( $fieldType ) ) {
			return $isTranslatable;
		}

		$metaKey = $this->getMetaKeyOfListLeaf( $fieldType );

		return $metaKey && $this->metaKeyPatterns->matches( self::FIELD_TYPE, $metaKey )
			? 0
			: $isTranslatable;
	}

	private function getMetaKeyOfListLeaf( string $fieldType ) {
		if ( preg_match( self::TERM_META_LEAF, $fieldType, $matches ) ) {
			return $matches[1];
		}

		list( $metaKey, $attributes ) = \WPML_TM_Field_Type_Encoding::decode( $fieldType );

		return $metaKey && 1 === count( $attributes ) ? $metaKey : null;
	}
}
