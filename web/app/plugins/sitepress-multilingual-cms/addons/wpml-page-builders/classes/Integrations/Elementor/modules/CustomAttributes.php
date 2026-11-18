<?php

namespace WPML\PB\Elementor\Modules;

use WPML\FP\Obj;
use WPML_Elementor_Module_With_Items;
use WPML_Elementor_Translatable_Nodes;
use WPML_PB_String;
use WPML\PB\Strings\VisibleHtmlAttributes;

class CustomAttributes extends WPML_Elementor_Module_With_Items {

	const FIELD = '_attributes';

	const NAME_PREFIX = '_attributes-';

	const VISIBLE_KEYS = VisibleHtmlAttributes::NAMES;

	public function get_items_field() {
		return self::FIELD;
	}

	public function get_fields() {
		return [];
	}

	protected function get_title( $field ) {
		return 'Custom attribute: ' . $field;
	}

	protected function get_editor_type( $field ) {
		return 'LINE';
	}

	public function get( $node_id, $element, $strings ) {
		foreach ( $this->get_last_lines( $element ) as $key => $line ) {
			$strings[] = new WPML_PB_String(
				$line['value'],
				$this->get_name( $key, $node_id ),
				$this->get_title( $key ),
				$this->get_editor_type( $key )
			);
		}

		return $strings;
	}

	public function update( $node_id, $element, WPML_PB_String $pbString ) {
		foreach ( $this->get_last_lines( $element ) as $key => $line ) {
			if ( $this->get_name( $key, $node_id ) !== $pbString->get_name() ) {
				continue;
			}

			$lines                   = explode( "\n", $this->get_textarea( $element ) );
			$lines[ $line['index'] ] = $line['before'] . $pbString->get_value() . $line['after'];

			return [ self::FIELD . self::FIELD_SEPARATOR, implode( "\n", $lines ) ];
		}

		return [ null, null ];
	}

	public function get_field_path( $key ) {
		return [ self::FIELD ];
	}

	private function get_name( $key, $node_id ) {
		return self::NAME_PREFIX . $key . '-' . $node_id;
	}

	private function get_textarea( $element ) {
		$textarea = Obj::path( [ WPML_Elementor_Translatable_Nodes::SETTINGS_FIELD, self::FIELD ], $element );

		return is_string( $textarea ) ? $textarea : '';
	}

	private function get_last_lines( $element ) {
		if ( null !== Obj::path( [ WPML_Elementor_Translatable_Nodes::SETTINGS_FIELD, '__dynamic__', self::FIELD ], $element ) ) {
			return [];
		}

		$textarea = $this->get_textarea( $element );
		if ( '' === $textarea ) {
			return [];
		}

		$lastLines = [];

		foreach ( explode( "\n", $textarea ) as $index => $rawLine ) {
			$ending = '';
			$line   = $rawLine;
			if ( "\r" === substr( $line, -1 ) ) {
				$ending = "\r";
				$line   = substr( $line, 0, -1 );
			}

			$separator = strpos( $line, '|' );
			if ( false === $separator ) {
				continue;
			}

			$key = trim( substr( $line, 0, $separator ) );
			if ( '' === $key || ! VisibleHtmlAttributes::matches( $key ) ) {
				continue;
			}

			$rest     = substr( $line, $separator + 1 );
			$next     = strpos( $rest, '|' );
			$rawValue = false === $next ? $rest : substr( $rest, 0, $next );
			$tail     = false === $next ? '' : substr( $rest, $next );
			$value    = trim( $rawValue );

			if ( '' === $value ) {
				unset( $lastLines[ $key ] );
				continue;
			}

			$lastLines[ $key ] = [
				'index'  => $index,
				'value'  => $value,
				'before' => substr( $line, 0, $separator + 1 ),
				'after'  => $tail . $ending,
			];
		}

		return $lastLines;
	}
}
