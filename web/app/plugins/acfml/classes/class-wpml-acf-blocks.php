<?php

use WPML\FP\Obj;

class WPML_ACF_Blocks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const AMBIGUOUS = '__wpml_acf_ambiguous_path__';

	const MAX_FIELD_DEPTH = 64;

	private $prefixedSubFieldsMap;

	private $ancestorPreferenceCache = [];

	private $blockGroupFieldsCache = [];

	public function add_hooks() {
		add_filter( 'wpml_found_strings_in_block', [ $this, 'add_block_data_attribute_strings' ], 10, 2 );
		add_filter( 'wpml_update_strings_in_block', [ $this, 'update_block_data_attribute' ], 10, 3 );
	}

	public function add_block_data_attribute_strings( array $strings, WP_Block_Parser_Block $block ) {
		if ( $this->is_acf_block( $block ) && isset( $block->attrs['data'] ) ) {
			if ( ! is_array( $block->attrs['data'] ) ) {
				$block->attrs['data'] = [ $block->attrs['data'] ];
			}

			$blockData                     = $block->attrs['data'];
			$this->prefixedSubFieldsMap    = null;
			$this->ancestorPreferenceCache = [];

			$addStringRecursive = function ( $value, $name, $localData, $leafName, array $ancestors ) use ( $block, &$strings, &$addStringRecursive ) {
				if ( $this->is_system_field( (string) $leafName ) ) {
					return;
				}
				$type = $this->get_text_type( $value );

				if ( 'array' === $type ) {
					$innerAncestors   = $ancestors;
					$innerAncestors[] = [
						'leafName'  => (string) $leafName,
						'localData' => $localData,
						'path'      => $name,
					];
					foreach ( $value as $innerName => $innerValue ) {
						$addStringRecursive( $innerValue, $name . '/' . $innerName, $value, (string) $innerName, $innerAncestors );
					}
				} elseif ( ! $this->must_skip( $name, $value, $localData, $leafName, (string) $block->blockName, $ancestors ) ) {
					$strings[] = $this->add_string( $block, $value, $name, $type );
				}
			};

			foreach ( $block->attrs['data'] as $fieldName => $fieldValue ) {
				$addStringRecursive( $fieldValue, $fieldName, $blockData, (string) $fieldName, [] );
			}
		}

		return $strings;
	}

	private function add_string( $block, $text, $field_name, $type ) {
		return (object) [
			'id'    => $this->get_string_hash( $block->blockName, $text ),
			'name'  => $this->get_string_name( $block, $field_name ),
			'value' => $text,
			'type'  => $type,
		];
	}

	public function update_block_data_attribute( WP_Block_Parser_Block $block, array $string_translations, $lang ) {
		if ( $this->is_acf_block( $block ) && isset( $block->attrs['data'] ) ) {
			$blockData                     = $block->attrs['data'];
			$this->prefixedSubFieldsMap    = null;
			$this->ancestorPreferenceCache = [];

			foreach ( $blockData as $field_name => $text ) {
				if ( $this->is_system_field( $field_name ) ) {
					continue;
				}
				$block = $this->set_block_field_translation_recursive( $block, $string_translations, $lang, $text, [ $field_name ], $blockData, (string) $field_name, [] );
			}
		}

		return $block;
	}

	private function set_block_field_translation_recursive( $block, $stringTranslations, $language, $value, $stringPath, array $localData = [], $leafName = null, array $ancestors = [] ) {
		$fieldPath = implode( '/', $stringPath );

		if ( is_array( $value ) ) {
			$innerAncestors   = $ancestors;
			$innerAncestors[] = [
				'leafName'  => (string) $leafName,
				'localData' => $localData,
				'path'      => $fieldPath,
			];
			foreach ( $value as $innerPath => $innerValue ) {
				if ( is_string( $innerPath ) && $this->is_system_field( $innerPath ) ) {
					continue;
				}
				$innerStringPath   = $stringPath;
				$innerStringPath[] = $innerPath;
				$block             = $this->set_block_field_translation_recursive( $block, $stringTranslations, $language, $innerValue, $innerStringPath, $value, (string) $innerPath, $innerAncestors );
			}
		} elseif ( ! $this->must_skip( $fieldPath, $value, $localData, $leafName, (string) $block->blockName, $ancestors ) ) {
			$block = $this->set_block_field_translation( $block, $stringTranslations, $language, $value, $stringPath );
		}

		return $block;
	}

	private function set_block_field_translation( $block, $stringTranslations, $language, $value, $stringPath ) {
		$stringHash = $this->get_string_hash( $block->blockName, $value );

		if (
			isset( $stringTranslations[ $stringHash ][ $language ]['status'] )
			&& ICL_TM_COMPLETE === (int) $stringTranslations[ $stringHash ][ $language ]['status']
			&& isset( $stringTranslations[ $stringHash ][ $language ]['value'] )
			&& Obj::hasPath( $stringPath, $block->attrs['data'] )
		) {
			$block->attrs['data'] = Obj::set(
				Obj::lensPath( $stringPath ),
				$stringTranslations[ $stringHash ][ $language ]['value'],
				$block->attrs['data']
			);
		}

		return $block;
	}

	private function is_acf_block( WP_Block_Parser_Block $block ) {
		$blockName = (string) $block->blockName;
		if ( '' === $blockName ) {
			return false;
		}

		return strpos( $blockName, 'acf/' ) === 0 ||
			function_exists( 'acf_has_block_type' ) && acf_has_block_type( $blockName );
	}

	private function get_string_hash( $block_name, $text ) {
		return md5( $block_name . $text );
	}

	private function get_string_name( WP_Block_Parser_Block $block, $field_name ) {
		return $block->blockName . '/' . $field_name;
	}

	private function is_system_field( $field_name ) {
		$pos  = strrpos( $field_name, '/' );
		$leaf = false === $pos ? $field_name : substr( $field_name, $pos + 1 );

		return strpos( $leaf, '_' ) === 0;
	}

	private function get_text_type( $text ) {
		if ( is_array( $text ) ) {
			return 'array';
		}

		$text = (string) $text;
		$type = 'LINE';
		if ( strip_tags( $text ) !== $text ) {
			$type = 'VISUAL';
		} elseif ( strpos( $text, "\n" ) !== false ) {
			$type = 'AREA';
		} elseif ( filter_var( $text, FILTER_VALIDATE_URL ) ) {
			$type = 'LINK';
		}

		return $type;
	}

	private function must_skip( $fieldName, $text, array $blockData = [], $leafName = null, $blockName = '', array $ancestors = [] ) {
		$leafName = null === $leafName ? $fieldName : $leafName;

		return $this->is_system_field( $fieldName ) ||
			$this->valueIsNotTranslatable( $text ) ||
			$this->isRowStructure( (string) $leafName, $blockData, $blockName, $fieldName, $ancestors ) ||
			! $this->isTranslatableInPreferences( $leafName, $blockData, $blockName, $fieldName, $ancestors );
	}

	private function isRowStructure( string $leafName, array $localData, $blockName, $fieldPath, array $ancestors ): bool {
		if ( \ACFML\Helper\Fields::LAYOUT_KEY === $leafName ) {
			return true;
		}
		if ( $this->isRowContainer( $leafName, $localData, $blockName, $fieldPath ) ) {
			return true;
		}

		$parent = end( $ancestors );

		return $parent && $this->isRowContainer( $parent['leafName'], $parent['localData'], $blockName, $parent['path'] );
	}

	private function isRowContainer( string $fieldName, array $localData, $blockName, $fieldPath ): bool {
		$fieldKey = $localData[ '_' . $fieldName ] ?? $fieldName;
		$acfField = is_string( $fieldKey ) && strpos( $fieldKey, 'field_' ) === 0
			? acf_get_field( $fieldKey )
			: null;
		if ( ! $acfField ) {
			$acfField = $this->getFieldFromBlockGroups( $fieldName, $blockName, $fieldPath, false );
		}

		return is_array( $acfField ) && \ACFML\Helper\Fields::isWrapper( $acfField );
	}

	private function isTranslatableInPreferences( $fieldName, array $blockData = [], $blockName = '', $fieldPath = '', array $ancestors = [] ) {
		$isSubValue = [] !== $ancestors;
		$preference = $this->resolveFieldPreference( $fieldName, $blockData, $blockName, $fieldPath, $isSubValue );

		if ( null === $preference && $isSubValue ) {
			$preference = $this->inheritPreferenceFromAncestors( $ancestors, $blockName );

			if ( null === $preference ) {
				$preference = $this->resolveFieldPreference( $fieldName, $blockData, $blockName, $fieldPath );
			}
		}

		return null === $preference || WPML_TRANSLATE_CUSTOM_FIELD === $preference;
	}

	private function inheritPreferenceFromAncestors( array $ancestors, $blockName ): ?int {
		foreach ( array_reverse( $ancestors, true ) as $depth => $ancestor ) {
			if ( is_numeric( $ancestor['leafName'] ) ) {
				continue;
			}
			$path = $ancestor['path'];
			if ( ! array_key_exists( $path, $this->ancestorPreferenceCache ) ) {
				$this->ancestorPreferenceCache[ $path ] = $this->resolveFieldPreference(
					$ancestor['leafName'],
					$ancestor['localData'],
					$blockName,
					$path,
					0 !== $depth
				);
			}
			if ( null !== $this->ancestorPreferenceCache[ $path ] ) {
				return $this->ancestorPreferenceCache[ $path ];
			}
		}

		return null;
	}

	private function resolveFieldPreference( $fieldName, array $blockData = [], $blockName = '', $fieldPath = '', $byIdentity = false ): ?int {
		$fieldKey = $blockData[ '_' . $fieldName ] ?? null;
		$acfField = null;
		if ( is_string( $fieldKey ) && strpos( $fieldKey, 'field_' ) === 0 ) {
			$acfField = acf_get_field( $fieldKey );
			if ( ! $acfField ) {
				$acfField = $this->resolveCombinedCloneKey( $fieldKey );
			}
		}
		if ( ! $acfField ) {
			$scopedField = $this->getFieldFromBlockGroups( $fieldName, $blockName, $fieldPath, ! $byIdentity );
			if ( self::AMBIGUOUS === $scopedField ) {
				return WPML_COPY_CUSTOM_FIELD;
			}
			$acfField = $scopedField;
		}
		if ( ! $acfField && $byIdentity ) {
			return null;
		}
		if ( ! $acfField ) {
			$acfField = acf_get_field( $fieldName );
		}
		if ( ! $acfField ) {
			$acfField = $this->maybeGetSubfield( $fieldName, $blockName );
		}
		if ( ! $acfField && ! empty( $blockData ) ) {
			$acfField = $this->maybeGetFieldFromPrefixedSubField( $fieldName, $blockData );
		}
		if ( ! $acfField ) {
			return null;
		}

		return isset( $acfField['wpml_cf_preferences'] )
			? (int) $acfField['wpml_cf_preferences']
			: WPML_TRANSLATE_CUSTOM_FIELD;
	}

	private function resolveCombinedCloneKey( string $fieldKey ): ?array {
		for (
			$offset = strpos( $fieldKey, '_field_', strlen( 'field_' ) );
			false !== $offset;
			$offset = strpos( $fieldKey, '_field_', $offset + 1 )
		) {
			$cloneField = acf_get_field( substr( $fieldKey, 0, $offset ) );
			if ( ! is_array( $cloneField ) || 'clone' !== ( $cloneField['type'] ?? '' ) ) {
				continue;
			}

			$subField = $this->findClonedSubField( $cloneField, $fieldKey );
			if ( $subField ) {
				return $subField;
			}
		}

		return null;
	}

	private function findClonedSubField( array $cloneField, string $fieldKey, int $depth = 0 ): ?array {
		if ( $depth > self::MAX_FIELD_DEPTH ) {
			return null;
		}

		foreach ( (array) ( $cloneField['sub_fields'] ?? [] ) as $subField ) {
			if ( ! is_array( $subField ) ) {
				continue;
			}
			if ( ( $subField['key'] ?? '' ) !== $fieldKey ) {
				$subField = 'clone' === ( $subField['type'] ?? '' )
					? $this->findClonedSubField( $subField, $fieldKey, $depth + 1 )
					: null;
			}
			if ( ! $subField ) {
				continue;
			}

			return $this->inheritParentPreference( $cloneField, $subField );
		}

		return null;
	}

	private function maybeGetFieldFromPrefixedSubField( $fieldName, array $blockData ) {
		if ( null === $this->prefixedSubFieldsMap ) {
			$this->prefixedSubFieldsMap = $this->buildPrefixedSubFieldsMap( $blockData );
		}
		return isset( $this->prefixedSubFieldsMap[ $fieldName ] ) ? $this->prefixedSubFieldsMap[ $fieldName ] : null;
	}

	private function buildPrefixedSubFieldsMap( array $blockData ) {
		$map = [];
		foreach ( $blockData as $key => $fieldKey ) {
			if ( strpos( $key, '_' ) !== 0 || ! is_string( $fieldKey ) || strpos( $fieldKey, 'field_' ) !== 0 ) {
				continue;
			}
			$parentField = acf_get_field( $fieldKey );
			if ( ! $parentField ) {
				continue;
			}
			if ( 'clone' === $parentField['type'] && ! empty( $parentField['prefix_name'] ) ) {
				$prefix   = $parentField['name'] . '_';
				$groupKey = $parentField['clone'][0];

				foreach ( (array) acf_get_fields( $groupKey ) as $subField ) {
					$map[ $prefix . $subField['name'] ] = $this->inheritParentPreference( $parentField, $subField );
				}
			} elseif ( 'group' === $parentField['type'] && ! empty( $parentField['sub_fields'] ) ) {
				$prefix = $parentField['name'] . '_';

				foreach ( $parentField['sub_fields'] as $subField ) {
					$map[ $prefix . $subField['name'] ] = $this->inheritParentPreference( $parentField, $subField );
				}
			}
		}
		return $map;
	}

	private function inheritParentPreference( array $parentField, array $subField ): array {
		$parentPreference = isset( $parentField['wpml_cf_preferences'] )
			? (int) $parentField['wpml_cf_preferences']
			: WPML_TRANSLATE_CUSTOM_FIELD;
		if ( WPML_TRANSLATE_CUSTOM_FIELD !== $parentPreference ) {
			$subField['wpml_cf_preferences'] = $parentPreference;
		}

		return $subField;
	}

	private function maybeGetSubfield( $fieldName, $blockName = '' ) {
		$fieldNameParts = preg_split( '/_\d+_/', $fieldName );
		if ( is_array( $fieldNameParts ) && 1 < count( $fieldNameParts ) ) {
			$subfieldName = end( $fieldNameParts );
			$scoped       = $this->getFieldFromBlockGroups( $subfieldName, $blockName, $fieldName );

			return $scoped ? $scoped : acf_get_field( $subfieldName );
		}

		return false;
	}

	private function getFieldFromBlockGroups( $fieldName, $blockName, $fieldPath = '', $allowNameMatch = true ) {
		$blockName = (string) $blockName;
		if ( '' === $blockName || ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return null;
		}
		if ( ! isset( $this->blockGroupFieldsCache[ $blockName ] ) ) {
			$map = [
				'byName'      => [],
				'flatPaths'   => [],
				'nestedPaths' => [],
			];
			foreach ( (array) acf_get_field_groups( [ 'block' => $blockName ] ) as $fieldGroup ) {
				$this->collectFieldsByPath( (array) acf_get_fields( $fieldGroup ), $map );
			}
			$this->blockGroupFieldsCache[ $blockName ] = $map;
		}

		$map            = $this->blockGroupFieldsCache[ $blockName ];
		$fieldPath      = (string) ( '' === $fieldPath ? $fieldName : $fieldPath );
		$isNestedPath   = false !== strpos( $fieldPath, '/' );
		$normalizedPath = $isNestedPath
			? preg_replace( '~/\d+/~', '/{row}/', $fieldPath )
			: preg_replace( '/_\d+_/', '_{row}_', $fieldPath );
		$pathMap        = $isNestedPath ? $map['nestedPaths'] : $map['flatPaths'];
		if ( ! empty( $pathMap[ $normalizedPath ] ) ) {
			return $this->pickFromCandidates( $pathMap[ $normalizedPath ] );
		}

		return $allowNameMatch ? $this->pickFromCandidates( $map['byName'][ $fieldName ] ?? [] ) : null;
	}

	private function pickFromCandidates( array $candidates ) {
		if ( ! $candidates ) {
			return null;
		}
		if ( 1 === count( $candidates ) ) {
			return reset( $candidates );
		}

		$preferences = [];
		foreach ( $candidates as $candidate ) {
			$preferences[] = isset( $candidate['wpml_cf_preferences'] )
				? (int) $candidate['wpml_cf_preferences']
				: WPML_TRANSLATE_CUSTOM_FIELD;
		}

		return 1 === count( array_unique( $preferences ) )
			? reset( $candidates )
			: self::AMBIGUOUS;
	}

	private function collectFieldsByPath( $fields, array &$map, $flatPrefix = '', $nestedPrefix = '', array $seenKeys = [] ) {
		if ( count( $seenKeys ) > self::MAX_FIELD_DEPTH ) {
			return;
		}
		foreach ( (array) $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['name'] ) ) {
				continue;
			}
			if ( ! empty( $field['_clone'] ) ) {
				continue;
			}

			$fieldKey = isset( $field['key'] ) ? (string) $field['key'] : '';
			if ( '' !== $fieldKey && isset( $seenKeys[ $fieldKey ] ) ) {
				continue;
			}

			$fieldName  = (string) $field['name'];
			$flatPath   = $flatPrefix . $fieldName;
			$nestedPath = $nestedPrefix . $fieldName;

			$map['byName'][ $fieldName ][]       = $field;
			$map['flatPaths'][ $flatPath ][]     = $field;
			$map['nestedPaths'][ $nestedPath ][] = $field;

			$branchKeys = $seenKeys;
			if ( '' !== $fieldKey ) {
				$branchKeys[ $fieldKey ] = true;
			}

			$isRowContainer    = in_array( $field['type'] ?? '', [ 'repeater', 'flexible_content' ], true );
			$flatChildPrefix   = $flatPath . ( $isRowContainer ? '_{row}_' : '_' );
			$nestedChildPrefix = $nestedPath . ( $isRowContainer ? '/{row}/' : '/' );
			if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				$this->collectFieldsByPath( $field['sub_fields'], $map, $flatChildPrefix, $nestedChildPrefix, $branchKeys );
			}
			if ( ! empty( $field['layouts'] ) ) {
				foreach ( (array) $field['layouts'] as $layout ) {
					if ( ! empty( $layout['sub_fields'] ) ) {
						$this->collectFieldsByPath( $layout['sub_fields'], $map, $flatChildPrefix, $nestedChildPrefix, $branchKeys );
					}
				}
			}
		}
	}

	private function valueIsNotTranslatable( $text ) {
		return ! is_string( $text ) &&
			! is_numeric( $text ) &&
			! is_array( $text );
	}

}
