<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\FieldGroup;
use ACFML\Helper\Fields;
use WPML\FP\Obj;

class NameCollisions {

	const ELEMENT_TYPE = 'post_' . FieldGroup::CPT;

	private $fieldNamePatterns;

	public function __construct( FieldNamePatterns $fieldNamePatterns ) {
		$this->fieldNamePatterns = $fieldNamePatterns;
	}

	public function find( $fieldGroup ) {
		$names = $this->getTopLevelFieldNames( $fieldGroup );

		if ( ! $names ) {
			return [];
		}

		$fieldGroupId = (int) Obj::propOr( 0, 'ID', $fieldGroup );
		$ownGroupIds  = $this->getFieldGroupAndTranslationIds( $fieldGroupId );
		$collisions   = $this->findInStoredGroups( $names, $ownGroupIds );

		return $this->keepOneCopyPerOtherGroup(
			array_merge(
				$collisions,
				$this->findInNamePatterns( $names, $this->getOwnGroupKeys( $fieldGroup, $fieldGroupId, $ownGroupIds ), $collisions )
			),
			$fieldGroupId
		);
	}

	private function keepOneCopyPerOtherGroup( array $collisions, int $fieldGroupId ): array {
		$keptCollisions     = [];
		$savedGroupLanguage = null;

		foreach ( $collisions as $collision ) {
			$copyKey = $collision['field_name'] . '|' . $this->getTranslationGroupKey( $collision );

			if ( ! isset( $keptCollisions[ $copyKey ] ) ) {
				$keptCollisions[ $copyKey ] = $collision;
				continue;
			}

			$savedGroupLanguage = $savedGroupLanguage ?? $this->getLanguage( $fieldGroupId );

			if ( $savedGroupLanguage && $this->getLanguage( $collision['group_id'] ) === $savedGroupLanguage ) {
				$keptCollisions[ $copyKey ] = $collision;
			}
		}

		return array_values( $keptCollisions );
	}

	private function getTranslationGroupKey( array $collision ): string {
		$trid = $collision['group_id']
			? apply_filters( 'wpml_element_trid', null, $collision['group_id'], self::ELEMENT_TYPE )
			: null;

		return $trid ? 'trid:' . $trid : 'key:' . $collision['group_key'];
	}

	private function getLanguage( int $fieldGroupId ): ?string {
		return apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $fieldGroupId,
				'element_type' => FieldGroup::CPT,
			]
		);
	}

	private function getFieldGroupAndTranslationIds( int $fieldGroupId ): array {
		$trid         = apply_filters( 'wpml_element_trid', null, $fieldGroupId, self::ELEMENT_TYPE );
		$translations = $trid ? (array) apply_filters( 'wpml_get_element_translations', [], $trid, self::ELEMENT_TYPE ) : [];

		$ids = [ $fieldGroupId ];
		foreach ( $translations as $translation ) {
			$ids[] = (int) Obj::propOr( 0, 'element_id', $translation );
		}

		return array_values( array_unique( $ids ) );
	}

	private function getOwnGroupKeys( array $fieldGroup, int $fieldGroupId, array $ownGroupIds ): array {
		$keys = [ (string) Obj::propOr( '', 'key', $fieldGroup ) ];

		foreach ( $ownGroupIds as $groupId ) {
			if ( $fieldGroupId !== $groupId ) {
				$keys[] = (string) get_post_field( 'post_name', $groupId );
			}
		}

		return array_values( array_unique( array_filter( $keys ) ) );
	}

	private function getTopLevelFieldNames( $fieldGroup ) {
		$names = [];

		foreach ( Fields::getFresh( $fieldGroup ) as $field ) {
			$name = Obj::propOr( '', 'name', $field );

			if ( is_string( $name ) && '' !== $name ) {
				$names[] = $name;
			}
		}

		return array_values( array_unique( $names ) );
	}

	private function findInStoredGroups( array $names, array $ownGroupIds ) {
		global $wpdb;

		$placeholders      = implode( ', ', array_fill( 0, count( $names ), '%s' ) );
		$groupPlaceholders = implode( ', ', array_fill( 0, count( $ownGroupIds ), '%d' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.post_excerpt AS field_name, g.ID AS group_id, g.post_name AS group_key, g.post_title AS group_title
				 FROM {$wpdb->posts} f
				 INNER JOIN {$wpdb->posts} g ON g.ID = f.post_parent
				 WHERE f.post_type = 'acf-field' AND f.post_status = 'publish'
				   AND g.post_type = 'acf-field-group' AND g.post_status = 'publish'
				   AND g.ID NOT IN ($groupPlaceholders)
				   AND f.post_excerpt IN ($placeholders)
				 GROUP BY f.post_excerpt, g.ID, g.post_name, g.post_title",
				array_merge( $ownGroupIds, $names )
			)
		);

		$collisions = [];

		foreach ( (array) $rows as $row ) {
			$collisions[] = [
				'field_name'  => (string) $row->field_name,
				'group_id'    => (int) $row->group_id,
				'group_key'   => (string) $row->group_key,
				'group_title' => (string) $row->group_title,
			];
		}

		return $collisions;
	}

	private function findInNamePatterns( $names, array $ownGroupKeys, array $alreadyFound ) {
		$exactNamePatterns = [];

		foreach ( $this->fieldNamePatterns->getAllPatterns() as $groupKey => $patterns ) {
			if ( in_array( $groupKey, $ownGroupKeys, true ) ) {
				continue;
			}

			foreach ( (array) $patterns as $pattern ) {
				$exactNamePatterns[ $pattern ][ $groupKey ] = $groupKey;
			}
		}

		$seen = [];
		foreach ( $alreadyFound as $collision ) {
			$seen[ $collision['group_key'] . '|' . $collision['field_name'] ] = true;
		}

		$collisions = [];
		$missing    = [];

		foreach ( $names as $name ) {
			$pattern = preg_quote( $name, '/' );

			if ( ! isset( $exactNamePatterns[ $pattern ] ) ) {
				continue;
			}

			foreach ( $exactNamePatterns[ $pattern ] as $groupKey ) {
				if ( isset( $seen[ $groupKey . '|' . $name ] ) || isset( $missing[ $groupKey ] ) ) {
					continue;
				}

				$seen[ $groupKey . '|' . $name ] = true;

				$group = acf_get_field_group( $groupKey );

				if ( ! is_array( $group ) ) {
					$missing[ $groupKey ] = true;
					$this->fieldNamePatterns->removeGroup( $groupKey );

					continue;
				}

				$collisions[] = $this->describeGroup( $groupKey, $name, $group );
			}
		}

		return $collisions;
	}

	private function describeGroup( $groupKey, $fieldName, array $group ) {
		return [
			'field_name'  => $fieldName,
			'group_id'    => (int) Obj::propOr( 0, 'ID', $group ),
			'group_key'   => $groupKey,
			'group_title' => (string) Obj::propOr( $groupKey, 'title', $group ),
		];
	}
}
