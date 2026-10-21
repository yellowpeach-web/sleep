<?php

namespace WPML\Media\Lookup;

class MediaLookupService {

	const STATUS_FOUND     = 'found';
	const STATUS_NOT_FOUND = 'not_found';
	const STATUS_UNDECIDED = 'undecided';

	private $schema;

	private $table;

	private $hasher;

	private $verifier;

	private $tombstone_ttl;

	private $enabled;

	private $available;

	public function __construct(
		MediaLookupSchema $schema,
		MediaLookupTable $table,
		MediaLookupHasher $hasher,
		MediaLookupVerifier $verifier,
		$tombstone_ttl,
		$enabled = true
	) {
		$this->schema        = $schema;
		$this->table         = $table;
		$this->hasher        = $hasher;
		$this->verifier      = $verifier;
		$this->tombstone_ttl = (int) $tombstone_ttl;
		$this->enabled       = (bool) $enabled;
	}

	public function isAvailable() {
		if ( null === $this->available ) {
			$this->available = $this->enabled && $this->schema->exists();
		}

		return $this->available;
	}

	public function decideMany( array $queries ) {
		$undecided = [ 'status' => self::STATUS_UNDECIDED, 'id' => 0 ];

		if ( ! $this->isAvailable() || ! $queries ) {
			return array_fill_keys( array_keys( $queries ), $undecided );
		}

		$decisions = [];
		$by_language = [];
		foreach ( $queries as $key => $query ) {
			$decisions[ $key ] = $undecided;
			$by_language[ $query['language'] ][ $key ] = $this->hasher->hash( $query['language'], $query['value'] );
		}

		$to_verify = [];
		$now       = time();

		foreach ( $by_language as $language => $hashes ) {
			$rows = $this->table->probeBatch( array_values( $hashes ), $language );

			foreach ( $hashes as $key => $hash ) {
				if ( ! isset( $rows[ $hash ] ) ) {
					continue;
				}
				$row = $rows[ $hash ];

				if ( 0 === $row['attachment_id'] ) {
					if ( $row['expires_at'] > $now ) {
						$decisions[ $key ] = [ 'status' => self::STATUS_NOT_FOUND, 'id' => 0 ];
					}
					continue;
				}

				$to_verify[ $key ] = [
					'id'      => $row['attachment_id'],
					'variant' => (int) $queries[ $key ]['variant'],
					'value'   => $queries[ $key ]['value'],
					'hash'    => $hash,
					'language' => $language,
				];
			}
		}

		if ( $to_verify ) {
			$verdicts    = $this->verifier->verifyBatch( array_values( $to_verify ) );
			$stale_by_language = [];

			foreach ( array_keys( $to_verify ) as $position => $key ) {
				$candidate = $to_verify[ $key ];

				if ( ! empty( $verdicts[ $position ] ) ) {
					$decisions[ $key ] = [ 'status' => self::STATUS_FOUND, 'id' => $candidate['id'] ];
				} else {
					$stale_by_language[ $candidate['language'] ][] = $candidate['hash'];
				}
			}

			foreach ( $stale_by_language as $language => $hashes ) {
				$this->table->deleteBatch( $hashes, $language );
			}
		}

		return $decisions;
	}

	public function recordMany( array $results ) {
		if ( ! $this->isAvailable() || ! $results ) {
			return;
		}

		$positive_by_language  = [];
		$tombstone_by_language = [];

		foreach ( $results as $result ) {
			$hash = $this->hasher->hash( $result['language'], $result['value'] );

			if ( (int) $result['id'] > 0 ) {
				$positive_by_language[ $result['language'] ][] = [
					'hash'          => $hash,
					'attachment_id' => (int) $result['id'],
					'variant'       => (int) $result['variant'],
					'expires_at'    => 0,
				];
			} else {
				$tombstone_by_language[ $result['language'] ][ $hash ] = (int) $result['variant'];
			}
		}

		foreach ( $positive_by_language as $language => $rows ) {
			$this->table->upsertBatch( $rows, $language );
		}

		foreach ( $tombstone_by_language as $language => $hashes ) {
			$by_variant = [];
			foreach ( $hashes as $hash => $variant ) {
				$by_variant[ $variant ][] = $hash;
			}
			foreach ( $by_variant as $variant => $variant_hashes ) {
				$this->table->tombstoneBatch( $variant_hashes, $language, $this->tombstone_ttl, $variant );
			}
		}
	}
}
