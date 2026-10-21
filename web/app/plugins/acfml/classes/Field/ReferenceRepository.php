<?php

namespace ACFML\Field;

class ReferenceRepository {

	const META_TYPE_POST = 'post';
	const META_TYPE_TERM = 'term';

	private static $cache = [];

	public function storeIfMissing( $metaType, $objectId, $metaKey, $reference ) {
		$context  = $this->getContext( $metaType );
		$objectId = (int) $objectId;

		if ( null === $context || $this->isStored( $context, $objectId, $metaKey, $reference ) ) {
			return;
		}

		$result = update_metadata( $metaType, $objectId, $metaKey, $reference );
		if ( false !== $result ) {
			self::$cache[ $context['blogId'] ][ $context['table'] ][ $objectId ][ $metaKey ] = [ $reference ];
		}
	}

	public function applyBatch( $metaType, $objectId, array $details ) {
		$context  = $this->getContext( $metaType );
		$objectId = (int) $objectId;

		if (
			null === $context
			|| ! isset( self::$cache[ $context['blogId'] ][ $context['table'] ][ $objectId ] )
		) {
			return;
		}

		foreach ( $details as $metaKey => $detail ) {
			if (
				! is_string( $metaKey )
				|| '' === $metaKey
				|| '_' !== $metaKey[0]
				|| ! is_array( $detail )
				|| ! isset( $detail['values_after'] )
				|| ! is_array( $detail['values_after'] )
			) {
				continue;
			}

			self::$cache[ $context['blogId'] ][ $context['table'] ][ $objectId ][ $metaKey ] =
				array_values( $detail['values_after'] );
		}
	}

	private function isStored( $context, $objectId, $metaKey, $reference ) {
		$map    = $this->getMap( $context, $objectId );
		$stored = isset( $map[ $metaKey ] ) ? $map[ $metaKey ] : [];

		return 1 === count( $stored ) && maybe_unserialize( $stored[0] ) === $reference;
	}

	private function getContext( $metaType ) {
		if ( ! in_array( $metaType, [ self::META_TYPE_POST, self::META_TYPE_TERM ], true ) ) {
			return null;
		}

		$table = _get_meta_table( $metaType );
		if ( ! $table ) {
			return null;
		}

		return [
			'blogId' => (int) get_current_blog_id(),
			'table'  => $table,
			'column' => $metaType . '_id',
		];
	}

	private function getMap( $context, $objectId ) {
		$blogId = $context['blogId'];
		$table  = $context['table'];

		if ( ! isset( self::$cache[ $blogId ][ $table ][ $objectId ] ) ) {
			global $wpdb;
			$column = $context['column'];
			$map    = [];
			$rows   = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_key, meta_value FROM {$table} WHERE {$column} = %d AND meta_key LIKE %s",
					$objectId,
					'\_%'
				),
				ARRAY_N
			);
			if ( ! is_array( $rows ) ) {
				return [];
			}

			foreach ( $rows as $row ) {
				$map[ $row[0] ][] = $row[1];
			}
			self::$cache[ $blogId ][ $table ][ $objectId ] = $map;
		}

		return self::$cache[ $blogId ][ $table ][ $objectId ];
	}
}
