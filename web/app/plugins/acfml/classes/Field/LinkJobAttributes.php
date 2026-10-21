<?php

namespace ACFML\Field;

class LinkJobAttributes implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_REST_Action, \IWPML_DIC_Action {

	const FIELD_TYPE = 'link';

	private $metaKeyPatterns;

	public function __construct( MetaKeyPatterns $metaKeyPatterns ) {
		$this->metaKeyPatterns = $metaKeyPatterns;
	}

	public function add_hooks() {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return;
		}

		add_filter( 'wpml_translation_job_post_meta_value_translated', [ $this, 'onlyTheLinkContentTranslates' ], 10, 2 );
	}

	public function onlyTheLinkContentTranslates( $isTranslatable, $fieldType ) {
		if ( ! $isTranslatable || ! is_string( $fieldType ) ) {
			return $isTranslatable;
		}

		list( $metaKey, $attributes ) = \WPML_TM_Field_Type_Encoding::decode( $fieldType );

		if ( ! $metaKey || ! $attributes || ! $this->metaKeyPatterns->matches( self::FIELD_TYPE, $metaKey ) ) {
			return $isTranslatable;
		}

		return in_array( end( $attributes ), CompositeValue::getSubKeysForType( self::FIELD_TYPE ), true )
			? $isTranslatable
			: 0;
	}
}
