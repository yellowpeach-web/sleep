<?php

namespace ACFML\Field;

use ACFML\Helper\Fields;
use WPML\FP\Obj;

class OrphanRowValues implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_REST_Action, \IWPML_DIC_Action {

	const ROW_INDEX_PATTERN = '/_(\d+)(?=_)/';

	private $containerRows;

	public function __construct( ContainerRows $containerRows ) {
		$this->containerRows = $containerRows;
	}

	public function add_hooks() {
		add_filter( 'wpml_tm_translation_job_data', [ $this, 'removeOrphanRowValues' ], 10, 2 );
		add_action( 'acf/delete_value/type=clone', [ $this, 'deleteClonedValues' ], 10, 3 );
	}

	public function removeOrphanRowValues( $package, $post ) {
		if ( ! $post instanceof \WP_Post || ! is_array( $package['contents'] ?? null ) ) {
			return $package;
		}

		$isOrphan = [];
		foreach ( array_keys( $package['contents'] ) as $contentKey ) {
			list( $metaKey ) = \WPML_TM_Field_Type_Encoding::decode( (string) $contentKey );
			if ( '' === $metaKey ) {
				continue;
			}
			if ( ! isset( $isOrphan[ $metaKey ] ) ) {
				$isOrphan[ $metaKey ] = $this->isOrphanRowValue( (int) $post->ID, $metaKey );
			}
			if ( $isOrphan[ $metaKey ] ) {
				unset( $package['contents'][ $contentKey ] );
			}
		}

		return $package;
	}

	public function deleteClonedValues( $postId, $metaKey, $field ) {
		if ( ! $this->isSubFieldClone( $field ) ) {
			return;
		}

		$parentPrefix = substr( $field['name'], 0, -strlen( $field['_name'] ) );
		foreach ( $field['sub_fields'] as $subField ) {
			$subField['name'] = $parentPrefix . $subField['name'];
			acf_delete_value( $postId, $subField );
		}
	}

	private function isSubFieldClone( $field ): bool {
		if ( ! is_array( $field ) || empty( $field['sub_fields'] ) || empty( $field['_name'] ) ) {
			return false;
		}

		$name      = (string) Obj::prop( 'name', $field );
		$cloneName = (string) $field['_name'];

		return $name !== $cloneName && substr( $name, -strlen( $cloneName ) ) === $cloneName;
	}

	private function isOrphanRowValue( int $postId, string $metaKey ): bool {
		preg_match_all( self::ROW_INDEX_PATTERN, $metaKey, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$offset    = $match[0][1];
			$container = substr( $metaKey, 0, $offset );
			$field     = $this->containerRows->getContainerField( $postId, $container );
			if ( ! $field ) {
				continue;
			}

			$index     = (int) $match[1][0];
			$fieldName = substr( $metaKey, $offset + strlen( $match[0][0] ) + 1 );
			if ( ! $this->rowHoldsField( $postId, $container, $field, $index, $fieldName ) ) {
				return true;
			}
		}

		return false;
	}

	private function rowHoldsField( int $postId, string $container, array $field, int $index, string $fieldName ): bool {
		$rows = $this->containerRows->getRows( $postId, $container );

		if ( 'repeater' === $field['type'] ) {
			return $index < (int) $rows;
		}

		$layoutNames = is_array( $rows ) ? array_values( $rows ) : [];
		if ( ! isset( $layoutNames[ $index ] ) ) {
			return false;
		}

		$layout               = Fields::getLayoutsByName( $field )[ $layoutNames[ $index ] ] ?? null;
		$layoutNoLongerExists = ! $layout;
		if ( $layoutNoLongerExists ) {
			return true;
		}

		foreach ( (array) Obj::propOr( [], 'sub_fields', $layout ) as $subField ) {
			foreach ( $this->getStoredNames( $subField ) as $storedName ) {
				if ( $fieldName === $storedName || 0 === strpos( $fieldName, $storedName . '_' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function getStoredNames( $subField ): array {
		$names = [ (string) Obj::prop( 'name', $subField ) ];

		if ( 'clone' === Obj::prop( 'type', $subField ) ) {
			$names = array_merge( $names, $this->getNamesStoredWithoutClonePrefix( $subField ) );
		}

		return array_filter( $names );
	}

	private function getNamesStoredWithoutClonePrefix( $cloneField ): array {
		$names = [];

		foreach ( (array) Obj::propOr( [], 'sub_fields', $cloneField ) as $clonedField ) {
			$names = array_merge( $names, $this->getStoredNames( $clonedField ) );
		}

		return $names;
	}
}
