<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\BoldNames;
use WPML\FP\Obj;

class ModeValidity {

	const NO_VALUE  = 'no-value';
	const CONTAINER = 'container';
	const MACHINE   = 'machine';

	const NO_VALUE_TYPES = [
		'accordion',
		'message',
		'separator',
		'tab',
	];

	const CONTAINER_TYPES = [
		'repeater',
		'flexible_content',
		'group',
		'clone',
	];

	const MACHINE_TYPES = [
		'email',
		'number',
		'color_picker',
		'date_picker',
		'date_time_picker',
		'time_picker',
		'select',
		'checkbox',
		'radio',
		'button_group',
		'true_false',
	];

	public static function getTypeClass( $field ) {
		$type = self::getType( $field );

		if ( in_array( $type, self::NO_VALUE_TYPES, true ) ) {
			return self::NO_VALUE;
		}

		if ( in_array( $type, self::CONTAINER_TYPES, true ) ) {
			return self::CONTAINER;
		}

		if ( in_array( $type, self::MACHINE_TYPES, true ) ) {
			return self::MACHINE;
		}

		return null;
	}

	public static function storesNoValue( $field ) {
		return self::NO_VALUE === self::getTypeClass( $field );
	}

	public static function isTranslateOffered( $field ) {
		$class = self::getTypeClass( $field );

		return self::CONTAINER !== $class && self::NO_VALUE !== $class;
	}

	public static function isTranslateFlagged( $field ) {
		return self::MACHINE === self::getTypeClass( $field );
	}

	public static function getReason( $field ) {
		$type = self::getType( $field );

		if ( in_array( $type, self::CONTAINER_TYPES, true ) ) {
			/* translators: Explanation shown on a repeater, group or flexible-content field. "Translate" is the name of a translation preference and is translated the same way it is in the preference list. */
			return esc_html__( 'Translate is not offered on container fields, because it replaces the value your theme reads. The fields inside carry their own preferences.', 'acfml' );
		}

		switch ( $type ) {
			case 'url':
				/* translators: Explanation shown on a URL field. "Translate" is the name of a translation preference. */
				return esc_html__( 'Translate lets each language point at its own address, which an external link often needs. A link to a page on this site already points at that page in the reader\'s language, so leave it as it is.', 'acfml' );
			case 'link':
				/* translators: Explanation shown on a link field, which stores a link text, a URL and a target. */
				return esc_html__( 'Translate sends the link text and the URL. The target keeps the value you set, and a URL you leave as it is points at the page in the reader\'s language.', 'acfml' );
			case 'email':
				/* translators: Explanation shown on an e-mail field for why translation is not offered. */
				return esc_html__( 'Translating an address produces one that does not exist. An e-mail address is the same in every language.', 'acfml' );
			case 'color_picker':
				/* translators: Explanation shown on a colour-picker field for why translation is not offered. */
				return esc_html__( 'A color code is not language content. Translating it changes the color your visitors see.', 'acfml' );
			case 'date_picker':
			case 'date_time_picker':
			case 'time_picker':
				/* translators: Explanation shown on a date or time field for why translation is not offered. */
				return esc_html__( 'The stored value is a fixed date format that your theme reads. Translating it breaks the format.', 'acfml' );
			case 'select':
			case 'checkbox':
			case 'radio':
			case 'button_group':
				/* translators: Explanation shown on a select, checkbox, radio or button-group field for why translation is not offered. Keep the bold tags around the WPML screen name. */
				return BoldNames::render( __( 'The stored value is the choice key, not the label your visitors see. Translating it stops the key from matching your choices. Translate the labels in <b>String Translation</b> instead.', 'acfml' ) );
			case 'number':
			case 'true_false':
				/* translators: Explanation shown on a number or true/false field for why translation is not offered. */
				return esc_html__( 'This value is read by your theme or plugin code. A translated value can stop matching what the code expects.', 'acfml' );
		}

		return '';
	}

	private static function getType( $field ) {
		if ( is_string( $field ) ) {
			return $field;
		}

		return (string) Obj::propOr( '', 'type', $field );
	}
}
