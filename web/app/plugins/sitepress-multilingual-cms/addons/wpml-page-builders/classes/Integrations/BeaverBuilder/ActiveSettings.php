<?php

namespace WPML\PB\BeaverBuilder;

class ActiveSettings {

	private $maps = [];

	private $nestedForms = [];

	public function get( $settings ) {
		if ( ! is_object( $settings ) || ! isset( $settings->type ) || ! class_exists( \FLBuilderModel::class ) ) {
			return $settings;
		}

		$form = \FLBuilderModel::$modules[ $settings->type ]->form ?? null;

		return is_array( $form )
			? $this->filterForm( $settings, 'module:' . $settings->type, $form )
			: $settings;
	}

	private function filterForm( $settings, string $key, array $tabs ) {
		if ( ! isset( $this->maps[ $key ] ) ) {
			$fields             = self::getFields( $tabs );
			$this->maps[ $key ] = [ $fields, self::getControllers( $tabs, $fields ) ];
		}
		list( $fields, $controllers ) = $this->maps[ $key ];

		$active = clone $settings;

		foreach ( $fields as $name => $field ) {
			if ( ! isset( $active->$name ) ) {
				continue;
			}

			if ( ! self::isActive( $name, $settings, $fields, $controllers ) ) {
				$active->$name = self::emptyValue( $active->$name );
			} elseif ( 'form' === ( $field['type'] ?? '' ) && isset( $field['form'] ) ) {
				$nestedTabs = $this->getSettingsForm( $field['form'] );
				if ( is_array( $nestedTabs ) ) {
					$active->$name = $this->filterNested( $active->$name, 'form:' . $field['form'], $nestedTabs );
				}
			}
		}

		return $active;
	}

	private function getSettingsForm( string $id ) {
		if ( ! array_key_exists( $id, $this->nestedForms ) ) {
			$form = \FLBuilderModel::$settings_forms[ $id ] ?? null;
			if ( is_array( $form ) && ! did_action( 'wp' ) ) {
				$form = apply_filters( 'fl_builder_filter_settings_form', $form, $id );
			}

			$this->nestedForms[ $id ] = isset( $form['tabs'] ) && is_array( $form['tabs'] ) ? $form['tabs'] : null;
		}

		return $this->nestedForms[ $id ];
	}

	private function filterNested( $value, string $key, array $tabs ) {
		if ( is_object( $value ) ) {
			return $this->filterForm( $value, $key, $tabs );
		}

		if ( is_array( $value ) ) {
			return array_map(
				fn( $item ) => is_object( $item ) ? $this->filterForm( $item, $key, $tabs ) : $item,
				$value
			);
		}

		return $value;
	}

	private static function isActive( string $name, object $settings, array $fields, array $controllers ): bool {
		foreach ( $controllers[ $name ] ?? [] as $controller => $values ) {
			$value = isset( $settings->$controller ) ? $settings->$controller : self::getDefault( $fields[ $controller ] ?? [] );

			if ( is_scalar( $value ) && ! in_array( (string) $value, $values, true ) ) {
				return false;
			}
		}

		return true;
	}

	private static function getDefault( array $field ) {
		if ( isset( $field['default'] ) ) {
			return $field['default'];
		}

		return isset( $field['options'] ) && is_array( $field['options'] ) && $field['options']
			? array_key_first( $field['options'] )
			: '';
	}

	private static function getControllers( array $tabs, array $fields ): array {
		$controllers = [];

		foreach ( $fields as $controller => $field ) {
			if ( ! isset( $field['toggle'] ) || ! is_array( $field['toggle'] ) ) {
				continue;
			}

			foreach ( $field['toggle'] as $value => $toggle ) {
				$controllers = self::addTargets( $controllers, $controller, (string) $value, self::getToggledFields( $tabs, (array) $toggle ) );
			}
		}

		return $controllers;
	}

	private static function addTargets( array $controllers, string $controller, string $value, array $names ): array {
		foreach ( $names as $name ) {
			$controllers[ $name ][ $controller ][] = $value;
		}

		return $controllers;
	}

	private static function getToggledFields( array $tabs, array $toggle ): array {
		$names = array_values( (array) ( $toggle['fields'] ?? [] ) );

		foreach ( (array) ( $toggle['tabs'] ?? [] ) as $tabId ) {
			if ( isset( $tabs[ $tabId ] ) && is_array( $tabs[ $tabId ] ) ) {
				$names = array_merge( $names, array_keys( self::getFields( [ $tabs[ $tabId ] ] ) ) );
			}
		}

		$sectionIds = (array) ( $toggle['sections'] ?? [] );
		if ( $sectionIds ) {
			foreach ( $tabs as $tab ) {
				foreach ( (array) ( $tab['sections'] ?? [] ) as $sectionId => $section ) {
					if ( in_array( $sectionId, $sectionIds, true ) ) {
						$names = array_merge( $names, array_keys( (array) ( $section['fields'] ?? [] ) ) );
					}
				}
			}
		}

		return array_unique( array_filter( $names, 'is_string' ) );
	}

	private static function getFields( array $tabs ): array {
		$fields = [];

		foreach ( $tabs as $tab ) {
			if ( ! is_array( $tab ) ) {
				continue;
			}
			foreach ( (array) ( $tab['sections'] ?? [] ) as $section ) {
				foreach ( (array) ( $section['fields'] ?? [] ) as $name => $field ) {
					if ( is_array( $field ) ) {
						$fields[ $name ] = $field;
					}
				}
			}
		}

		return $fields;
	}

	private static function emptyValue( $value ) {
		if ( is_string( $value ) ) {
			return '';
		}

		if ( is_object( $value ) ) {
			return new \stdClass();
		}

		return is_array( $value ) ? [] : $value;
	}
}
