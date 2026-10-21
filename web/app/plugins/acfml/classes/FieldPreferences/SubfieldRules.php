<?php

namespace ACFML\FieldPreferences;

use ACFML\FieldGroup\InlineBlockHooks;
use ACFML\FieldGroup\Mode;
use ACFML\Helper\Fields;
use WPML\FP\Fns;

class SubfieldRules implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private static $resolvedCache = [];

	private static $rules;

	public static function isCoreResolutionAvailable() {
		return function_exists( 'wpml_resolve_custom_field_preferences' );
	}

	public function add_hooks() {
		if ( ! self::isCoreResolutionAvailable() || ! function_exists( 'acf_get_field_groups' ) ) {
			return;
		}

		add_filter( 'wpml_resolve_custom_field_preferences', [ $this, 'resolve' ], 10, 3 );

		add_filter( 'acf/update_field_group', [ $this, 'invalidate' ], 20, 1 );
		add_filter( 'acf/update_field', [ $this, 'invalidate' ], 20, 1 );
		add_filter( 'acf/delete_field', [ $this, 'invalidate' ], 20, 1 );
		add_filter( 'acf/delete_field_group', [ $this, 'invalidate' ], 20, 1 );
		add_filter( 'acf/trash_field_group', [ $this, 'invalidate' ], 20, 1 );
		add_filter( 'acf/untrash_field', [ $this, 'invalidate' ], 20, 1 );
		add_filter( 'acf/untrash_field_group', [ $this, 'invalidate' ], 20, 1 );
		add_filter( 'acf/update_field_group_active_status', [ $this, 'invalidate' ], 20, 1 );

		add_action( 'switch_blog', [ $this, 'invalidateOnBlogSwitch' ], 10, 0 );

		add_action( 'admin_init', [ \ACFML\Upgrade\Commands\CollapseSubfieldSettings::class, 'run' ], 20 );
	}

	public function invalidate( $value = null ) {
		self::$rules         = null;
		self::$resolvedCache = [];

		return $value;
	}

	public function invalidateOnBlogSwitch() {
		$this->invalidate();
	}

	public function resolve( $resolved, $metaKeys, $type = 'post' ) {
		if ( ! is_array( $metaKeys ) ) {
			return $resolved;
		}
		$type = 'term' === $type ? 'term' : 'post';

		foreach ( $metaKeys as $metaKey ) {
			$mode = self::resolveKey( $metaKey, $type );
			if ( null !== $mode ) {
				$resolved[ $metaKey ] = $mode;
			}
		}

		return $resolved;
	}

	public static function resolveKey( $metaKey, $type = 'post' ) {
		if ( ! is_string( $metaKey ) || '' === $metaKey ) {
			return null;
		}

		$cacheKey = $type . ':' . $metaKey;
		if ( ! array_key_exists( $cacheKey, self::$resolvedCache ) ) {
			$rules = self::getRules();
			self::$resolvedCache[ $cacheKey ] = $rules ? self::resolveWithRules( $rules, $metaKey, $type ) : null;
		}

		return self::$resolvedCache[ $cacheKey ];
	}

	private static function getRules() {
		if ( null === self::$rules ) {
			$built = self::buildRules();
			if ( null === $built ) {
				return [];
			}
			self::$rules = $built;
		}

		return self::$rules;
	}

	public static function buildRules() {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return null;
		}

		$rules = [];
		foreach ( acf_get_field_groups() as $fieldGroup ) {
			$groupCompanion = Mode::LOCALIZATION === Mode::getMode( $fieldGroup )
				? WPML_COPY_ONCE_CUSTOM_FIELD
				: WPML_COPY_CUSTOM_FIELD;
			$types                      = self::elementTypesForGroup( $fieldGroup );
			$isPostMetaInlineBlockGroup = InlineBlockHooks::isPostMetaInlineBlockGroup( $fieldGroup );
			$rowContainerModes          = [];

			$collect = function ( $field, $fieldPattern ) use ( &$rules, &$rowContainerModes, $groupCompanion, $types, $isPostMetaInlineBlockGroup ) {
				$isNamed    = isset( $field['name'] )
					&& is_string( $field['name'] )
					&& '' !== $field['name'];
				$isSubfield = $isNamed && $fieldPattern !== preg_quote( $field['name'] );

				$mode = self::preferenceOrWrapperDefault( $field );
				if ( null === $mode ) {
					return $field;
				}

				$companion = self::isInRowsOfACopyOnceContainer( $fieldPattern, $rowContainerModes )
					? WPML_COPY_ONCE_CUSTOM_FIELD
					: $groupCompanion;

				if ( Fields::isWrapper( $field ) && isset( $field['wpml_cf_preferences'] ) ) {
					$rowContainerModes[ $fieldPattern ] = $mode;
				}

				$addRule = function ( string $pattern ) use ( &$rules, $mode, $companion, $types ) {
					$rules[] = [
						'pattern'   => $pattern,
						'mode'      => $mode,
						'companion' => $companion,
						'types'     => $types,
					];
				};

				if ( $isSubfield || self::isPrefixedCloneField( $field ) || ( $isNamed && $isPostMetaInlineBlockGroup ) ) {
					$addRule( $fieldPattern );
				}

				if ( self::isFlexibleContent( $field ) ) {
					$addRule( $fieldPattern . Fields::LAYOUT_META_SUFFIX );
				}

				return $field;
			};

			Fields::iterate( acf_get_fields( $fieldGroup ), $collect, Fns::identity() );
		}

		return $rules;
	}

	private static function isInRowsOfACopyOnceContainer( string $fieldPattern, array $rowContainerModes ): bool {
		$nearestContainerMode = null;
		foreach ( $rowContainerModes as $containerPattern => $containerMode ) {
			$rowPattern = $containerPattern . Fields::ROW_INDEX_PATTERN;
			if ( 0 === strpos( $fieldPattern, $rowPattern ) ) {
				$nearestContainerMode = $containerMode;
			}
		}

		return WPML_COPY_ONCE_CUSTOM_FIELD === $nearestContainerMode;
	}

	private static function isPrefixedCloneField( array $field ): bool {
		return ! empty( $field['_clone'] )
			&& isset( $field['name'], $field['__name'] )
			&& $field['name'] !== $field['__name'];
	}

	private static function preferenceOrWrapperDefault( array $field ) {
		if ( isset( $field['wpml_cf_preferences'] ) && is_numeric( $field['wpml_cf_preferences'] ) ) {
			return (int) $field['wpml_cf_preferences'];
		}

		if ( isset( $field['type'] ) && in_array( $field['type'], Fields::WRAPPER_FIELDS, true ) ) {
			return WPML_COPY_ONCE_CUSTOM_FIELD;
		}

		return null;
	}

	private static function isFlexibleContent( array $field ): bool {
		return isset( $field['type'] ) && 'flexible_content' === $field['type'];
	}

	public static function elementTypesForGroup( $fieldGroup ) {
		$locations = isset( $fieldGroup['location'] ) && is_array( $fieldGroup['location'] )
			? $fieldGroup['location']
			: [];

		$hasTaxonomy = false;
		$hasOther    = false;
		foreach ( $locations as $ruleGroup ) {
			foreach ( (array) $ruleGroup as $rule ) {
				if ( isset( $rule['param'] ) && 'taxonomy' === $rule['param'] ) {
					$hasTaxonomy = true;
				} else {
					$hasOther = true;
				}
			}
		}

		if ( $hasTaxonomy && ! $hasOther ) {
			return [ 'term' ];
		}
		if ( $hasTaxonomy ) {
			return [ 'post', 'term' ];
		}

		return [ 'post' ];
	}

	public static function definitionNames() {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return [];
		}

		$names   = [];
		$collect = function ( $field ) use ( &$names ) {
			if ( isset( $field['name'] ) && is_string( $field['name'] ) && '' !== $field['name'] ) {
				$names[ $field['name'] ] = true;
			}

			return $field;
		};

		foreach ( acf_get_field_groups() as $fieldGroup ) {
			Fields::iterate( acf_get_fields( $fieldGroup ), $collect, Fns::identity() );
		}

		return $names;
	}

	public static function isDefinitionKey( $metaKey, $definitionNames ) {
		$fieldName = 0 === strpos( $metaKey, '_' ) ? substr( $metaKey, 1 ) : $metaKey;

		return isset( $definitionNames[ $metaKey ] ) || isset( $definitionNames[ $fieldName ] );
	}

	public static function resolveWithRules( $rules, $metaKey, $type = 'post' ) {
		$isCompanion = 0 === strpos( $metaKey, '_' );
		$target      = $isCompanion ? substr( $metaKey, 1 ) : $metaKey;

		foreach ( $rules as $rule ) {
			if ( isset( $rule['types'] ) && ! in_array( $type, $rule['types'], true ) ) {
				continue;
			}
			if ( preg_match( '#^' . $rule['pattern'] . '$#', $target ) ) {
				if ( $isCompanion ) {
					return WPML_IGNORE_CUSTOM_FIELD === (int) $rule['mode'] ? null : (int) $rule['companion'];
				}

				return (int) $rule['mode'];
			}
		}

		return null;
	}
}
