<?php

namespace ACFML\Field;

use WPML\Translation\TranslationElements\FieldCompression;

class LinkUrlSaveHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_REST_Action, \IWPML_DIC_Action {

	const FIELD_TYPE     = LinkJobAttributes::FIELD_TYPE;
	const URL_FIELD_TYPE = 'url';
	const URL_KEY        = 'url';

	private $metaKeyPatterns;

	private $wpdb;

	public function __construct( MetaKeyPatterns $metaKeyPatterns, \wpdb $wpdb ) {
		$this->metaKeyPatterns = $metaKeyPatterns;
		$this->wpdb            = $wpdb;
	}

	public function add_hooks() {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return;
		}

		add_filter( 'wpml_tm_save_translation_cf', [ $this, 'keepManualUrlOfUnchangedSource' ], 10, 2 );
	}

	public function keepManualUrlOfUnchangedSource( $field, $fieldName ) {
		if ( ! is_array( $field )
			|| ! isset( $field['tid'], $field['data'] )
			|| ! is_string( $field['data'] )
			|| ! $this->isUrl( $fieldName )
		) {
			return $field;
		}

		$jobField = $this->getJobFieldAsCreated( (int) $field['tid'] );
		if ( ! $jobField || ! $jobField->automatic || $jobField->translated || ! $jobField->field_finished ) {
			return $field;
		}

		$sourceUrl       = trim( (string) FieldCompression::decompress( $jobField->field_data ) );
		$manualUrl       = trim( (string) FieldCompression::decompress( $jobField->field_data_translated ) );
		$deliveredUrl    = trim( $field['data'] );
		$isManualUrlLost = '' !== $manualUrl && $manualUrl !== $sourceUrl && $deliveredUrl === $sourceUrl;

		if ( $isManualUrlLost ) {
			$field['data'] = $manualUrl;
		}

		return $field;
	}

	private function isUrl( string $fieldName ): bool {
		list( $metaKey, $attributes ) = \WPML_TM_Field_Type_Encoding::decode( $fieldName );

		if ( ! $metaKey ) {
			return false;
		}

		if ( ! $attributes ) {
			return $this->metaKeyPatterns->matchesOnly( self::URL_FIELD_TYPE, $metaKey );
		}

		return self::URL_KEY === end( $attributes )
			&& $this->metaKeyPatterns->matches( self::FIELD_TYPE, $metaKey );
	}

	private function getJobFieldAsCreated( int $jobFieldId ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT job_field.field_data, job_field.field_data_translated, job_field.field_finished, job.automatic, job.translated
				FROM {$wpdb->prefix}icl_translate job_field
				INNER JOIN {$wpdb->prefix}icl_translate_job job ON job.job_id = job_field.job_id
				WHERE job_field.tid = %d",
				$jobFieldId
			)
		);
	}
}
