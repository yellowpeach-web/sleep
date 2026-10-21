<?php

use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\FP\Str;

class WPML_ACF_Term_Id {
	public $id;
	private $wpml_acf_field;

	public function __construct( $id, WPML_ACF_Field $wpml_acf_field ) {
		$this->id             = $id;
		$this->wpml_acf_field = $wpml_acf_field;
	}

	public function convert() {
		$fieldKey = Obj::prop( 'key', $this->wpml_acf_field->meta_data );
		if ( null === $fieldKey ) {
			return $this;
		}

		$objectId = $this->getObjectId( $this->wpml_acf_field->meta_data );
		if ( null === $objectId ) {
			return $this;
		}

		$field_object = get_field_object( $fieldKey, $objectId );
		if ( ! empty( $field_object['taxonomy'] ) ) {
			$this->id = apply_filters( 'wpml_object_id', $this->id, $field_object['taxonomy'], true, $this->wpml_acf_field->target_lang );
		}

		return $this;
	}

	private function getObjectId( $metaData ) {
		$context = Obj::prop( 'context', $metaData );
		switch ( $context ) {
			case \WPML_ACF_Worker::METADATA_CONTEXT_TERM_FIELD:
				return Logic::ifElse( Logic::isNotNull(), [ self::class, 'normalizeId' ], Fns::identity(), Obj::prop( 'master_term_id', $metaData ) );
			case \WPML_ACF_Worker::METADATA_CONTEXT_POST_FIELD:
			default:
				return Obj::prop( 'master_post_id', $metaData );
		}
	}

	public static function normalizeId( $id ) {
		return Logic::ifElse( Str::startsWith( 'term_' ), Fns::identity(), Str::concat( 'term_' ), $id );
	}
}
