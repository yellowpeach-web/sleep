<?php

namespace WPML\PB\Config;

use function WPML\FP\tap as tap;

class Hooks implements \IWPML_Action {

	const PRIORITY_AFTER_DEFAULT = 20;

	const PRIORITY_EXTRACT_CONFIG = 10;

	private $parser;

	private $storage;

	private $translatableWidgetsHook;

	public function __construct(
		Parser $parser,
		Storage $storage,
		$translatableWidgetsHook
	) {
		$this->parser                  = $parser;
		$this->storage                 = $storage;
		$this->translatableWidgetsHook = $translatableWidgetsHook;
	}

	public function add_hooks() {
		add_filter( 'wpml_config_array', tap( [ $this, 'extractConfig' ] ), self::PRIORITY_EXTRACT_CONFIG );
		add_filter( $this->translatableWidgetsHook, [ $this, 'extendTranslatableWidgets' ], self::PRIORITY_AFTER_DEFAULT );
	}

	public function extractConfig( array $allConfig ) {
		$this->storage->update( $this->parser->extract( $allConfig ) );

		do_action( 'wpml_elementor_auto_config_clear_cache' );
	}

	public function extendTranslatableWidgets( array $widgets ) {
		foreach ( $this->storage->get() as $name => $config ) {
			$widgets[ $name ] = isset( $widgets[ $name ] )
				? self::mergeWidgetConfig( $widgets[ $name ], $config, $name )
				: $config;
		}

		return $widgets;
	}

	private static function mergeWidgetConfig( array $builtin, array $custom, $widgetName ) {
		if ( isset( $custom['fields_in_item'] ) ) {
			unset( $builtin['integration-class'] );
		}

		$merged           = array_merge( $builtin, $custom );
		$merged['fields'] = self::mergeFields(
			isset( $builtin['fields'] ) ? $builtin['fields'] : [],
			isset( $custom['fields'] ) ? $custom['fields'] : [],
			$widgetName
		);

		if ( isset( $builtin['fields_in_item'] ) || isset( $custom['fields_in_item'] ) ) {
			$merged['fields_in_item'] = isset( $builtin['fields_in_item'] ) ? $builtin['fields_in_item'] : [];
			foreach ( isset( $custom['fields_in_item'] ) ? $custom['fields_in_item'] : [] as $item_of => $fields ) {
				$merged['fields_in_item'][ $item_of ] = isset( $merged['fields_in_item'][ $item_of ] )
					? self::mergeFields( $merged['fields_in_item'][ $item_of ], $fields, $widgetName )
					: $fields;
			}
		}

		return $merged;
	}

	private static function mergeFields( array $builtin, array $custom, $widgetName ) {
		foreach ( $custom as $key => $field ) {
			$existing = self::findMatchingField( $builtin, $key, isset( $field['field'] ) ? $field['field'] : null );

			if ( null !== $existing ) {
				$builtin[ $existing ] = $field;
			} elseif ( is_string( $key ) && isset( $builtin[ $key ] ) ) {
				do_action( 'wpml_pb_config_field_key_of_not_matched', $widgetName, $key, $field );
			} elseif ( is_string( $key ) ) {
				$builtin[ $key ] = $field;
			} else {
				$builtin[] = $field;
			}
		}

		return $builtin;
	}

	private static function findMatchingField( array $fields, $key, $name ) {
		if ( null === $name ) {
			return null;
		}

		if ( is_string( $key ) ) {
			return isset( $fields[ $key ]['field'] ) && $fields[ $key ]['field'] === $name ? $key : null;
		}

		foreach ( $fields as $fieldKey => $field ) {
			if ( ! is_string( $fieldKey ) && isset( $field['field'] ) && $field['field'] === $name ) {
				return $fieldKey;
			}
		}

		return null;
	}
}
