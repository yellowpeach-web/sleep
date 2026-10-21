<?php

namespace WPML\Knowledge;

use WPML\Upgrade\SchemaFacts;

class ElementKnowledge {

	const TABLE = 'icl_element_knowledge';

	const KIND_POST = 'post';
	const KIND_TERM = 'term';
	const KIND_USER = 'user';

	const MAX_ENTITY_KIND = 32;
	const MAX_ENTITY_ID   = 191;
	const MAX_PART        = 191;
	const MAX_NAME        = 64;
	const MAX_SCOPE       = 64;

	private $wpdb;

	private $available;

	public function __construct( $wpdb = null ) {
		$this->wpdb = null === $wpdb ? $GLOBALS['wpdb'] : $wpdb;
	}

	public function tableName() {
		return $this->wpdb->prefix . self::TABLE;
	}

	public function isAvailable() {
		if ( $this->usesSiteConnection() && $this->schemaFactsLoadable() ) {
			return SchemaFacts::hasTable( self::TABLE );
		}

		if ( null === $this->available ) {
			$wpdb  = $this->wpdb;
			$table = $this->tableName();

			$found = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			$this->available = 0 === strcasecmp( $found, $table );
		}

		return $this->available;
	}

	public function get( $entity_kind, $entity_id, $part, $name ) {
		if ( ! $this->isAvailable() ) {
			return null;
		}

		$wpdb = $this->wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM {$wpdb->prefix}icl_element_knowledge
				WHERE entity_kind = %s AND entity_id = %s AND part = %s AND name = %s",
				(string) $entity_kind,
				(string) $entity_id,
				$this->normalizePart( $part ),
				(string) $name
			)
		);
		$this->forgetIfQueryFailed();

		return null === $value ? null : (string) $value;
	}

	public function set( $entity_kind, $entity_id, $part, $name, $value, $scope = null ) {
		if ( ! $this->isAvailable() ) {
			return false;
		}

		$part = $this->normalizePart( $part );

		if ( ! $this->fits( $entity_kind, self::MAX_ENTITY_KIND )
			|| ! $this->fits( $entity_id, self::MAX_ENTITY_ID )
			|| ! $this->fits( $part, self::MAX_PART )
			|| ! $this->fits( $name, self::MAX_NAME )
			|| ( null !== $scope && ! $this->fits( $scope, self::MAX_SCOPE ) )
		) {
			return false;
		}

		$wpdb = $this->wpdb;

		$written = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}icl_element_knowledge
				(entity_kind, entity_id, part, name, scope, value, updated_at)
				VALUES (%s, %s, %s, %s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE scope = VALUES(scope), value = VALUES(value), updated_at = VALUES(updated_at)",
				(string) $entity_kind,
				(string) $entity_id,
				$part,
				(string) $name,
				null === $scope ? '' : (string) $scope,
				(string) $value,
				$this->now()
			)
		);
		$this->forgetIfQueryFailed();

		return false !== $written;
	}

	public function delete( $entity_kind, $entity_id, $part, $name ) {
		if ( ! $this->isAvailable() ) {
			return 0;
		}

		$deleted = $this->wpdb->delete(
			$this->tableName(),
			[
				'entity_kind' => (string) $entity_kind,
				'entity_id'   => (string) $entity_id,
				'part'        => $this->normalizePart( $part ),
				'name'        => (string) $name,
			],
			[ '%s', '%s', '%s', '%s' ]
		);
		$this->forgetIfQueryFailed();

		return (int) $deleted;
	}

	public function getEntity( $entity_kind, $entity_id, $name = null ) {
		if ( ! $this->isAvailable() ) {
			return [];
		}

		$wpdb = $this->wpdb;

		if ( null === $name ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT entity_kind, entity_id, part, name, scope, value, updated_at
					FROM {$wpdb->prefix}icl_element_knowledge
					WHERE entity_kind = %s AND entity_id = %s",
					(string) $entity_kind,
					(string) $entity_id
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT entity_kind, entity_id, part, name, scope, value, updated_at
					FROM {$wpdb->prefix}icl_element_knowledge
					WHERE entity_kind = %s AND entity_id = %s AND name = %s",
					(string) $entity_kind,
					(string) $entity_id,
					(string) $name
				),
				ARRAY_A
			);
		}
		$this->forgetIfQueryFailed();

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( [ $this, 'presentRow' ], $rows );
	}

	public function deleteEntity( $entity_kind, $entity_id, $name = null ) {
		if ( ! $this->isAvailable() ) {
			return 0;
		}

		$where  = [
			'entity_kind' => (string) $entity_kind,
			'entity_id'   => (string) $entity_id,
		];
		$format = [ '%s', '%s' ];

		if ( null !== $name ) {
			$where['name'] = (string) $name;
			$format[]      = '%s';
		}

		$deleted = $this->wpdb->delete( $this->tableName(), $where, $format );
		$this->forgetIfQueryFailed();

		return (int) $deleted;
	}

	public function deleteScope( $scope ) {
		if ( ! $this->isAvailable() || '' === (string) $scope ) {
			return 0;
		}

		$deleted = $this->wpdb->delete( $this->tableName(), [ 'scope' => (string) $scope ], [ '%s' ] );
		$this->forgetIfQueryFailed();

		return (int) $deleted;
	}

	private function usesSiteConnection() {
		return isset( $GLOBALS['wpdb'] ) && $this->wpdb === $GLOBALS['wpdb'];
	}

	protected function schemaFactsLoadable() {
		return class_exists( SchemaFacts::class );
	}

	private function forgetIfQueryFailed() {
		if ( $this->usesSiteConnection() && $this->schemaFactsLoadable() ) {
			SchemaFacts::forgetIfQueryFailed( $this->wpdb );

			return;
		}

		if ( isset( $this->wpdb->last_error ) && '' !== (string) $this->wpdb->last_error ) {
			$this->available = null;
		}
	}

	private function presentRow( array $row ) {
		$row['part']  = isset( $row['part'] ) && '' !== $row['part'] ? (string) $row['part'] : null;
		$row['scope'] = isset( $row['scope'] ) && '' !== $row['scope'] ? (string) $row['scope'] : null;

		return $row;
	}

	private function normalizePart( $part ) {
		return null === $part ? '' : (string) $part;
	}

	private function fits( $value, $length ) {
		return mb_strlen( (string) $value ) <= $length;
	}

	private function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}
}
