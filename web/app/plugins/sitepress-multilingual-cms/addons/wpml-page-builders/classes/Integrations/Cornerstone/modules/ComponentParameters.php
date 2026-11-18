<?php

namespace WPML\PB\Cornerstone\Modules;

use WPML\FP\Obj;

class ComponentParameters implements \IWPML_Page_Builders_Module {

	const PARAMETER_VALUES_FIELD = '_p_data';
	const COMPONENT_ID_FIELD     = 'component_id';
	const SCHEMA_FIELD           = '_p_json';
	const SCHEMA_KEY_PATTERN     = '/^([\w-]+)(#|\[\])?$/';
	const GROUP_FLAG             = '#';
	const LIST_FLAG              = '[]';
	const GROUP_TYPE             = 'group';
	const DEFAULT_TYPE           = 'text';

	const EDITOR_TYPE_BY_PARAMETER_TYPE = [
		'text'        => 'LINE',
		'textarea'    => 'AREA',
		'text-editor' => 'VISUAL',
		'rich-text'   => 'VISUAL',
		'url'         => 'LINK',
	];

	public function get( $node_id, $settings, $strings ) {
		foreach ( $this->getTranslatableParameters( $settings ) as $parameter ) {
			$value = Obj::path( $parameter['path'], $settings[ self::PARAMETER_VALUES_FIELD ] );

			if ( is_string( $value ) && trim( $value ) ) {
				$strings[] = new \WPML_PB_String(
					$value,
					$this->getStringName( $node_id, $parameter['path'] ),
					$this->getTitle(),
					$parameter['editorType']
				);
			}
		}

		return $strings;
	}

	public function update( $node_id, $settings, \WPML_PB_String $string ) {
		foreach ( $this->getTranslatableParameters( $settings ) as $parameter ) {
			if ( $this->getStringName( $node_id, $parameter['path'] ) === $string->get_name() ) {
				$settings[ self::PARAMETER_VALUES_FIELD ] = Obj::assocPath(
					$parameter['path'],
					$string->get_value(),
					$settings[ self::PARAMETER_VALUES_FIELD ]
				);
			}
		}

		return $settings;
	}

	private function getTranslatableParameters( $settings ) {
		if ( empty( $settings[ self::PARAMETER_VALUES_FIELD ] ) || ! is_array( $settings[ self::PARAMETER_VALUES_FIELD ] ) ) {
			return [];
		}

		return $this->getTextParametersOfSchema( $this->getSchema( Obj::propOr( '', self::COMPONENT_ID_FIELD, $settings ) ), [] );
	}

	private function getSchema( $componentId ) {
		if ( ! $componentId ) {
			return [];
		}

		try {
			$component = cornerstone( 'Components' )->getComponent( $componentId );
		} catch ( \Throwable $exception ) {
			return [];
		}

		$schema = Obj::path( [ 'data', Obj::propOr( '', 'root', (array) $component ), self::SCHEMA_FIELD ], (array) $component );
		$schema = is_string( $schema ) ? json_decode( $schema, true ) : $schema;

		return is_array( $schema ) ? $schema : [];
	}

	private function getTextParametersOfSchema( array $schema, array $parentPath ) {
		$parameters = [];

		foreach ( $schema as $key => $definition ) {
			if ( ! preg_match( self::SCHEMA_KEY_PATTERN, (string) $key, $keyParts ) ) {
				continue;
			}

			$name = $keyParts[1];
			$flag = isset( $keyParts[2] ) ? $keyParts[2] : '';
			$path = array_merge( $parentPath, [ $name ] );

			if ( self::LIST_FLAG === $flag ) {
				continue;
			}

			if ( self::GROUP_FLAG === $flag ) {
				$parameters = array_merge( $parameters, $this->getTextParametersOfSchema( (array) $definition, $path ) );
				continue;
			}

			$type = $this->getParameterType( $definition );

			if ( self::GROUP_TYPE === $type ) {
				$parameters = array_merge( $parameters, $this->getTextParametersOfSchema( (array) Obj::propOr( [], 'params', $definition ), $path ) );
				continue;
			}

			$editorType = Obj::prop( $type, self::EDITOR_TYPE_BY_PARAMETER_TYPE );

			if ( $editorType ) {
				$parameters[] = [
					'path'       => $path,
					'editorType' => $editorType,
				];
			}
		}

		return $parameters;
	}

	private function getParameterType( $definition ) {
		if ( is_array( $definition ) ) {
			return (string) Obj::propOr( self::DEFAULT_TYPE, 'type', $definition );
		}

		$shorthandParts = explode( '|', (string) $definition );

		return count( $shorthandParts ) > 1 ? $shorthandParts[0] : self::DEFAULT_TYPE;
	}

	private function getStringName( $nodeId, array $path ) {
		return self::PARAMETER_VALUES_FIELD . '-' . implode( '.', $path ) . '-component-' . $nodeId;
	}

	private function getTitle() {
		/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
		return esc_html__( 'Component: Parameter', 'sitepress' );
	}
}
