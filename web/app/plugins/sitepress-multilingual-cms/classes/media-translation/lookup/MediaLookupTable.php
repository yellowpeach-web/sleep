<?php

namespace WPML\Media\Lookup;

class MediaLookupTable {

	const VARIANT_GUID          = 0;
	const VARIANT_ATTACHED_FILE = 1;

	const CHUNK = 500;

	private $wpdb;

	private $schema;

	private $hasher;

	public function __construct( \wpdb $wpdb, MediaLookupSchema $schema, MediaLookupHasher $hasher ) {
		$this->wpdb   = $wpdb;
		$this->schema = $schema;
		$this->hasher = $hasher;
	}

	public function probeBatch( array $hex_hashes, $language ) {
		$hex_hashes = array_values( array_unique( array_filter( $hex_hashes, [ $this->hasher, 'isValidHex' ] ) ) );
		if ( ! $hex_hashes || ! is_string( $language ) || '' === $language ) {
			return [];
		}

		$wpdb  = $this->wpdb;
		$found = [];

		foreach ( array_chunk( $hex_hashes, self::CHUNK ) as $chunk ) {
			$hash_literals = implode( ', ', array_map( function ( $hex ) {
				return '0x' . $hex;
			}, $chunk ) );
			foreach (
				(array) $this->wpdb->get_results(
					sprintf(
						"SELECT HEX(l.url_hash) AS hash_hex, l.attachment_id, l.variant, l.expires_at
						FROM `{$wpdb->prefix}icl_media_url_lookup` l
						LEFT JOIN `{$wpdb->prefix}icl_translations` t
							ON t.element_id = l.attachment_id
							AND t.element_type = 'post_attachment'
							AND t.language_code = l.language_code
						WHERE l.language_code = 0x%s AND l.url_hash IN (%s)
							AND ( l.attachment_id = 0 OR t.translation_id IS NOT NULL )",
						esc_sql( bin2hex( $language ) ),
						esc_sql( $hash_literals )
					),
					ARRAY_A
				) as $row
			) {
				$found[ strtolower( $row['hash_hex'] ) ] = [
					'attachment_id' => (int) $row['attachment_id'],
					'variant'       => (int) $row['variant'],
					'expires_at'    => (int) $row['expires_at'],
				];
			}
		}

		return $found;
	}

	public function upsertBatch( array $rows, $language ) {
		$rows = array_filter( $rows, function ( $row ) {
			return isset( $row['hash'] ) && $this->hasher->isValidHex( $row['hash'] );
		} );
		if ( ! $rows || ! is_string( $language ) || '' === $language ) {
			return 0;
		}

		$wpdb         = $this->wpdb;
		$written      = 0;
		$language_hex = bin2hex( $language );
		foreach ( array_chunk( array_values( $rows ), self::CHUNK ) as $chunk ) {
			$values = [];
			foreach ( $chunk as $row ) {
				$attachment_id = (int) ( isset( $row['attachment_id'] ) ? $row['attachment_id'] : 0 );
				$variant       = (int) ( isset( $row['variant'] ) ? $row['variant'] : 0 );
				$expires_at    = (int) ( isset( $row['expires_at'] ) ? $row['expires_at'] : 0 );
				$values[]      = sprintf(
					'(0x%s,0x%s,%d,%d,%d)',
					esc_sql( $row['hash'] ),
					esc_sql( $language_hex ),
					$attachment_id,
					$variant,
					$expires_at
				);
			}
			$values = implode( ', ', $values );

			$result = $this->wpdb->query(
				sprintf(
					"INSERT INTO `{$wpdb->prefix}icl_media_url_lookup` (url_hash, language_code, attachment_id, variant, expires_at)
					VALUES %s
					ON DUPLICATE KEY UPDATE
						attachment_id = VALUES(attachment_id),
						variant = VALUES(variant),
						expires_at = VALUES(expires_at)",
					esc_sql( $values )
				)
			);

			if ( false !== $result ) {
				$written += (int) $result;
			}
		}

		return $written;
	}

	public function deleteBatch( array $hex_hashes, $language ) {
		$hex_hashes = array_values( array_unique( array_filter( $hex_hashes, [ $this->hasher, 'isValidHex' ] ) ) );
		if ( ! $hex_hashes || ! is_string( $language ) || '' === $language ) {
			return 0;
		}

		$wpdb    = $this->wpdb;
		$deleted = 0;

		foreach ( array_chunk( $hex_hashes, self::CHUNK ) as $chunk ) {
			$hash_literals = implode( ', ', array_map( function ( $hex ) {
				return '0x' . $hex;
			}, $chunk ) );

			$result = $this->wpdb->query(
				sprintf(
					"DELETE FROM `{$wpdb->prefix}icl_media_url_lookup` WHERE language_code = 0x%s AND url_hash IN (%s)",
					esc_sql( bin2hex( $language ) ),
					esc_sql( $hash_literals )
				)
			);

			if ( false !== $result ) {
				$deleted += (int) $result;
			}
		}

		return $deleted;
	}

	public function tombstoneBatch( array $hex_hashes, $language, $ttl_seconds, $variant = self::VARIANT_GUID ) {
		$expires_at = time() + max( 0, (int) $ttl_seconds );

		return $this->upsertBatch(
			array_map( function ( $hex ) use ( $expires_at, $variant ) {
				return [
					'hash'          => $hex,
					'attachment_id' => 0,
					'variant'       => (int) $variant,
					'expires_at'    => $expires_at,
				];
			}, $hex_hashes ),
			$language
		);
	}

	public function counts() {
		$wpdb = $this->wpdb;
		$row  = $this->wpdb->get_row(
			"SELECT COUNT(*) AS total,
				SUM(attachment_id > 0) AS positives,
				SUM(attachment_id = 0) AS tombstones
			FROM `{$wpdb->prefix}icl_media_url_lookup`",
			ARRAY_A
		);

		return [
			'total'      => (int) ( isset( $row['total'] ) ? $row['total'] : 0 ),
			'positives'  => (int) ( isset( $row['positives'] ) ? $row['positives'] : 0 ),
			'tombstones' => (int) ( isset( $row['tombstones'] ) ? $row['tombstones'] : 0 ),
		];
	}

	public function pruneTombstones( $cap ) {
		$wpdb    = $this->wpdb;
		$deleted = 0;
		$now     = time();

		$result = $this->wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$wpdb->prefix}icl_media_url_lookup` WHERE attachment_id = 0 AND expires_at < %d",
				$now
			)
		);
		if ( false !== $result ) {
			$deleted += (int) $result;
		}

		$cap      = max( 0, (int) $cap );
		$existing = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}icl_media_url_lookup` WHERE attachment_id = 0" );

		if ( $existing > $cap ) {
			$overflow = $existing - $cap;
			$result   = $this->wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$wpdb->prefix}icl_media_url_lookup` WHERE attachment_id = 0 ORDER BY expires_at ASC LIMIT %d",
					$overflow
				)
			);
			if ( false !== $result ) {
				$deleted += (int) $result;
			}
		}

		return $deleted;
	}
}
