<?php

use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\FP\Type;

class WPML_ACF_Field_Annotations implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const ACF_INPUT_PREFIX = 'acf[';

	private $acf_field_settings;

	public function __construct(
		WPML_ACF_Field_Settings $field_settings
	) {
		$this->acf_field_settings = $field_settings;
	}

	public function add_hooks() {
		if ( ! defined( 'ACFML_HIDE_FIELD_ANNOTATIONS' ) || true !== ACFML_HIDE_FIELD_ANNOTATIONS ) {
			add_action( 'acf/render_field', [ $this, 'renderOriginalValueHint' ] );
			add_filter( 'wpml_post_edit_settings_custom_field_description', [ $this, 'metabox_field_description' ], 10, 3 );
		}
	}

	public function renderOriginalValueHint( $field ) {
		if ( $this->is_acf_options_page() ) {
			return;
		}

		$post_id = get_the_ID();

		if ( $post_id ) {
			$this->field_original_value( $field, $post_id );
		}
	}

	private function field_original_value( $field, $post_id ) {
		static $originalByMetaKey = [];
		$type                     = Obj::prop( 'type', $field );
		$key                      = Obj::prop( 'key', $field );
		$metaKey                  = $this->getMetaKey( $field );

		if ( ! $this->is_secondary_language() ) {
			return;
		}

		if ( ! $type || ! $key || ! $metaKey || 'repeater' === $type ) {
			return;
		}

		if ( Obj::prop( $metaKey, $originalByMetaKey ) ) {
			return;
		}

		$custom_field_original_data = (array) apply_filters( 'wpml_custom_field_original_data', null, $post_id, $metaKey );

		if ( Type::isString( Obj::prop( 'value', $custom_field_original_data ) ) ) {
			echo '<div class="wpml_acf_original_value">';
			echo sprintf(
				/* translators: Displayed when editing an ACF field in a translation, showing its value in the original language; %1$s and %2$s turn the string into bold, and %3$s is the actual original value. */
				esc_html_x(
					'%1$sOriginal%2$s: %3$s',
					'Displayed when editing an ACF field in a translation, showing its value in the original language; %1$s and %2$s turn the string into bold, and %3$s is the actual original value.',
					'acfml'
				),
				'<strong>',
				'</strong>',
				esc_html( $custom_field_original_data['value'] )
			);
			echo '</div>';
		}

		$originalByMetaKey[ $metaKey ] = true;
	}

	private function getMetaKey( $field ) {
		$inputName = (string) Obj::prop( 'name', $field );
		if ( 0 !== strpos( $inputName, self::ACF_INPUT_PREFIX ) ) {
			return Obj::prop( '_name', $field );
		}

		$repeaterType = acf_get_field_type( 'repeater' );
		if ( is_callable( [ $repeaterType, 'get_field_name_from_input_name' ] ) ) {
			return $repeaterType->get_field_name_from_input_name( $inputName ) ?: null;
		}

		return $this->getMetaKeyFromFieldKeys( $inputName );
	}

	private function getMetaKeyFromFieldKeys( string $inputName ) : ?string {
		preg_match_all( '/\[([^\]]*)\]/', $inputName, $inputNameParts );

		$fieldNames = array_map(
			fn( $fieldKey ) => Obj::prop( 'name', acf_get_field( $fieldKey ) ?: [] ) ?: $fieldKey,
			$inputNameParts[1]
		);

		return implode( '_', $fieldNames ) ?: null;
	}

	private function is_secondary_language() {
		$current_language = apply_filters( 'wpml_current_language', null );
		$default_language = apply_filters( 'wpml_default_language', null );

		return $current_language !== $default_language;
	}

	public function metabox_field_description( $description, $name, $post_id ) {

		$field_object = get_field_object( $name, $post_id );

		if ( ! $field_object ) {
			return $description;
		}

		if ( Logic::complement( Logic::both( Obj::prop( 'label' ), Obj::prop( 'type' ) ) )( $field_object ) ) {
			return $description;
		}

		if ( $this->acf_field_settings->field_should_be_set_to_copy_once( $field_object ) ) {
			$field_data = [
				/* translators: Note under a field in the translation editor. The quoted text is the name of a translation preference and is translated the same way in the preference list. */
				__( 'This type of ACF field will always be set to "Copy once".', 'acfml' ),
			];
		} else {
			$field_data = [
				/* translators: Label of the field's name in the note shown under a field in the translation editor; the name follows the colon. */
				__( 'ACF field name:', 'acfml' ),
				$field_object['label'],
				/* translators: Label of the field's kind in the note shown under a field in the translation editor; the kind follows the colon. */
				__( 'ACF field type:', 'acfml' ),
				$field_object['type'],
			];
		}
		$description .= implode( ' ', $field_data );

		return $description;
	}

	private function is_acf_options_page() {
		return is_admin()
			&& function_exists( 'acf_get_options_page' )
			&& acf_get_options_page( sanitize_text_field( wp_unslash( Obj::prop( 'page', $_REQUEST ) ) ) );
	}
}
