<?php
require_once WPML_TM_PATH . '/inc/translation-jobs/helpers/wpml-update-translation-data-action.class.php';

class WPML_TM_Update_Post_Translation_Data_Action extends WPML_TM_Update_Translation_Data_Action {

	protected function populate_prev_translation( $rid, array $package ) {
		global $wpml_post_translations;

		$prev_translation = array();
		if ( (bool) ( $lang = $this->get_lang_by_rid( $rid ) ) === true ) {
			list( $prev_job_id ) = $this->get_prev_job_data( $rid );
			$prev_job = $this->get_translation_job( $prev_job_id );

			$translated_contents = array();
			$translated_post_id  = $wpml_post_translations->element_id_in( $package['contents']['original_id']['data'], $lang );
			if ( $translated_post_id ) {
				$package_trans       = $this->package_helper->create_translation_package( $translated_post_id );
				$translated_contents = $package_trans['contents'] ?? array();
			}

			if ( $prev_job ) {
				foreach ( $package['contents'] as $field_name => $field ) {
					if ( array_key_exists( 'translate', $field ) && $field['translate'] ) {
						$element = $this->get_previous_element( $prev_job, $field_name );
						if ( $element && '' !== trim( (string) $element->field_data_translated ) ) {
							$prev_translation[ $field_name ] = new WPML_TM_Translated_Field(
								$element->field_data,
								$element->field_data_translated,
								$this->resolve_field_finished( $element, $translated_contents[ $field_name ]['data'] ?? null )
							);
						}
					}
				}
			}

			foreach ( $package['contents'] as $field_name => $field ) {
				if ( ! array_key_exists( $field_name, $prev_translation )
					 && isset( $translated_contents[ $field_name ]['data'] )
				) {
					$prev_translation[ $field_name ] = new WPML_TM_Translated_Field(
						'',
						$translated_contents[ $field_name ]['data'],
						false );
				}
			}

			$prev_translation = apply_filters( 'wpml_tm_populate_prev_translation', $prev_translation, $package, $lang );
		}

		return $prev_translation;
	}

	private function resolve_field_finished( $element, $live_translation ) {
		if ( $element->field_finished ) {
			return true;
		}

		$stored_translation = trim( (string) $element->field_data_translated );
		if ( '' === $stored_translation ) {
			return false;
		}

		$live = trim( (string) $live_translation );

		return '' !== $live && $live === $stored_translation;
	}

	private function get_previous_element( $prev_job, $field_name ) {
		foreach ( $prev_job->elements as $element ) {
			if ( $element->field_type == $field_name ) {
				return $element;
			}
		}

		return null;
	}
}
